<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Models\TradingAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TradingAccountController extends MobileController
{
    public function index(Request $request): JsonResponse
    {
        $this->ensureTradingMember($request);

        $accounts = TradingAccount::with('propFirmModel:id,provider,name')
            ->withCount('journals')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('is_primary')
            ->latest('id')
            ->get()
            ->map(function (TradingAccount $account): TradingAccount {
                $account->setAttribute('net_profit_loss', round((float) $account->journals()->sum('profit_loss'), 2));
                $account->setAttribute('net_capital', round((float) $account->capitals()->sum('amount'), 2));
                $account->setAttribute('calculated_balance', round(
                    (float) $account->starting_balance
                    + (float) $account->capitals()->sum('amount')
                    + (float) $account->journals()->sum('profit_loss'),
                    2
                ));

                return $account;
            });

        return $this->success($accounts, 'Trading accounts loaded.');
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureTradingMember($request);
        $data = $this->validated($request);

        $account = DB::transaction(function () use ($request, $data): TradingAccount {
            $hasAccounts = TradingAccount::where('user_id', $request->user()->id)->exists();
            $makePrimary = ! $hasAccounts || (bool) ($data['is_primary'] ?? false);
            if ($makePrimary) {
                TradingAccount::where('user_id', $request->user()->id)->update(['is_primary' => false]);
            }

            return TradingAccount::create([
                ...$data,
                'user_id' => $request->user()->id,
                'is_primary' => $makePrimary,
            ]);
        });

        return $this->success($account->load('propFirmModel:id,provider,name'), 'Trading account created.', 201);
    }

    public function update(Request $request, TradingAccount $account): JsonResponse
    {
        $this->ensureOwner($request, $account);
        $data = $this->validated($request, $account);

        DB::transaction(function () use ($request, $account, $data): void {
            if ((bool) ($data['is_primary'] ?? false)) {
                TradingAccount::where('user_id', $request->user()->id)
                    ->where('id', '!=', $account->id)
                    ->update(['is_primary' => false]);
            }
            $account->update($data);
            if (! TradingAccount::where('user_id', $request->user()->id)->where('is_primary', true)->exists()) {
                $account->update(['is_primary' => true]);
            }
        });

        return $this->success($account->fresh('propFirmModel:id,provider,name'), 'Trading account updated.');
    }

    public function destroy(Request $request, TradingAccount $account): JsonResponse
    {
        $this->ensureOwner($request, $account);

        DB::transaction(function () use ($request, $account): void {
            $wasPrimary = (bool) $account->is_primary;
            $account->delete();
            if ($wasPrimary) {
                TradingAccount::where('user_id', $request->user()->id)
                    ->latest('id')
                    ->first()?->update(['is_primary' => true]);
            }
        });

        return $this->success(null, 'Trading account archived.');
    }

    private function validated(Request $request, ?TradingAccount $account = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'broker' => ['required', 'string', 'max:120'],
            'account_number' => [
                'nullable',
                'string',
                'max:120',
                Rule::unique('trading_accounts', 'account_number')
                    ->where(fn ($query) => $query
                        ->where('user_id', $request->user()->id)
                        ->where('broker', $request->input('broker'))
                        ->whereNull('deleted_at'))
                    ->ignore($account?->id),
            ],
            'platform' => ['required', Rule::in(['MT4', 'MT5', 'cTrader', 'TradingView', 'Other'])],
            'account_type' => ['required', Rule::in(['demo', 'live', 'prop_firm', 'funded'])],
            'currency' => ['required', 'string', 'size:3'],
            'starting_balance' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'current_equity' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'leverage' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in(['active', 'paused', 'passed', 'failed', 'archived'])],
            'is_primary' => ['nullable', 'boolean'],
            'prop_firm_model_id' => ['nullable', 'integer', 'exists:prop_firm_models,id'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);
    }

    private function ensureOwner(Request $request, TradingAccount $account): void
    {
        abort_unless((int) $account->user_id === (int) $request->user()->id, 404);
    }

    private function ensureTradingMember(Request $request): void
    {
        abort_unless($request->user()->isTradingMember(), 403);
    }
}
