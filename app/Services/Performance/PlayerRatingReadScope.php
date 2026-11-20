<?php

namespace App\Services\Performance;

use Illuminate\Http\Request;

/** Reuse exact read work only while one authorised My Tennis GET is rendering. */
class PlayerRatingReadScope
{
    private const ATTRIBUTE = '_ct_player_rating_read_scope';

    public function run(Request $request, callable $read): mixed
    {
        if (!$request->isMethod('GET') || !$request->route() || $request->attributes->has(self::ATTRIBUTE)) return $read();
        $request->attributes->set(self::ATTRIBUTE, new \ArrayObject());
        try {
            return $read();
        } finally {
            $request->attributes->remove(self::ATTRIBUTE);
        }
    }

    public function remember(string $key, callable $read): mixed
    {
        $request = app()->bound('request') ? app('request') : null;
        $values = $request instanceof Request && $request->isMethod('GET')
            ? $request->attributes->get(self::ATTRIBUTE) : null;
        if (!$values instanceof \ArrayObject) return $read();
        if (!$values->offsetExists($key)) $values[$key] = $read();
        return $values[$key];
    }
}
