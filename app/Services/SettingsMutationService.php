<?php

namespace App\Services;

use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Performance\PlayerPlusService;
use App\Services\Performance\PlayerRatingAccess;
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SettingsMutationService
{
    public function __construct(private readonly AuditWriter $audit) {}

    /** @param array<string, array{value: mixed, group: string}> $changes */
    public function apply(User $actor, array $changes): void
    {
        abort_unless($actor->hasRole('super-user'), 403);
        $allowed = $this->allowedKeys();
        foreach ($changes as $key => $change) {
            if (! isset($allowed[$key]) || ($change['group'] ?? null) !== $allowed[$key]
                || ! is_scalar($change['value'] ?? null)) {
                throw ValidationException::withMessages(['settings' => 'Unsupported setting or setting group.']);
            }
        }
        ksort($changes);

        DB::transaction(function () use ($actor, $changes): void {
            $rows = DB::table('site_settings')->whereIn('key', array_keys($changes))
                ->orderBy('key')->lockForUpdate()->get()->keyBy('key');
            $before = $after = [];
            foreach ($changes as $key => $change) {
                $existing = $rows->get($key);
                $value = (string) $change['value'];
                if ($existing && (string) $existing->value === $value && $existing->group === $change['group']) {
                    continue;
                }
                $before[$key] = ['exists' => $existing !== null, 'value' => $this->auditValue($key, $existing?->value)];
                $after[$key] = ['exists' => true, 'value' => $this->auditValue($key, $value)];
                $attributes = ['value' => $value, 'group' => $change['group'], 'updated_at' => now()];
                // Explicit, safe business evidence below replaces generic raw model payloads for this operation.
                if ($existing) {
                    DB::table('site_settings')->where('id', $existing->id)->update($attributes);
                } else {
                    DB::table('site_settings')->insert($attributes + ['key' => $key, 'created_at' => now()]);
                }
            }
            if ($after === []) {
                return;
            }
            $id = $this->audit->record([
                'category' => 'business', 'action' => 'settings.updated', 'actor' => $actor,
                'subject_type' => SiteSetting::class, 'subject_id' => 'global',
                'subject_label' => 'Platform settings', 'before' => $before, 'after' => $after,
                'metadata' => ['keys' => array_keys($after), 'changed_count' => count($after)],
            ], true);
            if ($id === null) {
                throw new RuntimeException('Settings cannot be changed without an audit record.');
            }
            DB::afterCommit(function () use ($after): void {
                foreach (array_keys($after) as $key) {
                    Cache::forget('site_setting.'.$key);
                }
            });
        }, 3);
    }

    private function auditValue(string $key, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        if (str_contains($key, 'email') || str_contains($key, 'content') || $key === 'brand_logo_url') {
            // Boolean notification preferences remain useful as actual before/after values.
            if (in_array($key, $this->notificationKeys(), true)) {
                return (string) $value;
            }
            return ['sha256' => hash('sha256', (string) $value), 'bytes' => strlen((string) $value)];
        }
        return (string) $value;
    }

    private function notificationKeys(): array
    {
        return array_unique(array_merge(SiteSetting::AUTOMATED_EMAIL_TOGGLES, [
            'email_on_registration', 'email_on_withdrawal', 'email_on_team_withdrawal',
            'email_on_wallet_topup', 'email_on_bank_refund_request', 'email_on_violation',
        ]));
    }

    private function allowedKeys(): array
    {
        $keys = [];
        foreach (['payfast_fee_percentage', 'payfast_fee_flat', 'payfast_vat_rate'] as $key) {
            $keys[$key] = SiteSetting::GROUP_PAYFAST;
        }
        foreach (array_keys(SiteSetting::PAYMENT_METHOD_LABELS) as $method) {
            $keys['payfast_fee_pct_'.$method] = SiteSetting::GROUP_PAYFAST;
        }
        foreach (['require_code_of_conduct', 'require_terms', 'require_profile_update', 'brand_logo_url',
            'code_of_conduct_content', 'terms_conditions_content', SiteSetting::DISCIPLINARY_SYSTEM_ENABLED,
            PlayerRatingAccess::SETTING, PlayerPlusService::PRICE_SETTING,
            PlayerPlusService::MONTHLY_PRICE_SETTING, PlayerPlusService::MONTHLY_ENABLED_SETTING] as $key) {
            $keys[$key] = SiteSetting::GROUP_GENERAL;
        }
        foreach (array_merge($this->notificationKeys(), ['admin_notification_email']) as $key) {
            $keys[$key] = SiteSetting::GROUP_EMAIL;
        }
        foreach (['registration', 'withdrawal', 'move'] as $type) {
            $keys['player_email_subject_'.$type] = $keys['player_email_body_'.$type] = SiteSetting::GROUP_EMAIL;
        }
        foreach (['registration_open', 'withdrawal_allowed', 'withdrawal_deadline_days', 'profile_required_for_registration'] as $key) {
            $keys[$key] = SiteSetting::GROUP_REGISTRATION;
        }
        return $keys;
    }
}
