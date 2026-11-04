<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Admin\StaffRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rechten per onderdeel van het control center: area:finance of
 * area:service,write. De `can:`-middleware van het framework ziet een losse
 * naam als routeparameter, vandaar een eigen middleware.
 */
final class EnsureStaffArea
{
    public function handle(Request $request, Closure $next, string $area, ?string $mode = null): Response
    {
        $role = $request->user() ? StaffRole::of($request->user()) : null;

        abort_unless($role?->can($area, $mode === 'write') ?? false, 403, __('admin.forbidden'));

        return $next($request);
    }
}
