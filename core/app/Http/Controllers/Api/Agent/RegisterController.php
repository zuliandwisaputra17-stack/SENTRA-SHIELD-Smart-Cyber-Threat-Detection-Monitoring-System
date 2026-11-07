<?php

namespace App\Http\Controllers\Api\Agent;

use App\Enums\AgentStatus;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * POST /api/v1/agent/register — tukar kode pendaftaran sekali pakai dengan
 * token API agent. Kode dihapus setelah dipakai.
 */
class RegisterController extends Controller
{
    public function __invoke(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'enrollment_code' => ['required', 'string', 'max:128'],
            'hostname' => ['required', 'string', 'max:255'],
            'ip' => ['nullable', 'ip'],
            'version' => ['required', 'string', 'max:32'],
        ]);

        $result = DB::transaction(function () use ($data, $request) {
            $agent = Agent::query()
                ->where('enrollment_code_hash', hash('sha256', $data['enrollment_code']))
                ->where('status', AgentStatus::Menunggu)
                ->where('enrollment_expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if ($agent === null) {
                return null;
            }

            $agent->tokens()->delete();
            $token = $agent->createToken('agent')->plainTextToken;

            $agent->forceFill([
                'hostname' => $data['hostname'],
                'ip' => $data['ip'] ?? $request->ip(),
                'version' => $data['version'],
                'status' => AgentStatus::Online,
                'last_heartbeat_at' => now(),
                'enrollment_code_hash' => null,
                'enrollment_expires_at' => null,
            ])->save();

            return [$agent, $token];
        });

        if ($result === null) {
            return response()->json(['message' => 'Kode pendaftaran tidak valid, sudah dipakai, atau kedaluwarsa.'], 401);
        }

        [$agent, $token] = $result;
        $audit->record('agent_terdaftar', $agent, ['hostname' => $agent->hostname, 'ip' => $agent->ip]);

        return response()->json([
            'agent_id' => $agent->id,
            'token' => $token,
            'unit' => ['id' => $agent->unit->id, 'code' => $agent->unit->code, 'name' => $agent->unit->name],
            'heartbeat_interval_s' => 10,
            'batch_interval_s' => 1,
            'command_poll_s' => 3,
            'max_batch' => 500,
        ]);
    }
}
