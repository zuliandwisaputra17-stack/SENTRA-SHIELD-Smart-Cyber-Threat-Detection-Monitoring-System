<?php

namespace App\Http\Controllers\Api\Agent;

use App\Enums\ActionStatus;
use App\Enums\ActionType;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\ResponseAction;
use App\Response\ActionService;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET  /api/v1/agent/commands             — perintah yang sudah disetujui.
 * POST /api/v1/agent/commands/{id}/result — lapor hasil eksekusi.
 *
 * Hanya jenis dari daftar tetap (block_ip, unblock_ip, run_test) yang pernah
 * dikirim ke agent.
 */
class CommandController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Agent $agent */
        $agent = $request->user();

        $commands = $agent->actions()
            ->with('approver:id,name')
            ->where('status', ActionStatus::Disetujui)
            ->whereIn('type', array_column(ActionType::cases(), 'value'))
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->map(fn (ResponseAction $action) => [
                'id' => $action->id,
                'type' => $action->type->value,
                'params' => $action->params,
                'approved_by' => $action->approver?->name,
            ]);

        return response()->json(['commands' => $commands]);
    }

    public function result(Request $request, int $id, AuditLogger $audit, ActionService $actions): JsonResponse
    {
        /** @var Agent $agent */
        $agent = $request->user();

        $data = $request->validate([
            'status' => ['required', 'string', 'in:success,failed'],
            'message' => ['nullable', 'string', 'max:1000'],
            'executed_at' => ['nullable', 'date'],
            'details' => ['nullable', 'array'],
        ]);

        $action = $agent->actions()->whereKey($id)->first();
        if ($action === null) {
            return response()->json(['message' => 'Perintah tidak ditemukan untuk agent ini.'], 404);
        }
        if ($action->status !== ActionStatus::Disetujui) {
            return response()->json(['message' => 'Hasil perintah ini sudah tercatat.', 'status' => $action->status->value]);
        }

        $executedAt = isset($data['executed_at']) ? now()->parse($data['executed_at']) : now();
        $success = $data['status'] === 'success';
        $durationS = (int) ($action->params['duration_s'] ?? 0);

        $action->forceFill([
            'status' => $success ? ActionStatus::Dieksekusi : ActionStatus::Gagal,
            'executed_at' => $executedAt,
            'expires_at' => $success && $action->type === ActionType::BlockIp && $durationS > 0
                ? $executedAt->copy()->addSeconds($durationS)
                : $action->expires_at,
            'result' => [
                'status' => $data['status'],
                'message' => $data['message'] ?? null,
                'details' => $data['details'] ?? null,
            ],
        ])->save();

        $audit->record($success ? 'tindakan_dieksekusi' : 'tindakan_gagal', $action, [
            'agent_id' => $agent->id,
            'type' => $action->type->value,
            'message' => $data['message'] ?? null,
        ]);
        $actions->afterResult($action);

        return response()->json(['status' => $action->status->value]);
    }
}
