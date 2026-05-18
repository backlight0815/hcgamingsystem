<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TradingReason;

class TradingReasonController extends Controller
{
    /**
     * Display a listing of the resource.
     */
 public function index()
{
    $reasons = TradingReason::latest()->get();

    // Breadcrumb data
    $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
        ['label' => 'Trading Reasons', 'url' => route('all.trading.reason')],
    ];

    return view('admin.trading_reason.trading_reason_all', compact('reasons', 'breadcrumbData'));
}


    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
           // Breadcrumb data
    $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
        ['label' => 'Trading Reasons', 'url' => route('all.trading.reason')],
    ];
        return view('admin.trading_reason.trading_reason_add',compact('breadcrumbData'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:trading_reason,name',
            'description' => 'nullable|string',
        ]);

        TradingReason::create([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return redirect()->route('all.trading.reason')->with('success', 'Trading reason created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TradingReason $tradingReason)
    {
        return view('admin.trading_reason.trading_reason_edit', compact('tradingReason'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TradingReason $tradingReason)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:trading_reason,name,' . $tradingReason->id,
            'description' => 'nullable|string',
        ]);

        $tradingReason->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return redirect()->route('all.trading.reason')->with('success', 'Trading reason updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TradingReason $tradingReason)
    {
        $tradingReason->delete();

        return redirect()->route('all.trading.reason')->with('success', 'Trading reason deleted successfully.');
    }
}
