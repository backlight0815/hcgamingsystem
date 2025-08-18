<?php

namespace App\Http\Controllers\Capital;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Capital;
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
