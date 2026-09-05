<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Player;

final class PublicPlayerProfileController extends Controller
{
    public function __invoke(Player $player)
    {
        return view('frontend.player.public-profile', [
            'displayName' => trim($player->full_name),
        ]);
    }
}
