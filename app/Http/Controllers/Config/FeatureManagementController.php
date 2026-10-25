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
        $this->ensureAdmin();
        FeatureToggle::ensureDefaultFeatures();

        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Features Management', 'url' => route('all.features')],
        ];

        $features = FeatureToggle::orderBy('feature_name')->get()
            ->reject(fn (FeatureToggle $feature) => str_starts_with($feature->feature_name, 'tradinghub_'));
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
        $this->ensureAdmin();
        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Features Management', 'url' => route('all.features')],
            ['label' => 'Add Feature', 'url' => route('add.feature')],
        ];

        return view('admin.features_management.features_add', compact('breadcrumbData'));
    }

    public function StoreFeature(Request $request)
    {
        $this->ensureAdmin();
        $request->validate([
            'feature_name' => 'required|string|max:255|unique:feature_toggles,feature_name',
        ]);

        abort_if(str_starts_with($request->feature_name, 'tradinghub_'), 403);
        FeatureToggle::create([
            'feature_name' => $request->feature_name,
            'enabled' => $request->has('enabled') ? $request->boolean('enabled') : true,
        ]);

        return redirect($request->input('redirect_to', route('all.features')))
            ->with('success', 'Feature added successfully!');
    }

    public function EditFeature($id)
    {
        $this->ensureAdmin();
        $feature = FeatureToggle::findOrFail($id);
        abort_if(str_starts_with($feature->feature_name, 'tradinghub_'), 403);
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
        $this->ensureAdmin();
        $request->validate([
            'feature_name' => 'required|string|max:255|unique:feature_toggles,feature_name,' . $id,
        ]);

        $feature = FeatureToggle::findOrFail($id);
        abort_if(str_starts_with($feature->feature_name, 'tradinghub_') || str_starts_with($request->feature_name, 'tradinghub_'), 403);
        $feature->update([
            'feature_name' => $request->feature_name,
            'enabled' => $request->boolean('enabled'),
        ]);

        return redirect()->route('all.features')->with('success', 'Feature updated successfully.');
    }

    // Delete feature
    public function DeleteFeature($id)
    {
        $this->ensureAdmin();
        $feature = FeatureToggle::findOrFail($id);
        abort_if(str_starts_with($feature->feature_name, 'tradinghub_'), 403);
        $feature->delete();
        return redirect()->route('all.features')->with('success', 'Feature deleted successfully.');
    }

    private function ensureAdmin(): void
    {
        abort_unless(auth()->check() && in_array((int) auth()->user()->role_id, [1, 2], true), 403);
    }
}
