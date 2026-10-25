<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Models\TradingJournal;
use App\Services\TradingJournalSyncLedger;
use App\Services\TradingJournalSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TradingJournalSyncController extends MobileController
{
    public function __construct(private readonly TradingJournalSyncService $sync) {}

    public function changes(Request $request): JsonResponse
    {
        $input = $request->validate([
            'cursor' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'between:1,200'],
        ]);
        return $this->success($this->sync->page((int) $request->user()->id, (int) ($input['cursor'] ?? 0), (int) ($input['limit'] ?? 100)));
    }

    public function batch(Request $request): JsonResponse
    {
        $input = $request->validate([
            'operations' => ['required', 'array', 'max:50'],
            'operations.*' => ['required', 'array'],
        ]);
        $results = [];
        foreach ($input['operations'] as $index => $operation) {
            try {
                $results[] = ['index' => $index, ...$this->sync->apply((int) $request->user()->id, $operation)];
            } catch (ValidationException $error) {
                $results[] = ['index' => $index, 'status' => 'failed', 'errors' => $error->errors()];
            } catch (\Throwable $error) {
                report($error);
                $results[] = ['index' => $index, 'status' => 'failed', 'message' => 'The journal change could not be saved.'];
            }
        }
        return $this->success(['results' => $results]);
    }

    public function index(Request $request): JsonResponse
    {
        app(TradingJournalSyncLedger::class)->ensureLegacyRows((int) $request->user()->id);
        $input = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,200'],
            'symbol' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:draft,open,closed'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
        $query = TradingJournal::where('user_id', $request->user()->id)->where(function ($q): void {
            $q->where('type', 'trade')->orWhereNull('type');
        });
        if (isset($input['symbol'])) $query->where('pair', $input['symbol']);
        if (isset($input['status'])) $query->where('status', $input['status']);
        if (isset($input['from'])) $query->whereDate('open_date', '>=', $input['from']);
        if (isset($input['to'])) $query->whereDate('open_date', '<=', $input['to']);
        $page = $query->orderByDesc('open_date')->paginate((int) ($input['per_page'] ?? 50));
        $items = collect($page->items())->map(fn ($journal) => $this->sync->itemByJournal((int) $request->user()->id, (int) $journal->id));
        return $this->success(['journals' => $items, 'pagination' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]]);
    }

    public function show(Request $request, int $syncId): JsonResponse
    {
        $item = $this->sync->item((int) $request->user()->id, $syncId);
        abort_unless($item && ! $item['deleted_at'], 404);
        return $this->success($item);
    }

    public function store(Request $request): JsonResponse
    {
        $result = $this->sync->apply((int) $request->user()->id, [
            'type' => 'create', 'mobile_id' => $request->input('mobile_id'),
            'sync_id' => $request->input('sync_id'),
            'base_revision' => $request->input('base_revision'),
            'confirm_match' => $request->boolean('confirm_match'),
            'fields' => $request->input('fields', []),
        ]);
        return $this->operationResponse($result, ($result['status'] ?? '') === 'created' ? 201 : 200);
    }

    public function patch(Request $request, int $syncId): JsonResponse
    {
        $result = $this->sync->apply((int) $request->user()->id, [
            'type' => 'update', 'sync_id' => $syncId,
            'mobile_id' => $request->input('mobile_id'),
            'base_revision' => $request->input('base_revision'),
            'base' => $request->input('base', []),
            'fields' => $request->input('fields', []),
        ]);
        return $this->operationResponse($result);
    }

    public function destroy(Request $request, int $syncId): JsonResponse
    {
        $result = $this->sync->apply((int) $request->user()->id, [
            'type' => 'delete', 'sync_id' => $syncId,
            'mobile_id' => $request->input('mobile_id'),
            'base_revision' => $request->input('base_revision'),
        ]);
        return $this->operationResponse($result);
    }

    public function statistics(Request $request): JsonResponse
    {
        $input = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        $query = TradingJournal::where('user_id', $request->user()->id)->where(function ($q): void {
            $q->where('type', 'trade')->orWhereNull('type');
        });
        if (isset($input['from'])) $query->whereDate('open_date', '>=', $input['from']);
        if (isset($input['to'])) $query->whereDate('open_date', '<=', $input['to']);
        $trades = $query->get();
        $closed = $trades->filter(fn ($trade) => $trade->isClosed() && $trade->profit_loss !== null);
        $wins = $closed->filter(fn ($trade) => (float) $trade->profit_loss > 0);
        $losses = $closed->filter(fn ($trade) => (float) $trade->profit_loss < 0);
        $net = '0.00';
        $profit = '0.00';
        $loss = '0.00';
        foreach ($closed as $trade) {
            $amount = (string) ($trade->profit_loss ?? '0');
            $net = bcadd($net, $amount, 2);
            if (bccomp($amount, '0', 2) > 0) $profit = bcadd($profit, $amount, 2);
            if (bccomp($amount, '0', 2) < 0) $loss = bcadd($loss, $amount, 2);
        }
        return $this->success([
            'total_trades' => $trades->count(),
            'closed_trades' => $closed->count(),
            'winning_trades' => $wins->count(),
            'losing_trades' => $losses->count(),
            'breakeven_trades' => $closed->count() - $wins->count() - $losses->count(),
            'win_rate' => $wins->count() + $losses->count() > 0 ? round($wins->count() * 100 / ($wins->count() + $losses->count()), 2) : null,
            'total_net_profit_loss' => $net,
            'total_profit' => $profit,
            'total_loss' => $loss,
            'average_trade_profit_loss' => $closed->count() ? bcdiv($net, (string) $closed->count(), 2) : null,
        ]);
    }

    private function operationResponse(array $result, int $successStatus = 200): JsonResponse
    {
        $status = $result['status'] ?? 'failed';
        $httpStatus = in_array($status, ['conflict', 'reconciliation_required'], true) ? 409
            : ($status === 'not_found' ? 404 : $successStatus);
        return response()->json([
            'success' => $httpStatus < 400,
            'message' => $result['message'] ?? 'Journal operation processed.',
            'data' => $result,
        ], $httpStatus);
    }
}
