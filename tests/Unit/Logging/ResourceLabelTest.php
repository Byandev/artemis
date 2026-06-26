<?php

use App\Http\Middleware\LogRequestActivity;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;

/**
 * Resolve the human-readable resource label the audit middleware derives from a
 * route name (exercises the private resourceLabel()).
 */
function labelForRoute(string $routeName): string
{
    $middleware = new LogRequestActivity;

    $request = Request::create('/x', 'POST');
    $route = (new RoutingRoute('POST', '/x', []))->name($routeName);
    $request->setRouteResolver(fn () => $route);

    $method = new ReflectionMethod($middleware, 'resourceLabel');
    $method->setAccessible(true);

    return $method->invoke($middleware, $request);
}

test('ordinary words that contain acronym letters are not mangled', function () {
    // Regression: "Reports" contains "rts" and "po", so a case-insensitive
    // substring replace turned it into "RePORTS".
    expect(labelForRoute('workspaces.metaads.reports.store'))->toBe('Reports');
});

test('acronyms are still restored when they are a whole word', function () {
    expect(labelForRoute('workspaces.csr.update'))->toBe('CSR')
        ->and(labelForRoute('workspaces.erp.store'))->toBe('ERP');
});

test('multi-word labels keep ordinary words and uppercase only acronym words', function () {
    // "purchased-orders" headlines to "Purchased Orders" — neither word is a
    // standalone acronym, so it stays intact.
    expect(labelForRoute('workspaces.inventory.purchased-orders.store'))
        ->toBe('Purchased Orders');
});

test('explicit overrides take precedence over acronym handling', function () {
    expect(labelForRoute('workspaces.csr.employees.update'))->toBe('CSR employee')
        ->and(labelForRoute('workspaces.metaads.api-keys.store'))->toBe('API key');
});
