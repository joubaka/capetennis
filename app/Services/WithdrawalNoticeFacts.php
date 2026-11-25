<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonInterface;

final class WithdrawalNoticeFacts
{
    public static function actor(?int $id): string
    {
        $actor = $id ? User::find($id) : null;
        return $actor ? $actor->name.' (account #'.$actor->id.')' : 'Not recorded';
    }

    public static function time(?CarbonInterface $at): string
    {
        return $at ? $at->copy()->timezone(config('app.timezone'))->format('d M Y H:i:s').' '.config('app.timezone') : 'Not recorded';
    }

    public static function html(string $player, ?CarbonInterface $at, ?int $actor): string
    {
        return '<p><strong>Player withdrawn:</strong> '.e($player).'<br><strong>Withdrawn on:</strong> '.e(self::time($at)).'<br><strong>Performed by:</strong> '.e(self::actor($actor)).'</p>';
    }
}
