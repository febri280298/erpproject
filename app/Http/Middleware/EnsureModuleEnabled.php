<?php

namespace App\Http\Middleware;

use App\Services\ModuleRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks routes belonging to a module the installation has switched off.
 *
 * Returns 404 rather than 403: a disabled module should look absent, not
 * forbidden, so bookmarked URLs do not hint at features that are not in use.
 */
class EnsureModuleEnabled
{
    public function __construct(private readonly ModuleRegistry $modules) {}

    public function handle(Request $request, Closure $next, string $module): Response
    {
        abort_if($this->modules->disabled($module), 404);

        return $next($request);
    }
}
