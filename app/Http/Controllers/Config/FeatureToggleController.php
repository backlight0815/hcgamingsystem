<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FeatureToggle;
use App\Models\SitePreference;
use App\Support\FrontendExperience;
use App\Support\PmsAccess;
use App\Support\ModuleAccess;
use App\Support\TradingSubmodules;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FeatureToggleController extends Controller
{
    /**
     * Display all features.
     */
    public function index()
    {
        $this->ensureAdmin();
        FeatureToggle::ensureDefaultFeatures();

        $moduleFeatureNames = array_keys(FeatureToggle::moduleDefinitions());
        $features = FeatureToggle::with('updater:id,name')->get()
            ->reject(fn (FeatureToggle $feature) => str_starts_with($feature->feature_name, 'tradinghub_'))
            ->sortBy(function (FeatureToggle $feature) use ($moduleFeatureNames): string {
                $prefix = in_array($feature->feature_name, $moduleFeatureNames, true) ? '0' : '1';

                return $prefix . '_' . $feature->feature_name;
            })
            ->values();

        $moduleDefinitions = FeatureToggle::moduleDefinitions();
        $featureDefinitions = FeatureToggle::defaultFeatureDefinitions();

        $experience = FrontendExperience::settings();
        $moduleAccess = [];
        foreach (ModuleAccess::definitions() as $key => $definition) {
            $moduleAccess[$key] = ModuleAccess::settings($key);
        }
        $updaters = \App\Models\User::whereIn('id', array_filter(array_column($moduleAccess, 'updated_by')))->pluck('name', 'id');
        return view('admin.config.features_config', compact('features', 'moduleDefinitions', 'featureDefinitions', 'experience', 'moduleAccess', 'updaters'));
    }

    /**
     * Store a new feature.
     */
    public function store(Request $request)
    {
        $this->ensureAdmin();
        $request->validate([
            'feature_name' => 'required|string|max:255|unique:feature_toggles,feature_name',
        ]);

        abort_if(str_starts_with($request->feature_name, 'tradinghub_'), 403);
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
        $this->ensureAdmin();
        $validated = $request->validate(['enabled' => ['required', Rule::in(['0', '1'])]]);
        $feature = FeatureToggle::findOrFail($id);
        if (str_starts_with($feature->feature_name, 'tradinghub_')) {
            abort_unless($request->user()?->isGlobalSuperAdmin(), 403);
        }
        $feature->enabled = $validated['enabled'] === '1';
        $feature->updated_by = $request->user()->id;
        $feature->save();

        return redirect()->back()->with('success', 'Feature updated successfully.');
    }

    public function tradingModules(Request $request)
    {
        abort_unless($request->user()?->isGlobalSuperAdmin(), 403);
        FeatureToggle::ensureDefaultFeatures();
        $definitions = TradingSubmodules::definitions();
        $features = FeatureToggle::whereIn('feature_name', array_map(TradingSubmodules::featureName(...), array_keys($definitions)))
            ->get()->keyBy('feature_name');
        $domains = TradingSubmodules::managementDomains();
        $domainForKey = [];
        $orderForKey = [];
        foreach ($domains as $domainKey => $domain) {
            foreach ($domain['modules'] as $order => $key) {
                $domainForKey[$key] = $domainKey;
                $orderForKey[$key] = $order;
            }
        }
        $modules = [];
        foreach ($definitions as $key => $definition) {
            $feature = $features[TradingSubmodules::featureName($key)] ?? null;
            $domainKey = $domainForKey[$key] ?? 'workspace';
            $modules[] = [
                'key' => $key,
                'label' => $definition['label'],
                'description' => $key === 'market_analyst'
                    ? 'AI-driven market analysis and insights for traders.' : $definition['description'],
                'purpose' => $key === 'market_analyst'
                    ? 'Provides market analysis, trends and insights to support trading decisions.' : $definition['description'],
                'domain' => $domainKey,
                'order' => $orderForKey[$key] ?? count($definitions),
                'parent' => $definition['parent'] ?? null,
                'enabled' => (bool) ($feature?->enabled ?? $definition['default']),
                'updated_at' => $feature?->updated_at?->format('Y-m-d H:i:s.u'),
            ];
        }
        $domainData = collect($domains)->map(fn ($domain) => collect($domain)->except('modules')->all())->all();
        $payload = ['modules' => $modules, 'domains' => $domainData];

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return view('admin.config.trading_modules', compact('payload'));
    }

    public function updateTradingModule(Request $request, string $key)
    {
        abort_unless($request->user()?->isGlobalSuperAdmin(), 403);
        abort_unless(array_key_exists($key, TradingSubmodules::definitions()), 404);
        $data = $request->validate([
            'enabled' => ['required', Rule::in(['0', '1'])],
            'expected_updated_at' => ['sometimes', 'nullable', 'date_format:Y-m-d H:i:s.u'],
            'expected_enabled' => ['sometimes', Rule::in(['0', '1'])],
        ]);
        $featureName = TradingSubmodules::featureName($key);
        $saved = DB::transaction(function () use ($request, $data, $featureName): ?FeatureToggle {
            $feature = FeatureToggle::where('feature_name', $featureName)->lockForUpdate()->first();
            if ($request->expectsJson() && (
                ! array_key_exists('expected_updated_at', $data)
                || ! array_key_exists('expected_enabled', $data)
                || ($feature?->updated_at?->format('Y-m-d H:i:s.u') !== $data['expected_updated_at'])
                || ((bool) $feature?->enabled !== ($data['expected_enabled'] === '1'))
            )) {
                return null;
            }
            return FeatureToggle::updateOrCreate(
                ['feature_name' => $featureName],
                ['enabled' => $data['enabled'] === '1', 'updated_by' => $request->user()->id]
            );
        });
        if (! $saved) {
            return response()->json(['message' => 'This module was changed by another administrator. Refresh and review its current setting.'], 409);
        }
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'TradingHub module updated.',
                'enabled' => $saved->enabled,
                'updated_at' => $saved->updated_at?->format('Y-m-d H:i:s.u'),
            ]);
        }
        return redirect()->route('admin.trading-modules.index')->with('success', 'TradingHub module updated.');
    }

    public function updateHomepage(Request $request)
    {
        $this->ensureAdmin();
        $validated = $request->validate([
            'homepage_experience' => ['required', Rule::in([
                FrontendExperience::TRADING,
                FrontendExperience::ECOMMERCE,
                FrontendExperience::COMBINED,
            ])],
        ]);
        DB::transaction(fn () => SitePreference::updateOrCreate(
            ['key' => FrontendExperience::PREFERENCE_KEY],
            ['value' => $validated['homepage_experience']]
        ));
        FrontendExperience::forgetCache();
        \App\Support\AdminAudit::record($request->user(), 'company.homepage_changed', 'site_preference', null,
            ['homepage_experience' => $validated['homepage_experience']]);

        return redirect()->back()->with('success', 'Default homepage updated.');
    }

    public function updateModuleAccess(Request $request, string $module)
    {
        $this->ensureAdmin();
        abort_unless(isset(ModuleAccess::definitions()[$module]), 404);
        $policies = $module === ModuleAccess::PMS
            ? ['approval', 'open', 'invitation', 'disabled'] : ['public', 'admin', 'disabled'];
        $data = $request->validate([
            'public_visible' => ['required', Rule::in(['0', '1'])],
            'show_login_entry' => ['sometimes', 'required', Rule::in(['0', '1'])],
            'registration_policy' => ['required', Rule::in($policies)],
        ]);
        // Older forms can still update public visibility without resetting discovery.
        $showLoginEntry = isset($data['show_login_entry'])
            ? $data['show_login_entry'] === '1' : ModuleAccess::settings($module)['show_login_entry'];
        DB::transaction(function () use ($request, $module, $data, $showLoginEntry): void {
            $values = [
                'public_visible' => $data['public_visible'] === '1',
                'show_login_entry' => $showLoginEntry,
                'registration_policy' => $data['registration_policy'],
                'updated_by' => $request->user()->id,
                'updated_at' => now(),
            ];
            $query = DB::table('module_access_settings')->where('module_key', $module);
            if ($query->exists()) $query->update($values);
            else DB::table('module_access_settings')->insert($values + ['module_key' => $module, 'created_at' => now()]);
            if ($module === ModuleAccess::PMS) {
                DB::table('pms_workspace_settings')->update(['registration_policy' => $data['registration_policy'], 'updated_at' => now()]);
            }
        });
        FrontendExperience::forgetCache();
        \App\Support\AdminAudit::record($request->user(), 'module.access_settings_changed', 'module_access_setting', null,
            ['module' => $module, 'public_visible' => $data['public_visible'], 'show_login_entry' => $showLoginEntry, 'registration_policy' => $data['registration_policy']]);

        return back()->with('success', 'Module access settings updated.');
    }

    public function updateMembership(Request $request)
    {
        abort_unless($request->user()?->isGlobalSuperAdmin(), 403);
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'module_key' => ['required', Rule::in([ModuleAccess::TRADINGHUB, ModuleAccess::DEALERSHIP])],
            'status' => ['required', Rule::in(['active', 'suspended'])],
        ]);
        $member = \App\Models\User::findOrFail($data['user_id']);
        if ($member->projectManagementIdentity && str_ends_with($member->email, '@internal.invalid')) {
            return back()->withErrors(['user_id' => 'This PMS-only identity needs a separate website account before another module can be granted.']);
        }
        $query = DB::table('module_memberships')->where('user_id', $data['user_id'])->where('module_key', $data['module_key']);
        $values = ['status' => $data['status'], 'assigned_by' => $request->user()->id, 'updated_at' => now()];
        if ($query->exists()) $query->update($values);
        else DB::table('module_memberships')->insert($values + ['user_id' => $data['user_id'], 'module_key' => $data['module_key'], 'created_at' => now()]);
        \App\Support\AdminAudit::record($request->user(), 'module.membership_changed', 'user', (int) $data['user_id'],
            ['module' => $data['module_key'], 'status' => $data['status']]);
        return back()->with('success', 'Module membership updated.');
    }

    private function ensureAdmin(): void
    {
        abort_unless(PmsAccess::administrator(auth()->user()), 403);
    }
}
