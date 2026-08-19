<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class IssueJtaResultsToken extends Command
{
    protected $signature = 'jta:issue-results-token
        {--user= : Email address of the super-user who owns the service token}
        {--name=jta-results : Token name to issue or rotate}
        {--expires-days= : Number of days before expiry}';

    protected $description = 'Issue or rotate the read-only JTA results integration token';

    public function handle(): int
    {
        $email = trim((string) $this->option('user'));
        $name = trim((string) $this->option('name'));
        $days = (int) ($this->option('expires-days') ?: config('integrations.jta.token_expiration_days', 90));

        if ($email === '' || $name === '' || $days < 1) {
            $this->error('A super-user email, non-empty token name, and positive expiry period are required.');
            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();
        if (! $user || ! $user->hasRole('super-user')) {
            $this->error('The selected token owner must exist and have the super-user role.');
            return self::FAILURE;
        }

        $expiresAt = now()->addDays($days);
        $token = DB::transaction(function () use ($user, $name, $expiresAt) {
            $user->tokens()->where('name', $name)->delete();

            return $user->createToken(
                $name,
                [(string) config('integrations.jta.ability', 'jta-results:read')],
                $expiresAt,
            );
        });

        $this->warn('Store this token now. It will not be shown again:');
        $this->line($token->plainTextToken);
        $this->info('Expires at: '.$expiresAt->toIso8601String());

        return self::SUCCESS;
    }
}
