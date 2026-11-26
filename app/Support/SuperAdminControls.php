<?php

namespace App\Support;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

/** Presentation metadata only. Route/controller authorization remains authoritative. */
final class SuperAdminControls
{
    private const CONTROLLER_ACTIONS = [
        'AdminRegistrationRefundController' => ['chooseRefund', 'storeRefund', 'cancelWithdraw'],
        'EventEntryController' => ['entryDetails'],
        'EventMailLogController' => ['clear'],
        'EventPayoutRequestController' => ['index'],
        'RegionalClothingSettlementController' => ['users', 'store'],
        'ClothingRefundController' => ['checkPayfast', 'deadline', 'reconcile'],
        'DrawEngineModeController' => ['show', 'update', 'rollback', 'updateEvent'],
        'UserController' => ['index', 'data', 'show', 'destroy', 'search'],
        'RoleController' => ['store', 'destroy'],
        'ClothingOrderController' => ['index'],
        'TeamSelectionInvitationController' => ['managerPreview', 'transferInvitationPayment', 'undoPrivatePayment', 'recordCashRefund', 'transferImportedPayment'],
    ];

    public function exclusive(?Route $route): bool
    {
        if (! $route) {
            return false;
        }

        // All current role middleware lives on routes; controller-only guards are
        // listed below. Avoid constructing every controller just to decorate UI.
        foreach ($route->middleware() as $middleware) {
            if (is_string($middleware) && preg_match('/^(?:role|'.preg_quote(\Spatie\Permission\Middleware\RoleMiddleware::class, '/').'):super-user(?:,.*)?$/', $middleware)) {
                return true;
            }
            if ($middleware === 'can:super-user') {
                return true;
            }
        }

        [$controller, $method] = array_pad(explode('@', $route->getActionName(), 2), 2, '');

        return str_starts_with($controller, 'App\\Http\\Controllers\\Backend\\')
            && in_array($method, self::CONTROLLER_ACTIONS[class_basename($controller)] ?? [], true);
    }

    public function manifest(): array
    {
        $routes = [];
        foreach (RouteFacade::getRoutes() as $route) {
            // Preserve routing order and methods, including shared routes that precede
            // a parameterized exclusive route. Never infer permission from URL prefixes.
            if ($route->getDomain() !== null) {
                continue;
            }
            $regex = $route->toSymfonyRoute()->compile()->getRegex();
            $delimiter = match ($regex[0]) { '{' => '}', '[' => ']', '(' => ')', '<' => '>', default => $regex[0] };
            $pattern = substr($regex, 1, strrpos($regex, $delimiter) - 1);
            // Symfony's default path patterns use PCRE named/possessive groups.
            $pattern = preg_replace('/\(\?P<[^>]+>/', '(?:', $pattern);
            $pattern = str_replace(['++', '*+', '?+', '(?>'], ['+', '*', '?', '(?:'], $pattern);
            $routes[] = [$pattern, array_values(array_diff($route->methods(), ['HEAD'])), $this->exclusive($route)];
        }

        return $routes;
    }
}
