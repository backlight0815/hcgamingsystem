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
                $breadcrumbData=[
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Features Management', 'url' => route('all.features')],

        ];
$features = FeatureToggle::orderBy('feature_name', 'desc')->get();
        return view('admin.features_management.features_all', compact('features','breadcrumbData'));
    }

    // Show add form
    public function AddFeature()
    {
        return view('admin.features_management.features_add');
    }

    // Store new feature
    public function StoreFeature(Request $request)
    {
        $request->validate([
            'feature_name' => 'required|string|max:255',
        ]);

        FeatureToggle::create([
            'feature_name' => $request->feature_name,
            'enabled' => 1, // default enabled
        ]);
// Redirect to the URL sent by form, or fallback
    return redirect($request->input('redirect_to', route('all.features')))
           ->with('success', 'Feature added successfully!');
    }

    // Show edit form
    public function EditFeature($id)
    {
        $feature = FeatureToggle::findOrFail($id);
        return view('admin.features_management.features_edit', compact('feature'));
    }

    // Update feature
    public function UpdateFeature(Request $request, $id)
    {
        $request->validate([
            'feature_name' => 'required|string|max:255',
        ]);

        $feature = FeatureToggle::findOrFail($id);
        $feature->update([
            'feature_name' => $request->feature_name,
            'enabled' => $request->enabled ?? 0,
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
