<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $rows = [
            // ── Email notification toggles ──────────────────────────────────
            [
                'key'   => 'email_on_registration',
                'value' => '1',
                'label' => 'Notify admin on player registration',
                'group' => 'email',
            ],
            [
                'key'   => 'email_on_withdrawal',
                'value' => '1',
                'label' => 'Notify admin on player withdrawal',
                'group' => 'email',
            ],
            [
                'key'   => 'email_on_team_withdrawal',
                'value' => '1',
                'label' => 'Notify admin on team player withdrawal',
                'group' => 'email',
            ],
            [
                'key'   => 'email_on_wallet_topup',
                'value' => '0',
                'label' => 'Notify admin on wallet top-up',
                'group' => 'email',
            ],
            [
                'key'   => 'email_on_bank_refund_request',
                'value' => '1',
                'label' => 'Notify admin on bank refund request',
                'group' => 'email',
            ],
            [
                'key'   => 'admin_notification_email',
                'value' => 'support@capetennis.co.za',
                'label' => 'Admin notification email address',
                'group' => 'email',
            ],

            // ── Registration & withdrawal behaviour ─────────────────────────
            [
                'key'   => 'registration_open',
                'value' => '1',
                'label' => 'Registrations open (global)',
                'group' => 'registration',
            ],
            [
                'key'   => 'withdrawal_allowed',
                'value' => '1',
                'label' => 'Withdrawals allowed (global)',
                'group' => 'registration',
            ],
            [
                'key'   => 'withdrawal_deadline_days',
                'value' => '7',
                'label' => 'Withdrawal deadline (days before event start)',
                'group' => 'registration',
            ],
            [
                'key'   => 'profile_required_for_registration',
                'value' => '1',
                'label' => 'Complete profile required to register',
                'group' => 'registration',
            ],
        ];

        foreach ($rows as &$row) {
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
        }

        DB::table('site_settings')->insertOrIgnore($rows);
    }

    public function down(): void
    {
        DB::table('site_settings')->whereIn('key', [
            'email_on_registration',
            'email_on_withdrawal',
            'email_on_team_withdrawal',
            'email_on_wallet_topup',
            'email_on_bank_refund_request',
            'admin_notification_email',
            'registration_open',
            'withdrawal_allowed',
            'withdrawal_deadline_days',
            'profile_required_for_registration',
        ])->delete();
    }
};
