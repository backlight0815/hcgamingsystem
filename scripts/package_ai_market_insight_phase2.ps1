$ErrorActionPreference = 'Stop'
$workspacePath = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$packagePath = Join-Path $workspacePath 'release_packages\ai_market_insight_phase2_20261014'
if (Test-Path -LiteralPath $packagePath) {
    throw 'Phase 2 release directory already exists.'
}
New-Item -ItemType Directory -Path $packagePath | Out-Null
$relativeFiles = @(
    '.env.example'
    'app\Console\Commands\SeedMarketInsightDemo.php'
    'app\Console\Commands\SyncMarketInsight.php'
    'app\Console\Kernel.php'
    'app\Http\Controllers\MarketInsightController.php'
    'app\Providers\AppServiceProvider.php'
    'config\market_data.php'
    'config\market_insight_releases.php'
    'database\migrations\2026_10_14_000001_create_market_insight_tables.php'
    'database\migrations\2026_10_14_000002_add_real_market_data_support.php'
    'resources\views\market_insight\dashboard.blade.php'
    'resources\views\market_insight\management.blade.php'
    'resources\views\admin\body\sidebar.blade.php'
    'routes\api.php'
    'routes\web.php'
    'docs\ai-market-insight-phase2.md'
)
$relativeFiles += @(Get-ChildItem -LiteralPath (Join-Path $workspacePath 'app\Services\MarketInsight') -Filter '*.php' -File |
    ForEach-Object { 'app\Services\MarketInsight\' + $_.Name })
foreach ($relativePath in $relativeFiles) {
    $sourcePath = Join-Path $workspacePath $relativePath
    $targetPath = Join-Path $packagePath $relativePath
    New-Item -ItemType Directory -Path (Split-Path -Parent $targetPath) -Force | Out-Null
    Copy-Item -LiteralPath $sourcePath -Destination $targetPath
}
Write-Output "Packaged $($relativeFiles.Count) source files."
Write-Output $packagePath
