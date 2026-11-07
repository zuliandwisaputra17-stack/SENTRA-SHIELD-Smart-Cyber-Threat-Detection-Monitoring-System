<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessEvents;
use App\Models\Agent;
use App\Services\EventIngestor;
use App\Support\LiveUpdate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/v1/agent/events — kirim batch event (maks 500 per permintaan).
 * Skema mengikuti PRD bagian 9. Sumber "site" hanya dibuat Core sendiri.
 */
class EventController extends Controller
{
    public const AGENT_SOURCES = ['web', 'ssh', 'firewall', 'fim', 'modbus'];

    public function __invoke(Request $request, EventIngestor $ingestor): JsonResponse
    {
        /** @var Agent $agent */
        $agent = $request->user();

        $data = $request->validate([
            'events' => ['required', 'array', 'min:1', 'max:500'],
            'events.*.source' => ['required', 'string', 'in:'.implode(',', self::AGENT_SOURCES)],
            'events.*.occurred_at' => ['required', 'date'],
            'events.*.src_ip' => ['nullable', 'ip'],
            'events.*.dst_port' => ['nullable', 'integer', 'between:0,65535'],
            'events.*.payload' => ['present', 'array'],
            'events.*.raw' => ['nullable', 'string', 'max:8192'],
            'events.*.is_test' => ['sometimes', 'boolean'],
        ]);

        $ids = $ingestor->ingest($agent, $data['events']);
        ProcessEvents::dispatch($ids);
        LiveUpdate::notify('event', [$agent->unit_id]);

        return response()->json(['accepted' => count($ids)]);
    }
}
