<?php

namespace App\Support;

class BladeRouteReferenceScanner
{
    private const RETIRED_VIEW_PATHS = [
        'email-campaigns/form.blade.php',
        'email-campaigns/index.blade.php',
        'email-campaigns/preferences.blade.php',
        'email-campaigns/unsubscribed.blade.php',
        'partner-promo-codes/index.blade.php',
    ];

    public function shouldScanPath(string $relativePath): bool
    {
        $relativePath = str_replace('\\', '/', ltrim($relativePath, '/'));

        return !in_array($relativePath, self::RETIRED_VIEW_PATHS, true);
    }

    public function missingRoutes(string $source, callable $routeExists): array
    {
        $source = preg_replace('/\{\{--.*?--\}\}/s', '', $source);
        preg_match_all("/\\broute\\(\\s*['\"]([^'\"]+)['\"] /x", $source, $matches);

        $missing = [];
        foreach (array_unique($matches[1]) as $routeName) {
            $guarded = preg_match(
                "/Route::has\\(\\s*['\"]" . preg_quote($routeName, '/') . "['\"]\\s*\\)/",
                $source
            );

            if (!$routeExists($routeName) && !$guarded) {
                $missing[] = $routeName;
            }
        }

        return $missing;
    }
}
