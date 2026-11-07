<?php

namespace App\Http\Middleware;

use App\Enums\AgentStatus;
use App\Models\Agent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hanya token milik agent yang belum dicabut yang boleh memakai API agent.
 */
class EnsureActiveAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        $agent = $request->user();

        if (! $agent instanceof Agent || $agent->status === AgentStatus::Dicabut) {
            return response()->json(['message' => 'Token agent salah atau dicabut.'], 401);
        }

        return $next($request);
    }
}
