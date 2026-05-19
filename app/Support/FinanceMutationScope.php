<?php

namespace App\Support;

class FinanceMutationScope
{
    /**
     * @var array<int, string>
     */
    protected static array $scopes = [];

    /**
     * @template TReturn
     *
     * @param  string|array<int, string>  $scopes
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function run(string|array $scopes, callable $callback): mixed
    {
        $scopes = is_array($scopes) ? array_values($scopes) : [$scopes];

        foreach ($scopes as $scope) {
            static::$scopes[] = $scope;
        }

        try {
            return $callback();
        } finally {
            foreach ($scopes as $_scope) {
                array_pop(static::$scopes);
            }
        }
    }

    public static function allows(string ...$scopes): bool
    {
        foreach ($scopes as $scope) {
            if (in_array($scope, static::$scopes, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    public static function current(): array
    {
        return array_values(array_unique(static::$scopes));
    }
}
