<?php

if (! function_exists('vasset')) {
    /**
     * Versioned asset URL — appends ?v=x.x.x for cache busting.
     */
    function vasset(string $path, bool $secure = null): string
    {
        $url = asset($path, $secure);
        $version = config('app.asset_version', '1.0.0');

        return $url . '?v=' . $version;
    }
}
