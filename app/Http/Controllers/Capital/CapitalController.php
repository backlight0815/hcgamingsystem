<?php

namespace App\Http\Controllers\Capital;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Capital;
use App\Models\TradingJournal;
use App\Services\TradingJournalAnalytics;
use Illuminate\Support\Facades\Auth;

class CapitalController extends Controller
{
    public function index()
    {
        $capitals = Capital::where('user_id', Auth::id())->latest()->get();
        return view('admin.capital.index', compact('capitals'));
    }

    public function create(Request $request)
    {
        $type = $request->input('type', 1); // Default to Deposit
        return view('admin.trading_journals.deposit', compact('type'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type'          => 'required|in:1,2', // 1 = Deposit, 2 = Withdraw
            'depositAmount' => 'required|numeric|min:0.01',
            'deposit_date'  => 'required|date',
            'notes'         => 'nullable|string|max:255',
        ]);

        $amount = $validated['depositAmount'];

        // If withdrawal, ensure balance is sufficient and make amount negative
        if ((int)$validated['type'] === 2) {
            $balance = $this->getUserCapitalBalance(Auth::id());
            if ($amount > $balance) {
                return redirect()->back()
                    ->withErrors(['depositAmount' => 'Withdrawal failed: insufficient balance.'])
                    ->withInput();
            }

            $eligibility = $this->withdrawalEligibility(Auth::id());
            if (! $eligibility['eligible']) {
                return redirect()->back()
                    ->withErrors(['depositAmount' => $eligibility['message']])
                    ->withInput();
            }

            $amount = -$amount; // Make withdrawal negative
        }

        Capital::create([
            'user_id'      => Auth::id(),
            'type'         => $validated['type'],
            'amount'       => $amount,
            'deposit_date' => $validated['deposit_date'],
            'notes'        => $validated['notes'] ?? null,
        ]);

        $notification = [
            'message'    => ($validated['type'] == 1 ? 'Deposit' : 'Withdrawal') . ' recorded successfully!',
            'alert-type' => 'success',
        ];

        return redirect()->route('all.trading.journals')->with($notification);
    }

  private function getUserCapitalBalance($userId)
{
    $capitalSum = Capital::where('user_id', $userId)->sum('amount');
    $tradingProfit = \App\Models\TradingJournal::where('user_id', $userId)->sum('profit_loss');
    return $capitalSum + $tradingProfit;
}

    private function withdrawalEligibility($userId): array
    {
        $analytics = new TradingJournalAnalytics();
        $trades = TradingJournal::where('user_id', $userId)
            ->where(function ($query) {
                $query->where('type', 'trade')->orWhereNull('type');
            })
            ->orderBy('close_date')
            ->get();
        $accountBalance = (float) Capital::where('user_id', $userId)
            ->where('type', 1)
            ->sum('amount');

        $bestDayRule = $analytics->bestDayRule($trades);
        $grossProfitRule = $analytics->grossProfitRule($trades, $accountBalance);

        if (($bestDayRule['passed'] ?? false) && ($grossProfitRule['passed'] ?? false)) {
            return [
                'eligible' => true,
                'message' => '',
            ];
        }

        $messages = [];
        if (! ($bestDayRule['passed'] ?? false)) {
            $messages[] = '40% Best Day Rule pending (best day is '
                . number_format((float) $bestDayRule['score_percent'], 2)
                . '%; need '
                . number_format((float) $bestDayRule['additional_profit_needed'], 2)
                . 'u more generated profit).';
        }

        if (! ($grossProfitRule['passed'] ?? false)) {
            $messages[] = '2% gross profit rule pending ('
                . number_format((float) $grossProfitRule['gross_profit'], 2)
                . 'u / '
                . number_format((float) $grossProfitRule['required_amount'], 2)
                . 'u).';
        }

        return [
            'eligible' => false,
            'message' => 'Withdrawal failed: ' . implode(' ', $messages),
        ];
    }


    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'depositAmount' => 'required|numeric|min:0.01',
            'deposit_date'  => 'required|date',
            'notes'         => 'nullable|string|max:255',
        ]);

        $capital = Capital::findOrFail($id);

        // Preserve withdrawal negativity
        $amount = $capital->type == 2 ? -$validated['depositAmount'] : $validated['depositAmount'];

        $capital->update([
            'amount'       => $amount,
            'deposit_date' => $validated['deposit_date'],
            'notes'        => $validated['notes'] ?? null,
        ]);

        return redirect()->route('capital.index')->with([
            'message' => 'Capital record updated successfully!',
            'alert-type' => 'success',
        ]);
    }

    public function destroy($id)
    {
        $capital = Capital::findOrFail($id);
        $capital->delete();

        return redirect()->route('capital.index')->with([
            'message' => 'Capital record deleted successfully!',
            'alert-type' => 'success',
        ]);
    }
}
