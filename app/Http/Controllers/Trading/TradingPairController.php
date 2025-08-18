<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Controller; // ✅ Make sure this is here
use Illuminate\Http\Request;
use App\Models\TradingPair;

class TradingPairController extends Controller
{
    // Display all trading pairs
    public function AllTradingPairs()
    {
        $pairs = TradingPair::latest()->get();
    // Total trades (includes win, loss, and breakeven)
    $totalpairs = $pairs->count();
        $breadcrumbData = [
            ['label' => 'Trading Pairs', 'url' => route('all.trading.pairs')],
        ];

        return view('admin.trading_pairs.pairs_all', compact('pairs', 'totalpairs', 'breadcrumbData'));
    }   

    // Show the form to add a new trading pair
    public function AddTradingPair()
    {
        $breadcrumbData = [
            ['label' => 'Trading Pairs', 'url' => route('all.trading.pairs')],
            ['label' => 'Add New Pair', 'url' => route('add.trading.pair')],
        ];

        return view('admin.trading_pairs.pairs_add', compact('breadcrumbData'));
    }

    // Store a new trading pair into the database
    public function StoreTradingPair(Request $request)
    {
        $request->validate([
            'symbol' => 'required|string|max:50',
        ]);

        $pair = new TradingPair();
        $pair->symbol = $request->symbol;
        $pair->description = $request->description ?? ''; // Optional field
        // If you have other fields, add them here
        $pair->save();

        return redirect()->route('all.trading.pairs')->with('success', 'Trading pair added successfully.');
    }

    // Show the form to edit an existing trading pair
    public function EditTradingPair($id)
    {
        $pair = TradingPair::findOrFail($id);

        $breadcrumbData = [
            ['label' => 'Trading Pairs', 'url' => route('all.trading.pairs')],
            ['label' => 'Edit Pair', 'url' => route('edit.trading.pair', $id)],
        ];

        return view('admin.trading_pairs.pairs_edit', compact('pair', 'breadcrumbData'));
    }

    // Update the trading pair in the database
    public function UpdateTradingPair(Request $request, $id)
    {
        $request->validate([
            'symbol' => 'required|string|max:50',
        ]);

        $pair = TradingPair::findOrFail($id);
        $pair->symbol = $request->symbol;
        $pair->description = $request->description ?? ''; // Optional field
        $pair->save();

        return redirect()->route('all.trading.pairs')->with('success', 'Trading pair updated successfully.');
    }

    // Delete a trading pair from the database
    public function DeleteTradingPair($id)
    {
        $pair = TradingPair::findOrFail($id);
        $pair->delete();

        return redirect()->route('all.trading.pairs')->with('success', 'Trading pair deleted successfully.');
    }
}
