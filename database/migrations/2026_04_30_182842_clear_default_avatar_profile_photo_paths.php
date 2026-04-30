<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Clear any profile_photo_path values that point to old default avatar
     * images (e.g. the numbered man-avatar PNGs stored under /avatars/).
     * After this, the gender-neutral default.svg will be shown instead.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'profile_photo_path')) {
            return;
        }

        DB::table('users')
            ->whereNotNull('profile_photo_path')
            ->where(function ($query) {
                $query->where('profile_photo_path', 'LIKE', '%/avatars/%')
                      ->orWhere('profile_photo_path', 'LIKE', 'avatars/%');
            })
            ->update(['profile_photo_path' => null]);
    }

    public function down(): void
    {
        // Intentionally left empty: restoring default avatar paths is not
        // meaningful and could not be done safely.
    }
};
