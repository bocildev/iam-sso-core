<?php

namespace App\Http\Middleware;

use App\Domain\Access\AccessResolver;
use Closure;
use Illuminate\Http\Request;

class EnsureIamAdmin
{
    public function __construct(private AccessResolver $access) {}

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user) {
            $request->session()->put('iam_intended', $request->fullUrl());

            return redirect('/login');
        }
        abort_unless($this->access->can($user, 'admin'), 403);

        return $next($request);
    }
}
