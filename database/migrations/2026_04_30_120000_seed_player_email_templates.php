<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ROWS = [
        /* ─── Registration ─────────────────────────────────────────────── */
        [
            'key'   => 'player_email_on_registration',
            'value' => '1',
            'label' => 'Send player registration confirmation email',
            'group' => 'email',
        ],
        [
            'key'   => 'player_email_subject_registration',
            'value' => 'Registration Confirmation – {event_name}',
            'label' => 'Player registration confirmation email subject',
            'group' => 'email',
        ],
        [
            'key'   => 'player_email_body_registration',
            'value' => "Hi {user_name},\n\nYour registration for **{event_name}** has been confirmed.\n\nIf you have any questions, please contact us at support@capetennis.co.za.",
            'label' => 'Player registration confirmation email body',
            'group' => 'email',
        ],

        /* ─── Withdrawal ────────────────────────────────────────────────── */
        [
            'key'   => 'player_email_on_withdrawal',
            'value' => '1',
            'label' => 'Send player withdrawal confirmation email',
            'group' => 'email',
        ],
        [
            'key'   => 'player_email_subject_withdrawal',
            'value' => 'Withdrawal Confirmation – {event_name}',
            'label' => 'Player withdrawal confirmation email subject',
            'group' => 'email',
        ],
        [
            'key'   => 'player_email_body_withdrawal',
            'value' => "Hi {player_name},\n\nYour withdrawal from **{event_name}** ({category_name}) has been confirmed.\n\n**Withdrawn on:** {withdrawn_at}  \n**Initiated by:** {initiated_by}\n\nIf you have any questions, please contact us at support@capetennis.co.za.",
            'label' => 'Player withdrawal confirmation email body',
            'group' => 'email',
        ],

        /* ─── Category Move ─────────────────────────────────────────────── */
        [
            'key'   => 'player_email_on_move',
            'value' => '1',
            'label' => 'Send player category-move confirmation email',
            'group' => 'email',
        ],
        [
            'key'   => 'player_email_subject_move',
            'value' => 'Category Changed – {event_name}',
            'label' => 'Player category-move confirmation email subject',
            'group' => 'email',
        ],
        [
            'key'   => 'player_email_body_move',
            'value' => "Hi {player_name},\n\nYour category for **{event_name}** has been changed.\n\n- **Previous Category:** {old_category}\n- **New Category:** {new_category}\n\nThis change was made by {changed_by}.\n\nIf you did not request this change, please contact support at support@capetennis.co.za.",
            'label' => 'Player category-move confirmation email body',
            'group' => 'email',
        ],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::ROWS as $row) {
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
            DB::table('site_settings')->insertOrIgnore($row);
        }
    }

    public function down(): void
    {
        DB::table('site_settings')->whereIn('key', array_column(self::ROWS, 'key'))->delete();
    }
};
