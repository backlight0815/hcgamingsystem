<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FeatureToggle;

class FeatureManagementController extends Controller
{
    // Show all features
    public function AllFeatures()
    {
        FeatureToggle::ensureDefaultFeatures();

        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Features Management', 'url' => route('all.features')],
        ];

        $features = FeatureToggle::orderBy('feature_name')->get();
        $moduleDefinitions = FeatureToggle::moduleDefinitions();
        $featureDefinitions = FeatureToggle::defaultFeatureDefinitions();

        return view('admin.features_management.features_all', compact(
            'features',
            'breadcrumbData',
            'moduleDefinitions',
            'featureDefinitions'
        ));
    }

    public function AddFeature()
    {
        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Features Management', 'url' => route('all.features')],
            ['label' => 'Add Feature', 'url' => route('add.feature')],
        ];

        return view('admin.features_management.features_add', compact('breadcrumbData'));
    }

    public function StoreFeature(Request $request)
    {
        $request->validate([
            'feature_name' => 'required|string|max:255|unique:feature_toggles,feature_name',
        ]);

        FeatureToggle::create([
            'feature_name' => $request->feature_name,
            'enabled' => $request->has('enabled') ? $request->boolean('enabled') : true,
        ]);

        return redirect($request->input('redirect_to', route('all.features')))
            ->with('success', 'Feature added successfully!');
    }

    public function EditFeature($id)
    {
        $feature = FeatureToggle::findOrFail($id);
        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Features Management', 'url' => route('all.features')],
            ['label' => 'Edit Feature', 'url' => route('edit.feature', $feature->id)],
        ];
        $moduleDefinitions = FeatureToggle::moduleDefinitions();
        $featureDefinitions = FeatureToggle::defaultFeatureDefinitions();

        return view('admin.features_management.features_edit', compact(
            'feature',
            'breadcrumbData',
            'moduleDefinitions',
            'featureDefinitions'
        ));
    }

    public function UpdateFeature(Request $request, $id)
    {
        $request->validate([
            'feature_name' => 'required|string|max:255|unique:feature_toggles,feature_name,' . $id,
        ]);

        $feature = FeatureToggle::findOrFail($id);
        $feature->update([
            'feature_name' => $request->feature_name,
            'enabled' => $request->boolean('enabled'),
        ]);

        return redirect()->route('all.features')->with('success', 'Feature updated successfully.');
    }

    // Delete feature
    public function DeleteFeature($id)
    {
        FeatureToggle::findOrFail($id)->delete();
        return redirect()->route('all.features')->with('success', 'Feature deleted successfully.');
    }
}
