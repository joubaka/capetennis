<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class DeploymentConfigTest extends TestCase
{
    public function test_readable_approval_uses_exact_preflight_and_preserves_incident_repair_gate(): void
    {
        $script = (string) file_get_contents(dirname(__DIR__, 2).'/deploy.sh');

        $this->assertStringContainsString('--approved-migrations)', $script);
        $this->assertStringContainsString('--approved-migrations=*)', $script);
        $this->assertStringContainsString('Supply migration approval only once', $script);
        $this->assertStringContainsString('printf \'%s\' "$2" | base64', $script);
        $this->assertStringContainsString('[ "$PLAIN_MIGRATION_APPROVAL" = true ] && grep -Fxq', $script);
        $this->assertStringContainsString('deploy-ct main --expected-sha %s --approved-migrations %s', $script);
    }

    public function test_predictable_checkout_and_dependency_failures_precede_migration_approval(): void
    {
        $script = (string) file_get_contents(dirname(__DIR__, 2).'/deploy.sh');
        $prompt = strpos($script, 'Type DEPLOY');

        foreach (['merge-base --is-ancestor HEAD', 'Live deploy rejected:', 'Composer is unavailable;'] as $check) {
            $position = strpos($script, $check);
            $this->assertNotFalse($position);
            $this->assertLessThan($prompt, $position);
        }
    }

    public function test_release_allowlists_every_new_migration_after_the_reviewed_baseline(): void
    {
        $root = dirname(__DIR__, 2);
        $config = (string) file_get_contents($root.'/deploy.config');

        $this->assertSame(1, preg_match('/^MIGRATION_PATHS="([^"]*)"$/m', $config, $matches));

        $allowlistedMigrations = preg_split('/\s+/', trim($matches[1])) ?: [];
        $migrationFiles = glob($root.'/database/migrations/*.php') ?: [];
        $reviewedBaseline = '2026_09_27_000000';

        // Add only an exact, explicitly reviewed one-off migration here when it
        // must remain manual and must never be run by the normal deploy command.
        $manualOnlyMigrations = [];

        $requiredMigrations = array_values(array_filter(
            array_map(
                static fn (string $path): string => 'database/migrations/'.basename($path),
                $migrationFiles
            ),
            static fn (string $path): bool => basename($path) >= $reviewedBaseline
                && ! in_array($path, $manualOnlyMigrations, true)
        ));
        sort($requiredMigrations);

        $missingMigrations = array_values(array_diff($requiredMigrations, $allowlistedMigrations));

        $this->assertSame(
            [],
            $missingMigrations,
            'New migrations must be added to deploy.config or explicitly reviewed as manual-only: '.implode(', ', $missingMigrations)
        );
    }

    public function test_deploy_preflights_exact_target_migrations_before_downtime_or_merge(): void
    {
        $script = file_get_contents(dirname(__DIR__, 2).'/deploy.sh');

        $preflight = 'run_php "$PREFLIGHT_DIR/preflight.php"';
        $downtime = 'run_php "$APP_PATH/artisan" down --retry=60';
        $merge = 'git -C "$APP_PATH" merge --ff-only "$FETCHED_MAIN"';

        $this->assertStringContainsString('git -C "$APP_PATH" show "$FETCHED_MAIN:deploy.config"', $script);
        $this->assertStringContainsString('git -C "$APP_PATH" ls-tree -r --name-only "$FETCHED_MAIN"', $script);
        $this->assertStringContainsString('--approved-migrations-b64', $script);
        $this->assertLessThan(strpos($script, $downtime), strpos($script, $preflight));
        $this->assertLessThan(strpos($script, $merge), strpos($script, $preflight));
        $this->assertStringContainsString('done < "$PREFLIGHT_DIR/pending-migrations"', $script);
        $this->assertStringNotContainsString('for migration in $MIGRATION_PATHS', $script);
        $this->assertStringContainsString('diff --name-only HEAD.."$FETCHED_MAIN"', $script);
        $this->assertStringContainsString('[ "$REMOTE_HEAD" = "$FETCHED_MAIN" ] || fail', $script);
        $this->assertStringNotContainsString('HEAD..origin/main', $script);
        $this->assertStringNotContainsString('merge --ff-only "${EXPECTED_SHA:-origin/main}"', $script);
    }

    public function test_interactive_deploy_can_review_but_automation_still_requires_explicit_pending_approval(): void
    {
        $script = file_get_contents(dirname(__DIR__, 2).'/deploy.sh');

        $this->assertStringContainsString('[ -t 0 ] && [ -t 1 ] || fail \'Non-interactive deployments with pending migrations require --approved-migrations or --approved-migrations-b64\'', $script);
        $this->assertStringContainsString('Exact pending migrations for the target commit:', $script);
        $this->assertStringContainsString('Type DEPLOY to approve this exact migration set and continue:', $script);
        $this->assertStringContainsString('IFS= read -r INTERACTIVE_APPROVAL', $script);
        $this->assertStringContainsString('[ "$INTERACTIVE_APPROVAL" = DEPLOY ] || fail', $script);
        $this->assertStringContainsString("'Migration preflight failed: Pending migrations lack explicit per-run approval: '*", $script);
        $this->assertStringContainsString('Unable to determine an exact safe migration set', $script);
        $this->assertStringContainsString('--approved-migrations-b64', $script);
        $this->assertSame(3, substr_count($script, 'run_php "$PREFLIGHT_DIR/preflight.php"'));
        $this->assertStringContainsString('The pending Wilson Masters incident repair requires deliberate --approved-migrations-b64 input', $script);
    }

    public function test_maintenance_deployment_stays_offline_on_failure_and_syncs_assets_before_reopening(): void
    {
        $script = file_get_contents(dirname(__DIR__, 2).'/deploy.sh');
        $handler = substr($script, strpos($script, 'deployment_exit()'), strpos($script, 'sync_public_html()') - strpos($script, 'deployment_exit()'));

        $this->assertStringContainsString('trap deployment_exit EXIT', $script);
        $this->assertStringNotContainsString('artisan" up', $handler);
        $this->assertStringContainsString('mix-manifest.json', $script);
        $this->assertLessThan(strpos($script, 'sync_public_html; run_php'), strpos($script, 'composer install'));
        $this->assertLessThan(strpos($script, 'if [ "$LIVE_DEPLOY" = false ]; then run_php "$APP_PATH/artisan" up'), strpos($script, 'sync_public_html; run_php'));
    }

    public function test_deploy_does_not_execute_checkout_config_before_checkout_trust_checks(): void
    {
        $script = file_get_contents(dirname(__DIR__, 2).'/deploy.sh');
        $firstConfigSource = strpos($script, '[ -f "$APP_PATH/deploy.config" ] && source "$APP_PATH/deploy.config"');

        $this->assertNotFalse($firstConfigSource);
        foreach ([
            '[ -d "$APP_PATH/.git" ] || fail "$APP_PATH is not a Git checkout"',
            'git -C "$APP_PATH" status --porcelain',
            'git -C "$APP_PATH" branch --show-current',
        ] as $trustCheck) {
            $trustCheckPosition = strpos($script, $trustCheck);
            $this->assertNotFalse($trustCheckPosition);
            $this->assertLessThan(
                $firstConfigSource,
                $trustCheckPosition,
                "Adversarial deploy.config content must not execute before: {$trustCheck}"
            );
        }
    }

    public function test_deploy_only_reconciles_masters_payments_when_explicitly_requested(): void
    {
        $script = file_get_contents(dirname(__DIR__, 2).'/deploy.sh');
        $reconciliation = 'masters:reconcile-payments --apply';

        $this->assertStringContainsString($reconciliation, $script);
        $this->assertStringContainsString('--reconcile-masters-payments) RECONCILE_MASTERS_PAYMENTS=true', $script);
        $this->assertStringContainsString('if [ "$RECONCILE_MASTERS_PAYMENTS" = true ]; then', $script);
    }

    public function test_automated_deploy_can_pin_and_verify_an_exact_main_commit(): void
    {
        $script = file_get_contents(dirname(__DIR__, 2).'/deploy.sh');

        $this->assertStringContainsString('--expected-sha', $script);
        $this->assertStringContainsString('[ "$FETCHED_MAIN" != "$EXPECTED_SHA" ]', $script);
        $this->assertStringContainsString('merge --ff-only "$FETCHED_MAIN"', $script);
        $this->assertStringContainsString('[ "$REMOTE_HEAD" = "$FETCHED_MAIN" ]', $script);
        $this->assertStringContainsString('APP_PATH="${DEPLOY_APP_PATH_OVERRIDE:-$SCRIPT_PATH}"', $script);
        $this->assertStringContainsString('CANONICAL_APP_PATH="$APP_PATH"', $script);
        $this->assertSame(2, substr_count($script, 'APP_PATH="$CANONICAL_APP_PATH"'));
    }

    public function test_release_config_cannot_replace_cli_deployment_controls(): void
    {
        $script = file_get_contents(dirname(__DIR__, 2).'/deploy.sh');

        foreach ([
            'FETCHED_MAIN',
            'EXPECTED_SHA',
            'RECONCILE_MASTERS_PAYMENTS',
            'REQUESTED_BRANCH',
            'SKIP_MIGRATIONS',
            'SKIP_DEPS',
            'LIVE_DEPLOY',
            'APP_IS_DOWN',
            'APPROVED_MIGRATIONS_B64',
            'PREFLIGHT_DIR',
        ] as $control) {
            $this->assertStringContainsString('LOCKED_'.$control.'="$'.$control.'"', $script);
            $this->assertStringContainsString($control.'="$LOCKED_'.$control.'"', $script);
        }
    }

    public function test_github_deployment_is_manual_and_does_not_invoke_payment_reconciliation(): void
    {
        $workflow = file_get_contents(dirname(__DIR__, 2).'/.github/workflows/deploy.yml');

        $this->assertStringContainsString('workflow_dispatch:', $workflow);
        $this->assertStringContainsString('approved_migrations:', $workflow);
        $this->assertStringNotContainsString("\n  push:", $workflow);
        $this->assertStringNotContainsString("\n  pull_request:", $workflow);
        $this->assertStringContainsString('environment: production', $workflow);
        $this->assertStringContainsString('cancel-in-progress: false', $workflow);
        $this->assertStringContainsString('SERVER_APP_PATH: ${{ secrets.SERVER_APP_PATH }}', $workflow);
        $this->assertStringContainsString('git show "$DEPLOY_SHA:deploy.sh" > "$EXACT_DEPLOY_SCRIPT"', $workflow);
        $this->assertStringContainsString('--approved-migrations-b64 "$APPROVED_MIGRATIONS_B64"', $workflow);
        $this->assertStringNotContainsString('--reconcile-masters-payments', $workflow);
    }

    public function test_migration_preflight_accepts_only_the_exact_pending_approved_set(): void
    {
        $migrationA = 'database/migrations/2026_09_01_000000_first.php';
        $migrationB = 'database/migrations/2026_09_02_000000_second.php';
        $result = $this->runMigrationPreflight(
            [$migrationA, $migrationB],
            [$migrationA, $migrationB],
            ['2026_09_01_000000_first'],
            [$migrationB]
        );

        $this->assertSame(0, $result['exitCode'], $result['stderr']);
        $this->assertSame($migrationB."\n", $result['pending']);
        $this->assertStringContainsString('1 exact pending migration(s) approved', $result['stdout']);
    }

    public function test_migration_preflight_parses_a_quoted_multiline_release_allowlist(): void
    {
        $migrationA = 'database/migrations/2026_09_01_000000_first.php';
        $migrationB = 'database/migrations/2026_09_02_000000_second.php';
        $config = "RUN_MIGRATIONS=true\nMIGRATION_PATHS=\"\n  {$migrationA}\n  {$migrationB}\n\"\n";

        $result = $this->runMigrationPreflight(
            [$migrationA, $migrationB],
            [$migrationA, $migrationB],
            ['2026_09_01_000000_first'],
            [$migrationB],
            $config
        );

        $this->assertSame(0, $result['exitCode'], $result['stderr']);
        $this->assertSame($migrationB."\n", $result['pending']);
    }

    public function test_migration_preflight_rejects_duplicate_or_malformed_allowlist_definitions(): void
    {
        $migration = 'database/migrations/2026_09_01_000000_first.php';
        $duplicateConfig = "RUN_MIGRATIONS=true\nMIGRATION_PATHS=\"{$migration}\"\nMIGRATION_PATHS=\"{$migration}\"\n";
        $malformedConfig = "RUN_MIGRATIONS=true\nMIGRATION_PATHS={$migration}\n";

        $duplicate = $this->runMigrationPreflight([$migration], [$migration], [], [$migration], $duplicateConfig);
        $this->assertSame(1, $duplicate['exitCode']);
        $this->assertStringContainsString('must not define MIGRATION_PATHS more than once', $duplicate['stderr']);

        $malformed = $this->runMigrationPreflight([$migration], [$migration], [], [$migration], $malformedConfig);
        $this->assertSame(1, $malformed['exitCode']);
        $this->assertStringContainsString('malformed MIGRATION_PATHS assignment', $malformed['stderr']);
    }

    public function test_migration_preflight_rejects_pending_target_migrations_when_migrations_are_disabled(): void
    {
        $migration = 'database/migrations/2026_09_01_000000_first.php';
        $disabledConfig = "RUN_MIGRATIONS=false\nMIGRATION_PATHS=\"\"\n";
        $result = $this->runMigrationPreflight([$migration], [], [], [], $disabledConfig);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('Target migrations are pending while RUN_MIGRATIONS=false', $result['stderr']);
    }

    public function test_migration_preflight_allows_none_when_migrations_are_disabled_and_none_are_pending(): void
    {
        $migration = 'database/migrations/2026_09_01_000000_first.php';
        $disabledConfig = "RUN_MIGRATIONS=false\nMIGRATION_PATHS=\"\"\n";
        $result = $this->runMigrationPreflight(
            [$migration],
            [],
            ['2026_09_01_000000_first'],
            [],
            $disabledConfig
        );

        $this->assertSame(0, $result['exitCode'], $result['stderr']);
        $this->assertSame('', $result['pending']);
        $this->assertStringContainsString('migrations are disabled for this release', $result['stdout']);
    }

    public function test_migration_preflight_rejects_pending_paths_absent_from_release_allowlist(): void
    {
        $migrationA = 'database/migrations/2026_09_01_000000_first.php';
        $migrationB = 'database/migrations/2026_09_02_000000_second.php';
        $result = $this->runMigrationPreflight(
            [$migrationA, $migrationB],
            [$migrationA],
            [],
            [$migrationA, $migrationB]
        );

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('Pending migrations are not release-allowlisted', $result['stderr']);
    }

    public function test_migration_preflight_rejects_nonpending_or_missing_approvals(): void
    {
        $migrationA = 'database/migrations/2026_09_01_000000_first.php';
        $migrationB = 'database/migrations/2026_09_02_000000_second.php';

        $nonPending = $this->runMigrationPreflight(
            [$migrationA, $migrationB],
            [$migrationA, $migrationB],
            ['2026_09_01_000000_first'],
            [$migrationA, $migrationB]
        );
        $this->assertSame(1, $nonPending['exitCode']);
        $this->assertStringContainsString('Approval contains migrations that are not pending', $nonPending['stderr']);

        $missing = $this->runMigrationPreflight(
            [$migrationA, $migrationB],
            [$migrationA, $migrationB],
            ['2026_09_01_000000_first'],
            []
        );
        $this->assertSame(1, $missing['exitCode']);
        $this->assertStringContainsString('Pending migrations lack explicit per-run approval', $missing['stderr']);
    }

    public function test_incident_migration_is_not_approved_by_the_release_allowlist(): void
    {
        $incident = 'database/migrations/2026_09_15_120000_reconcile_wilson_masters_registration_incidents.php';
        $result = $this->runMigrationPreflight([$incident], [$incident], [], []);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('Pending migrations lack explicit per-run approval', $result['stderr']);
    }

    /**
     * @param  list<string>  $targetMigrations
     * @param  list<string>  $allowlistedMigrations
     * @param  list<string>  $appliedMigrations
     * @param  list<string>  $approvedMigrations
     * @return array{exitCode: int, stdout: string, stderr: string, pending: string}
     */
    private function runMigrationPreflight(
        array $targetMigrations,
        array $allowlistedMigrations,
        array $appliedMigrations,
        array $approvedMigrations,
        ?string $configContents = null
    ): array {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ct-deploy-preflight-'.bin2hex(random_bytes(8));
        $this->assertTrue(mkdir($directory));

        $files = [
            'config' => $directory.DIRECTORY_SEPARATOR.'deploy.config',
            'target' => $directory.DIRECTORY_SEPARATOR.'target-migrations',
            'applied' => $directory.DIRECTORY_SEPARATOR.'applied-migrations',
            'approved' => $directory.DIRECTORY_SEPARATOR.'approved-migrations',
            'pending' => $directory.DIRECTORY_SEPARATOR.'pending-migrations',
        ];

        file_put_contents(
            $files['config'],
            $configContents ?? "RUN_MIGRATIONS=true\nMIGRATION_PATHS=\"".implode(' ', $allowlistedMigrations)."\"\n"
        );
        file_put_contents($files['target'], $this->lineFile($targetMigrations));
        file_put_contents($files['applied'], $this->lineFile($appliedMigrations));
        file_put_contents($files['approved'], $this->lineFile($approvedMigrations));

        $command = [
            PHP_BINARY,
            dirname(__DIR__, 2).'/scripts/deployment-migration-preflight.php',
            '--deploy-config='.$files['config'],
            '--target-migrations='.$files['target'],
            '--applied-migrations='.$files['applied'],
            '--approved-migrations='.$files['approved'],
            '--pending-output='.$files['pending'],
        ];
        $pipes = [];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $this->assertIsResource($process);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        $pending = is_file($files['pending']) ? (string) file_get_contents($files['pending']) : '';

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($directory);

        return compact('exitCode', 'stdout', 'stderr', 'pending');
    }

    /** @param list<string> $lines */
    private function lineFile(array $lines): string
    {
        return $lines === [] ? '' : implode("\n", $lines)."\n";
    }
}
