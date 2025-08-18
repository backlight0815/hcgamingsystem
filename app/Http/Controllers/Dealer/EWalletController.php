<?php

namespace App\Http\Controllers\Dealer;

use App\Http\Controllers\Controller;
use App\Models\EWallet;
use App\Models\EWalletTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\FeatureToggle;

class EWalletController extends Controller
{
    // public function __construct()
    // {
    //     // Middleware to check feature toggle for top-up routes
    //     $this->middleware(function ($request, $next) {
    //         $topUpRoutes = [
    //             'add.wallet',   // Route name for TopUpWallet view
    //             'store.wallet', // Route name for StoreWallet POST
    //         ];

    //         if (in_array($request->route()->getName(), $topUpRoutes)) {
    //             if (!feature_enabled('ewallet_topup')) {
    //                 // Return friendly error view if feature disabled
    //                 return response()->view('errors.feature_disabled', ['feature' => 'E-Wallet Top Up'], 403);
    //             }
    //         }
    //         return $next($request);
    //     });
    // }

    public function MyEWallet(Request $request)
    {
        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'My E-Wallet', 'url' => route('My.Wallet')],
        ];

        $id = Auth::user()->id;
        $ewalletData = EWallet::where('user_id', $id)->latest()->get();

        // Total amount calculations
        $totalIncome = EWalletTransaction::where('user_id', $id)
            ->where('type', 'credit')
            ->sum('amount');

        $totalExpenses = EWalletTransaction::where('user_id', $id)
            ->where('type', 'debit')
            ->sum('amount');

        $currentBalance = $totalIncome - $totalExpenses;
        $totalAmount = $currentBalance;

        $processingTotal = EWallet::where('user_id', $id)->where('status', 0)->sum('amount');
        $approvedTotal = EWallet::where('user_id', $id)->where('status', 1)->sum('amount');

        return view('agent.ewallet.mywallet_all', compact('totalAmount', 'processingTotal', 'approvedTotal', 'ewalletData', 'breadcrumbData'));
    }

public function TopUpWallet()
{
    $feature = FeatureToggle::where('feature_name', 'ewallet_topup')->first();
    $featureEnabled = $feature ? (bool)$feature->enabled : false;

    return view('agent.ewallet.mywallet_topup_add', compact('featureEnabled'));
}


    public function StoreWallet(Request $request)
    {
        // Validate the incoming request data
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'receipt' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ], [
            'amount.required' => 'Amount is required',
            'amount.numeric' => 'Amount must be a number',
            'amount.min' => 'Amount must be at least 1',
            'receipt.required' => 'Payment proof is required',
        ]);

        // Handle payment proof upload
        $image = $request->file('receipt');
        $name_gen = hexdec(uniqid()) . '.' . $image->getClientOriginalExtension();
        $image->move(public_path('upload/ewallet'), $name_gen);
        $receipt = 'upload/ewallet/' . $name_gen;

        // Save the top-up transaction to the database
        EWallet::create([
            'user_id' => Auth::id(),
            'amount' => $request->amount,
            'receipt' => $receipt,
            'created_at' => Carbon::now(),
        ]);

        $notification = [
            'message' => 'E-Wallet Top Up Request submitted Successfully',
            'alert-type' => 'success',
        ];
        return redirect()->route('My.Wallet')->with($notification);
    }

    public function AllDealerWallets()
    {
        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Dealer E-Wallet Request', 'url' => route('all.dealer.wallets')],
        ];

        $ewalletrequest = EWallet::latest()->get();
        $WalletRequestCount = EWallet::where('status', '0')->count();
        $totalAmount = EWallet::where('status', '!=', -1)->sum('amount');
        $processingTotal = EWallet::where('status', 0)->sum('amount');
        $ApprovedTotal = EWallet::where('status', 1)->sum('amount');

        return view('admin.finance.financial_all', compact('ewalletrequest', 'WalletRequestCount', 'processingTotal', 'ApprovedTotal', 'breadcrumbData'));
    }

    public function UpdateDealerWalletsApprovedStatus($id, Request $request)
    {
        $walletrequest = EWallet::find($id);
        if (!$walletrequest) {
            return redirect()->back()->with('error', 'Wallet Request Not Found');
        }

        $walletrequest->status = '1';
        $walletrequest->save();

        // Record the top-up transaction
        EWalletTransaction::create([
            'user_id' => $walletrequest->user_id,
            'amount' => $walletrequest->amount,
            'type' => 'credit',
            'remarks' => 'E-Wallet Top Up Request Approved',
        ]);

        $notification = [
            'message' => 'Wallet Request has been updated successfully',
            'alert-type' => 'success',
        ];

        return redirect()->back()->with($notification);
    }

    public function UpdateDealerWalletsRejectStatus($id)
    {
        $walletrequest = EWallet::find($id);
        if (!$walletrequest) {
            return redirect()->back()->with('error', 'Wallet Request Not Found');
        }

        $walletrequest->status = '-1';
        $walletrequest->save();

        $notification = [
            'message' => 'Wallet Request has been rejected successfully',
            'alert-type' => 'success',
        ];

        return redirect()->back()->with($notification);
    }

    public function MyWalletHistory(Request $request)
    {
        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'My E-Wallet History', 'url' => route('My.Wallet.History')],
        ];

        $id = Auth::user()->id;

        $totalIncome = EWalletTransaction::where('user_id', $id)
            ->where('type', 'credit')
            ->sum('amount');

        $totalExpenses = EWalletTransaction::where('user_id', $id)
            ->where('type', 'debit')
            ->sum('amount');

        $currentBalance = $totalIncome - $totalExpenses;

        $walletHistoryData = EWalletTransaction::where('user_id', $id)->latest()->get();

        $totalAmount = EWallet::where('user_id', $id)
            ->where('status', '!=', -1) // Exclude reject
            ->where('status', '!=', 0)  // Exclude processing
            ->sum('amount');

        $totalCreditAmount = $walletHistoryData->where('type', 'credit')->sum('amount');
        $totalDebitAmount = $walletHistoryData->where('type', 'debit')->sum('amount');

        return view('agent.ewallet.wallet_history_all', compact(
            'currentBalance',
            'walletHistoryData',
            'breadcrumbData',
            'totalAmount',
            'totalCreditAmount',
            'totalDebitAmount'
        ));
    }
}
