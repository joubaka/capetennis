<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const EXISTING_KEYS = [
        'player_email_on_registration',
        'player_email_on_withdrawal',
        'player_email_on_move',
    ];

    public function up(): void
    {
        foreach ($this->newKeys() as $key) {
            SiteSetting::query()->firstOrCreate(
                ['key' => $key],
                ['value' => '1', 'group' => SiteSetting::GROUP_EMAIL, 'label' => str($key)->replace('_', ' ')->headline()]
            );
        }
    }

    public function down(): void
    {
        SiteSetting::query()->whereIn('key', $this->newKeys())->delete();
    }

    private function newKeys(): array
    {
        return array_values(array_diff(SiteSetting::AUTOMATED_EMAIL_TOGGLES, self::EXISTING_KEYS));
    }
};
