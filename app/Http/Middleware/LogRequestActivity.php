<?php

namespace App\Http\Middleware;

use App\Enums\Logging\LogCategory;
use App\Enums\Logging\LogStatus;
use App\Facades\Activity;
use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Catch-all audit middleware: records every state-changing web request
 * (POST/PUT/PATCH/DELETE) as a user activity log, regardless of how the
 * controller persists data. This guarantees coverage for actions that the
 * model observer can't see — mass updates, pivot writes, non-observed models
 * (e.g. toggling a CSR employee's status), etc.
 *
 * Read requests (GET/HEAD) are intentionally not logged to keep volume sane.
 */
class LogRequestActivity
{
    /**
     * HTTP verbs that change state and are therefore worth auditing.
     */
    private const LOGGED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * Route-name / path fragments handled elsewhere or not worth logging here
     * (auth events are captured by LogAuthenticationActivity; dev tooling is
     * noise).
     */
    private const SKIP = [
        'login', 'logout', 'register', 'password', 'two-factor', 'verification',
        'broadcasting', 'telescope', 'horizon', 'livewire', '_debugbar', '_ignition',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldLog($request)) {
            $this->record($request, $response);
        }

        return $response;
    }

    private function shouldLog(Request $request): bool
    {
        if (! in_array($request->method(), self::LOGGED_METHODS, true)) {
            return false;
        }

        $haystack = strtolower($request->route()?->getName().' '.$request->path());

        foreach (self::SKIP as $fragment) {
            if (str_contains($haystack, $fragment)) {
                return false;
            }
        }

        return true;
    }

    private function record(Request $request, Response $response): void
    {
        $status = $response->getStatusCode();
        $route = $request->route();
        $action = $route?->getName() ?? $route?->getActionName() ?? $request->method();
        $attributes = $this->notableInput($request);

        $builder = Activity::build()
            ->asUser()
            ->category($this->category($request))
            ->action($action)
            ->status($this->statusFor($status))
            ->message($this->describe($request, $attributes))
            ->metadata([
                'method' => $request->method(),
                'path' => '/'.ltrim($request->path(), '/'),
                'route' => $route?->getName(),
                'controller' => $route?->getActionName(),
                'status_code' => $status,
                'attributes' => $attributes ?: null,
            ]);

        $workspace = $request->route('workspace');
        if ($workspace instanceof Workspace) {
            $builder->workspace($workspace->getKey());
        }

        $builder->save();
    }

    /**
     * Build a human-readable action sentence, e.g. "Updated CSR employee
     * (status: INACTIVE)" or "Deleted Page".
     *
     * @param  array<string, scalar>  $attributes
     */
    private function describe(Request $request, array $attributes): string
    {
        $verb = match ($request->method()) {
            'POST' => 'Created',
            'PUT', 'PATCH' => 'Updated',
            'DELETE' => 'Deleted',
            default => 'Changed',
        };

        $sentence = trim("{$verb} {$this->resourceLabel($request)}");

        if ($attributes !== []) {
            $pairs = [];
            foreach ($attributes as $key => $value) {
                $pairs[] = str_replace('_', ' ', $key).': '.$value;
            }
            $sentence .= ' ('.implode(', ', $pairs).')';
        }

        return $sentence;
    }

    /** Friendly name for the thing being acted on, derived from the route name. */
    private function resourceLabel(Request $request): string
    {
        $name = $request->route()?->getName();

        if (! $name) {
            return 'record';
        }

        $segments = explode('.', $name);
        array_pop($segments); // drop the trailing action (update/store/destroy/…)

        $key = end($segments) ?: $name;

        // Nicer labels for resources whose route name reads poorly.
        $overrides = [
            'employees' => 'CSR employee',
            'rmo-management' => 'RMO order',
            'optimization-rules' => 'optimization rule',
            'api-keys' => 'API key',
        ];

        if (isset($overrides[$key])) {
            return $overrides[$key];
        }

        $label = Str::headline(str_replace('-', ' ', $key));

        // Restore common acronyms that headline() would have title-cased.
        return str_ireplace(
            ['Csr', 'Rts', 'Rmo', 'Erp', 'Api', 'Po'],
            ['CSR', 'RTS', 'RMO', 'ERP', 'API', 'PO'],
            $label,
        );
    }

    /**
     * Pull a few meaningful, non-sensitive scalar inputs to enrich the message
     * (so an audit reader sees *what* changed, e.g. status: INACTIVE).
     *
     * @return array<string, scalar>
     */
    private function notableInput(Request $request): array
    {
        $keys = ['status', 'state', 'name', 'title', 'role', 'type', 'is_active', 'active', 'enabled'];
        $result = [];

        foreach ($keys as $key) {
            $value = $request->input($key);

            if (is_scalar($value) && $value !== '') {
                $result[$key] = Str::limit((string) $value, 50, '');
            }
        }

        return $result;
    }

    private function statusFor(int $code): LogStatus
    {
        return match (true) {
            $code >= 500 => LogStatus::Failure,
            $code >= 400 => LogStatus::Warning,
            default => LogStatus::Success,
        };
    }

    /** Best-effort category from the route name / path. */
    private function category(Request $request): LogCategory
    {
        $haystack = strtolower($request->route()?->getName().' '.$request->path());

        return match (true) {
            Str::contains($haystack, ['role', 'permission', 'api-key', 'member', 'invitation']) => LogCategory::Security,
            Str::contains($haystack, ['integration', 'meta', 'pancake', 'botcake', 'erp', 'sync']) => LogCategory::Integration,
            default => LogCategory::Data,
        };
    }
}
