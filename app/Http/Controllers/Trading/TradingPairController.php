<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Controller; // ✅ Make sure this is here
use Illuminate\Http\Request;
use App\Models\TradingPair;
use Maatwebsite\Excel\Facades\Excel; // ✅ correct import
use App\Imports\TradingPairsImport;
use Maatwebsite\Excel\Validators\ValidationException;
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
        'symbol'      => 'required|string|max:50',
        'pip_factor'  => 'required|numeric',
        'pip_decimal' => 'required|integer|min:0|max:6',
    ]);

    $pair = new TradingPair();
    $pair->symbol      = $request->symbol;
    $pair->description = $request->description ?? ''; // Optional field
    $pair->pip_factor  = $request->pip_factor;
    $pair->pip_decimal = $request->pip_decimal;
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
        'symbol'      => 'required|string|max:50',
        'pip_factor'  => 'required|numeric',
        'pip_decimal' => 'required|integer|min:0|max:6',
    ]);

    $pair = TradingPair::findOrFail($id);
    $pair->symbol      = $request->symbol;
    $pair->description = $request->description ?? ''; // Optional field
    $pair->pip_factor  = $request->pip_factor;
    $pair->pip_decimal = $request->pip_decimal;
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
  public function ImportTradingPairs(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:2048',
        ]);

        try {
            // Load collection first (raw data)
            $collection = Excel::toCollection(new TradingPairsImport, $request->file('file'));

            if ($collection->isEmpty() || $collection[0]->isEmpty()) {
                return back()->with('import_errors', ['The Excel file is empty.']);
            }

            // ✅ Check headings (case-sensitive)
            $headings = $collection[0]->first()->keys()->toArray();
            $requiredHeadings = ['symbol', 'description'];

            foreach ($requiredHeadings as $heading) {
                if (!in_array($heading, $headings, true)) {
                    return back()->with('import_errors', ["Missing required column: {$heading}"]);
                }
            }

            // ✅ Check at least one row has valid values
            $validRows = $collection[0]->filter(function ($row) {
                return !empty($row['symbol']) && !empty($row['description']);
            });

            if ($validRows->isEmpty()) {
                return back()->with('import_errors', ['The Excel file does not contain any valid trading pairs.']);
            }

            // ✅ Run the import (this triggers row-level validation in TradingPairsImport)
            Excel::import(new TradingPairsImport, $request->file('file'));

        } catch (ValidationException $e) {
            $failures = $e->failures();

            // Collect row-specific errors into list
            $messages = [];
            foreach ($failures as $failure) {
                $messages[] = "Row {$failure->row()}: " . implode(', ', $failure->errors());
            }

            return back()->with('import_errors', $messages);
        }

        return redirect()->route('all.trading.pairs')
            ->with('import_success', 'Trading pairs imported successfully.');
    }
    
    // ✅ Download Template
    public function DownloadTemplate()
    {
        $headers = ['Content-Type' => 'text/csv'];
        $filename = "trading_pairs_template.csv";

        $content = "symbol,description\nXAUUSD,Gold vs USD\nEURUSD,Euro vs USD\n";

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $filename, $headers);
    }
}
