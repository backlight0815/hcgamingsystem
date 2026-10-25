<?php

namespace App\Http\Controllers\Pms;

use App\Http\Controllers\Controller;
use App\Models\PmWorkItem;
use App\Services\PmsTicketHoldService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TicketHoldController extends Controller
{
    public function hold(Request $request, PmWorkItem $ticket, PmsTicketHoldService $service)
    {
        $data = $request->validate([
            'lock_version' => 'required|integer|min:0', 'reason' => 'required|string|max:5000',
            'blocker_id' => 'nullable|integer',
        ]);
        $result = $service->process($request, $ticket, ['action' => 'hold'] + $data);
        return $request->expectsJson() ? response()->json($result, $result['pending'] ? 202 : 200)
            : back()->with('success', $result['pending'] ? 'On Hold submitted for Project Manager review.' : 'Ticket placed On Hold.');
    }

    public function resume(Request $request, PmWorkItem $ticket, PmsTicketHoldService $service)
    {
        $data = $request->validate([
            'lock_version' => 'required|integer|min:0',
            'status' => ['required', 'string', Rule::in($service->destinations($ticket))],
            'reason' => 'nullable|string|max:5000', 'override_unresolved' => 'nullable|boolean',
        ]);
        $result = $service->process($request, $ticket, ['action' => 'resume'] + $data);
        return $request->expectsJson() ? response()->json($result, $result['pending'] ? 202 : 200)
            : back()->with('success', $result['pending'] ? 'Resume submitted for Project Manager review.' : 'Ticket resumed.');
    }
}
