<?php

namespace App\Support;

use App\Models\FeatureToggle;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ModuleAccess
{
    public const TRADINGHUB = 'tradinghub';
    public const DEALERSHIP = 'dealership';
    public const PMS = 'pms';

    private const MODULES = [
        self::TRADINGHUB => ['name' => 'TradingHub', 'feature' => FeatureToggle::MODULE_TRADING, 'roles' => [201, 202, 501, 502, 750, 760, 770], 'visible' => true, 'registration' => 'public', 'login' => 'tradinghub.login'],
        self::DEALERSHIP => ['name' => 'Dealership Ecommerce', 'feature' => FeatureToggle::MODULE_DEALERSHIP_ECOMMERCE, 'roles' => [350, 700], 'visible' => true, 'registration' => 'public', 'login' => 'dealership.login'],
        self::PMS => ['name' => 'Project Management', 'feature' => FeatureToggle::MODULE_PROJECT_MANAGEMENT, 'roles' => [], 'visible' => false, 'registration' => 'approval', 'login' => 'pms.login'],
    ];

    public static function definitions(): array { return self::MODULES; }

    public static function enabled(string $module): bool
    {
        return isset(self::MODULES[$module]) && FeatureToggle::isEnabled(self::MODULES[$module]['feature'], $module !== self::PMS);
    }

    public static function settings(string $module): array
    {
        $definition = self::MODULES[$module];
        $row = Schema::hasTable('module_access_settings')
            ? DB::table('module_access_settings')->where('module_key', $module)->first() : null;

        return [
            'public_visible' => $row ? (bool) $row->public_visible : $definition['visible'],
            'show_login_entry' => (bool) ($row->show_login_entry ?? true),
            'registration_policy' => $module === self::PMS ? PmsModule::settings()->registration_policy : ($row->registration_policy ?? $definition['registration']),
            'updated_at' => $row->updated_at ?? null,
            'updated_by' => $row->updated_by ?? null,
        ];
    }

    public static function publicVisible(string $module): bool
    {
        return self::enabled($module) && self::settings($module)['public_visible'];
    }

    public static function loginEntryVisible(string $module): bool
    {
        return self::enabled($module) && self::settings($module)['show_login_entry'];
    }

    public static function loginEntries(?string $currentModule = null): array
    {
        return array_filter(self::MODULES, fn (array $definition, string $key): bool =>
            $key !== $currentModule && self::loginEntryVisible($key), ARRAY_FILTER_USE_BOTH);
    }

    public static function registrationAllowed(string $module): bool
    {
        return self::enabled($module) && in_array(self::settings($module)['registration_policy'], ['public', 'approval', 'open'], true);
    }

    public static function legacyModuleForRole(?int $role): ?string
    {
        foreach ([self::TRADINGHUB, self::DEALERSHIP] as $module) {
            if (in_array($role, self::MODULES[$module]['roles'], true)) return $module;
        }

        return null;
    }

    public static function allows(?User $user, string $module): bool
    {
        if (! $user || (string) $user->status !== '1' || ! self::enabled($module)) return false;
        if ($module === self::PMS) return PmsAccess::workspace($user);
        if (! isset(self::MODULES[$module])) return false;

        if (Schema::hasTable('module_memberships')) {
            $membership = DB::table('module_memberships')->where('user_id', $user->id)->where('module_key', $module)->value('status');
            if ($membership !== null) return $membership === 'active';
        }

        // Existing role IDs retain their original single-module meaning. The two
        // historical backoffice administrator roles keep their existing reach.
        return $user->isBackofficeAdministrator() || in_array((int) $user->role_id, self::MODULES[$module]['roles'], true);
    }

    public static function safeIntended(\Illuminate\Http\Request $request, string $module): ?string
    {
        $url = $request->session()->get('url.intended');
        if (! is_string($url) || ! str_starts_with($url, $request->getSchemeAndHttpHost().'/')) return null;
        $path = parse_url($url, PHP_URL_PATH);
        $prefixes = match ($module) {
            self::PMS => ['/backoffice/project-management/'],
            self::TRADINGHUB => ['/trading-hub/', '/trading/', '/trader/'],
            self::DEALERSHIP => ['/dealer/', '/customer/', '/checkout'],
            default => [],
        };
        if ($module === self::PMS && preg_match('#^/backoffice/project-management/(login|register|forgot-password|reset-password|logout|organization-options)(/|$)#', (string) $path)) return null;
        foreach ($prefixes as $prefix) if (str_starts_with((string) $path, $prefix)) return $url;

        return null;
    }
}
