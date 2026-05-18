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
        FeatureToggle::ensureDefaultFeatures();

        $moduleFeatureNames = array_keys(FeatureToggle::moduleDefinitions());
        $features = FeatureToggle::all()
            ->sortBy(function (FeatureToggle $feature) use ($moduleFeatureNames): string {
                $prefix = in_array($feature->feature_name, $moduleFeatureNames, true) ? '0' : '1';

                return $prefix . '_' . $feature->feature_name;
            })
            ->values();

        $moduleDefinitions = FeatureToggle::moduleDefinitions();
        $featureDefinitions = FeatureToggle::defaultFeatureDefinitions();

        return view('admin.config.features_config', compact('features', 'moduleDefinitions', 'featureDefinitions'));
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

        return redirect()->back()->with('success', 'Feature added successfully.');
    }

    /**
     * Update a feature's enabled status.
     */
    public function update(Request $request, $id)
    {
        $feature = FeatureToggle::findOrFail($id);
        $feature->enabled = $request->enabled == '1' ? 1 : 0;
        $feature->save();

        return redirect()->back()->with('success', 'Feature updated successfully.');
    }
}
