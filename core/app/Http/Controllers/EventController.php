<?php

namespace App\Http\Controllers;

use App\Models\SecurityEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Detail satu event telemetri (bukti). Hanya bisa dilihat, tidak bisa diubah.
 */
class EventController extends Controller
{
    public function show(Request $request, SecurityEvent $event): View
    {
        abort_unless($request->user()->can('lihat-dashboard') && $request->user()->canSeeUnit($event->unit_id), 403);

        $event->load(['agent', 'unit', 'detections.rule', 'detections.incident']);

        return view('events.show', ['event' => $event]);
    }
}
