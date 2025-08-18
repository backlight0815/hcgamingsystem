<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FeatureToggle;

class FeatureToggleController extends Controller
{
    /**
     * Display all features.
     */
    public function index()
    {
        $features = FeatureToggle::all();
        return view('admin.config.features_config', compact('features'));
    }

    /**
     * Store a new feature.
     */
    public function store(Request $request)
    {
        $request->validate([
            'feature_name' => 'required|string|max:255|unique:feature_toggles,feature_name',
        ]);

        FeatureToggle::create([
            'feature_name' => $request->feature_name,
            'enabled' => 1 // default enabled
        ]);

        return redirect()->back()->with('success', 'Feature added successfully!');
    }

    /**
     * Update a feature's enabled status.
     */
    public function update(Request $request, $id)
    {
        $feature = FeatureToggle::findOrFail($id);
        $feature->enabled = $request->enabled == '1' ? 1 : 0;
        $feature->save();

        return redirect()->back()->with('success', 'Feature updated successfully!');
    }
}
