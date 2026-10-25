<?php

use App\Http\Controllers\Api\Mobile\V1\AdminController as MobileAdminController;
use App\Http\Controllers\Api\Mobile\V1\AuthController as MobileAuthController;
use App\Http\Controllers\Api\Mobile\V1\ContentController as MobileContentController;
use App\Http\Controllers\Api\Mobile\V1\FinalExamController as MobileFinalExamController;
use App\Http\Controllers\Api\Mobile\V1\JournalAttachmentController as MobileJournalAttachmentController;
use App\Http\Controllers\Api\Mobile\V1\JournalImportController as MobileJournalImportController;
use App\Http\Controllers\Api\Mobile\V1\ProfileController as MobileProfileController;
use App\Http\Controllers\Api\Mobile\V1\TradingAccountController as MobileTradingAccountController;
use App\Http\Controllers\Api\Mobile\V1\TradingBlogController as MobileTradingBlogController;
use App\Http\Controllers\Api\Mobile\V1\TradingDataController as MobileTradingDataController;
use App\Http\Controllers\Api\Mobile\V1\TradingJournalSyncController as MobileTradingJournalSyncController;
use App\Http\Controllers\Api\Mobile\V1\TradingSignalController as MobileTradingSignalController;
use App\Http\Controllers\MarketInsightController;
use App\Http\Middleware\AuthenticateTradingJournalSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(\App\Http\Middleware\EnsureTradingModuleEnabled::class)->group(function (): void {
    Route::get('/v1/market-outlooks', [\App\Http\Controllers\Trading\MarketOutlookPublicationController::class, 'index'])->name('api.market_outlooks.index');
    Route::get('/v1/market-outlooks/{analysis}', [\App\Http\Controllers\Trading\MarketOutlookPublicationController::class, 'show'])->whereNumber('analysis')->name('api.market_outlooks.show');
});

Route::prefix('v1/project-management')->name('api.pms.v1.')
    ->middleware(['throttle:60,1', 'auth:sanctum', \App\Http\Middleware\EnsurePmsApiBearer::class, \App\Http\Middleware\EnsurePmsAccess::class])
    ->controller(\App\Http\Controllers\Api\Pms\V1\ProjectManagementController::class)
    ->group(function (): void {
        Route::post('/updateProjectInfo', 'updateProjectInfo')->name('projects.updateInfo');
        Route::get('/getProjectInfo', 'getProjectInfo')->name('projects.getInfo');
        Route::get('/getProjectTickets', 'getProjectTickets')->name('projects.tickets');
        Route::post('/updateTicketInfo', 'updateTicketInfo')->name('tickets.updateInfo');
        Route::get('/getAssignableUsers', 'getAssignableUsers')->name('assignableUsers');
    });

// PMS authentication supports native clients without exposing browser registration in combined mode.
Route::prefix('backoffice/pms')->name('api.pms.')->group(function (): void {
    Route::post('/auth/login', [\App\Http\Controllers\Pms\AuthController::class, 'apiLogin'])->middleware('throttle:10,1')->name('auth.login');
    Route::post('/auth/register', [\App\Http\Controllers\Pms\AuthController::class, 'apiRegister'])->middleware('throttle:5,1')->name('auth.register');
    Route::post('/auth/forgot-password', [\App\Http\Controllers\Pms\AuthController::class, 'sendReset'])->middleware('throttle:5,1')->name('auth.forgot_password');
});

// PMS JSON endpoints share authorization and workflow services with the private web workspace.
Route::middleware(['auth:sanctum', \App\Http\Middleware\EnsurePmsAccess::class, \App\Http\Middleware\EnsurePmsApiAccess::class])
    ->prefix('backoffice/pms')->name('api.pms.')->group(function (): void {
        Route::get('/change-reviews', [\App\Http\Controllers\Pms\ChangeReviewController::class, 'index'])->name('change-reviews.index');
        Route::post('/change-reviews/{review}/decision', [\App\Http\Controllers\Pms\ChangeReviewController::class, 'decide'])->name('change-reviews.decide');
        Route::post('/change-reviews/{review}/cancel', [\App\Http\Controllers\Pms\ChangeReviewController::class, 'cancel'])->name('change-reviews.cancel');
        Route::get('/auth/profile', [\App\Http\Controllers\Pms\AuthController::class, 'apiProfile'])->name('auth.profile');
        Route::delete('/auth/token', [\App\Http\Controllers\Pms\AuthController::class, 'apiLogout'])->name('auth.logout');
        Route::get('/memberships', [\App\Http\Controllers\Pms\TeamController::class, 'index'])->name('memberships.index');
        Route::get('/organization-options', [\App\Http\Controllers\Pms\OrganizationSettingsController::class, 'options'])->name('organization.options');
        Route::get('/organization-settings', [\App\Http\Controllers\Pms\OrganizationSettingsController::class, 'index'])->name('organization.index');
        Route::post('/organization-settings/departments', [\App\Http\Controllers\Pms\OrganizationSettingsController::class, 'department'])->name('organization.departments.store');
        Route::put('/organization-settings/departments/{department}', [\App\Http\Controllers\Pms\OrganizationSettingsController::class, 'department'])->name('organization.departments.update');
        Route::post('/organization-settings/positions', [\App\Http\Controllers\Pms\OrganizationSettingsController::class, 'position'])->name('organization.positions.store');
        Route::put('/organization-settings/positions/{position}', [\App\Http\Controllers\Pms\OrganizationSettingsController::class, 'position'])->name('organization.positions.update');
        Route::put('/organization-settings/profiles/{user}', [\App\Http\Controllers\Pms\OrganizationSettingsController::class, 'profile'])->name('organization.profiles.update');
        Route::post('/organization-settings/legacy-cycles/{cycle}/reconcile', [\App\Http\Controllers\Pms\OrganizationSettingsController::class, 'reconcileCycle'])->name('organization.legacy-cycles.reconcile');
        Route::get('/invitations', [\App\Http\Controllers\Pms\InvitationController::class, 'index'])->name('invitations.index');
        Route::post('/invitations', [\App\Http\Controllers\Pms\InvitationController::class, 'store'])->name('invitations.store');
        Route::post('/invitations/batch', [\App\Http\Controllers\Pms\InvitationController::class, 'batch'])->name('invitations.batch');
        Route::delete('/invitations/{invitation}', [\App\Http\Controllers\Pms\InvitationController::class, 'revoke'])->name('invitations.revoke');
        Route::post('/invitations/{invitation}/reissue', [\App\Http\Controllers\Pms\InvitationController::class, 'reissue'])->name('invitations.reissue');
        Route::post('/memberships', [\App\Http\Controllers\Pms\TeamController::class, 'provision'])->name('memberships.store');
        Route::put('/memberships/{profile}', [\App\Http\Controllers\Pms\TeamController::class, 'update'])->name('memberships.update');
        Route::get('/projects', [\App\Http\Controllers\Pms\ProjectController::class, 'index'])->name('projects.index');
        Route::post('/projects', [\App\Http\Controllers\Pms\ProjectController::class, 'store'])->name('projects.store');
        Route::get('/projects/{project}', [\App\Http\Controllers\Pms\ProjectController::class, 'show'])->name('projects.show');
        Route::put('/projects/{project}', [\App\Http\Controllers\Pms\ProjectController::class, 'update'])->name('projects.update');
        Route::post('/projects/{project}/archive', [\App\Http\Controllers\Pms\ProjectController::class, 'archive'])->name('projects.archive');
        Route::post('/projects/{project}/unarchive', [\App\Http\Controllers\Pms\ProjectController::class, 'unarchive'])->name('projects.unarchive');
        Route::delete('/projects/{project}', [\App\Http\Controllers\Pms\ProjectController::class, 'trash'])->name('projects.trash');
        Route::post('/projects/{id}/restore', [\App\Http\Controllers\Pms\ProjectController::class, 'restore'])->name('projects.restore');
        Route::delete('/projects/{id}/purge', [\App\Http\Controllers\Pms\ProjectController::class, 'purge'])->name('projects.purge');
        Route::post('/projects/{project}/members', [\App\Http\Controllers\Pms\ProjectController::class, 'addMember'])->name('projects.members.store');
        Route::delete('/projects/{project}/members/{userId}', [\App\Http\Controllers\Pms\ProjectController::class, 'removeMember'])->name('projects.members.destroy');
        Route::get('/cycles', [\App\Http\Controllers\Pms\CycleController::class, 'index'])->name('cycles.index');
        Route::post('/cycles', [\App\Http\Controllers\Pms\CycleController::class, 'store'])->name('cycles.store');
        Route::get('/cycles/{cycle}', [\App\Http\Controllers\Pms\CycleController::class, 'show'])->name('cycles.show');
        Route::put('/cycles/{cycle}', [\App\Http\Controllers\Pms\CycleController::class, 'update'])->name('cycles.update');
        Route::post('/cycles/{cycle}/duplicate', [\App\Http\Controllers\Pms\CycleController::class, 'duplicate'])->name('cycles.duplicate');
        Route::post('/cycles/{cycle}/start', [\App\Http\Controllers\Pms\CycleController::class, 'start'])->name('cycles.start');
        Route::post('/cycles/{cycle}/close', [\App\Http\Controllers\Pms\CycleController::class, 'close'])->name('cycles.close');
        Route::post('/cycles/{cycle}/cancel', [\App\Http\Controllers\Pms\CycleController::class, 'cancel'])->name('cycles.cancel');
        Route::delete('/cycles/{cycle}', [\App\Http\Controllers\Pms\CycleController::class, 'destroy'])->name('cycles.destroy');
        Route::post('/cycles/{cycle}/issues', [\App\Http\Controllers\Pms\CycleController::class, 'assignIssue'])->name('cycles.issues.store');
        Route::delete('/cycles/{cycle}/issues/{issue}', [\App\Http\Controllers\Pms\CycleController::class, 'removeIssue'])->name('cycles.issues.destroy');
        Route::put('/cycles/{cycle}/phases/{phaseId}', [\App\Http\Controllers\Pms\CycleController::class, 'updatePhase'])->name('cycles.phases.update');
        Route::post('/delivery-cycles/{cycle}/tickets/{ticket}/stage', [\App\Http\Controllers\Pms\GlobalCycleController::class, 'assignTicketStage'])->name('global-cycles.tickets.stage');
        Route::get('/tickets', [\App\Http\Controllers\Pms\TicketController::class, 'index'])->name('tickets.index');
        Route::post('/tickets', [\App\Http\Controllers\Pms\TicketController::class, 'store'])->name('tickets.store');
        Route::get('/tickets/{ticket}', [\App\Http\Controllers\Pms\TicketController::class, 'show'])->name('tickets.show');
        Route::put('/tickets/{ticket}', [\App\Http\Controllers\Pms\TicketController::class, 'update'])->name('tickets.update');
        Route::put('/tickets/{ticket}/qat-resolution', [\App\Http\Controllers\Pms\TicketController::class, 'updateQatResolution'])->name('tickets.qat-resolution');
        Route::put('/tickets/{ticket}/qa-estimate', [\App\Http\Controllers\Pms\TicketController::class, 'updateQaEstimate'])->name('tickets.qa-estimate');
        Route::post('/tickets/{ticket}/transitions', [\App\Http\Controllers\Pms\TicketController::class, 'transition'])->name('tickets.transition');
        Route::post('/tickets/{ticket}/verifications', [\App\Http\Controllers\Pms\TicketController::class, 'verify'])->name('tickets.verify');
        Route::post('/tickets/{ticket}/links', [\App\Http\Controllers\Pms\TicketController::class, 'link'])->name('tickets.links.store');
        Route::get('/tickets/{ticket}/blockers', [\App\Http\Controllers\Pms\BlockerController::class, 'index'])->name('tickets.blockers.index');
        Route::post('/tickets/{ticket}/blockers', [\App\Http\Controllers\Pms\BlockerController::class, 'store'])->name('tickets.blockers.store');
        Route::post('/tickets/{ticket}/hold', [\App\Http\Controllers\Pms\TicketHoldController::class, 'hold'])->name('tickets.hold');
        Route::post('/tickets/{ticket}/resume', [\App\Http\Controllers\Pms\TicketHoldController::class, 'resume'])->name('tickets.resume');
        Route::put('/tickets/{ticket}/blockers/{blocker}', [\App\Http\Controllers\Pms\BlockerController::class, 'update'])->name('tickets.blockers.update');
        Route::post('/tickets/{ticket}/blockers/{blocker}/resolve', [\App\Http\Controllers\Pms\BlockerController::class, 'resolve'])->name('tickets.blockers.resolve');
        Route::post('/tickets/{ticket}/blockers/{blocker}/cancel', [\App\Http\Controllers\Pms\BlockerController::class, 'cancel'])->name('tickets.blockers.cancel');
        Route::post('/tickets/{ticket}/blockers/{blocker}/reopen', [\App\Http\Controllers\Pms\BlockerController::class, 'reopen'])->name('tickets.blockers.reopen');
        Route::get('/tickets/{ticket}/contributors', [\App\Http\Controllers\Pms\TicketContributorController::class, 'index'])->name('tickets.contributors.index');
        Route::get('/tickets/{ticket}/pics', [\App\Http\Controllers\Pms\TicketPicController::class, 'index'])->name('tickets.pics.index');
        Route::put('/tickets/{ticket}/pics', [\App\Http\Controllers\Pms\TicketPicController::class, 'update'])->name('tickets.pics.update');
        Route::delete('/tickets/{ticket}/pics/{user}', [\App\Http\Controllers\Pms\TicketPicController::class, 'destroy'])->name('tickets.pics.destroy');
        Route::post('/tickets/{ticket}/contributors', [\App\Http\Controllers\Pms\TicketContributorController::class, 'store'])->name('tickets.contributors.store');
        Route::delete('/tickets/{ticket}/contributors/{user}', [\App\Http\Controllers\Pms\TicketContributorController::class, 'destroy'])->name('tickets.contributors.destroy');
        Route::post('/tickets/{ticket}/comments', [\App\Http\Controllers\Pms\TicketCommentController::class, 'store'])->name('tickets.comments.store');
        Route::put('/tickets/{ticket}/comments/{comment}', [\App\Http\Controllers\Pms\TicketCommentController::class, 'update'])->name('tickets.comments.update');
        Route::delete('/tickets/{ticket}/comments/{comment}', [\App\Http\Controllers\Pms\TicketCommentController::class, 'destroy'])->name('tickets.comments.destroy');
        Route::post('/tickets/{ticket}/attachments', [\App\Http\Controllers\Pms\TicketAttachmentController::class, 'store'])->name('tickets.attachments.store');
        Route::get('/tickets/{ticket}/attachments/{attachment}', [\App\Http\Controllers\Pms\TicketAttachmentController::class, 'show'])->name('tickets.attachments.show');
        Route::delete('/tickets/{ticket}/attachments/{attachment}', [\App\Http\Controllers\Pms\TicketAttachmentController::class, 'destroy'])->name('tickets.attachments.destroy');
        Route::get('/releases', [\App\Http\Controllers\Pms\ReleaseController::class, 'index'])->name('releases.index');
        Route::post('/releases/assign-cycle', [\App\Http\Controllers\Pms\ReleaseController::class, 'assignCycle'])->name('releases.cycle-assign');
        Route::post('/releases', [\App\Http\Controllers\Pms\ReleaseController::class, 'store'])->name('releases.store');
        Route::get('/releases/{release}', [\App\Http\Controllers\Pms\ReleaseController::class, 'show'])->name('releases.show');
        Route::put('/releases/{release}', [\App\Http\Controllers\Pms\ReleaseController::class, 'update'])->name('releases.update');
        Route::post('/releases/{release}/tickets', [\App\Http\Controllers\Pms\ReleaseController::class, 'addTicket'])->name('releases.tickets.store');
        Route::post('/releases/{release}/status', [\App\Http\Controllers\Pms\ReleaseController::class, 'advance'])->name('releases.advance');
        Route::post('/releases/{release}/notes', [\App\Http\Controllers\Pms\ReleaseController::class, 'draftNotes'])->name('releases.notes');
        Route::post('/releases/{release}/approve', [\App\Http\Controllers\Pms\ReleaseController::class, 'approve'])->name('releases.approve');
        Route::post('/releases/{release}/deploy', [\App\Http\Controllers\Pms\ReleaseController::class, 'deploy'])->name('releases.deploy');
        Route::post('/releases/{release}/complete', [\App\Http\Controllers\Pms\ReleaseController::class, 'complete'])->name('releases.complete');
        Route::get('/dashboard', [\App\Http\Controllers\Pms\WorkspaceController::class, 'dashboard'])->name('dashboard');
        Route::get('/reports', [\App\Http\Controllers\Pms\WorkspaceController::class, 'reports'])->name('reports');
        Route::get('/issues', [\App\Http\Controllers\Pms\IssueController::class, 'index'])->name('issues.index');
        Route::post('/issues', [\App\Http\Controllers\Pms\IssueController::class, 'store'])->name('issues.store');
        Route::get('/issues/{issue}', [\App\Http\Controllers\Pms\IssueController::class, 'show'])->name('issues.show');
        Route::put('/issues/{issue}', [\App\Http\Controllers\Pms\IssueController::class, 'update'])->name('issues.update');
        Route::delete('/issues/{issue}', [\App\Http\Controllers\Pms\IssueController::class, 'destroy'])->name('issues.destroy');
        Route::post('/issues/{id}/restore', [\App\Http\Controllers\Pms\IssueController::class, 'restore'])->name('issues.restore');
        Route::post('/issues/{issue}/transitions', [\App\Http\Controllers\Pms\IssueController::class, 'transition'])->name('issues.transition');
    });

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('mobile/v1')->middleware(\App\Http\Middleware\EnsureTradingModuleEnabled::class)
    ->name('api.mobile.v1.')->group(function (): void {
    Route::get('/auth/roles', [MobileAuthController::class, 'roles'])->name('auth.roles');
    Route::post('/auth/login', [MobileAuthController::class, 'login'])->middleware('throttle:6,1')->name('auth.login');
    Route::post('/auth/register', [MobileAuthController::class, 'register'])->name('auth.register');
    Route::post('/auth/forgot-password', [MobileAuthController::class, 'forgotPassword'])->name('auth.forgot_password');

    Route::middleware([AuthenticateTradingJournalSync::class, 'throttle:api'])->prefix('journal-sync')->name('journal_sync.')->group(function (): void {
        Route::get('/changes', [MobileTradingJournalSyncController::class, 'changes'])->name('changes');
        Route::post('/batch', [MobileTradingJournalSyncController::class, 'batch'])->name('batch');
        Route::get('/statistics', [MobileTradingJournalSyncController::class, 'statistics'])->name('statistics');
        Route::get('/', [MobileTradingJournalSyncController::class, 'index'])->name('index');
        Route::post('/', [MobileTradingJournalSyncController::class, 'store'])->name('store');
        Route::get('/{syncId}', [MobileTradingJournalSyncController::class, 'show'])->name('show');
        Route::patch('/{syncId}', [MobileTradingJournalSyncController::class, 'patch'])->name('patch');
        Route::delete('/{syncId}', [MobileTradingJournalSyncController::class, 'destroy'])->name('destroy');
    });

    Route::middleware(['auth:sanctum', \App\Http\Middleware\EnsureTradingApiAccess::class])->group(function (): void {
        Route::get('/auth/me', [MobileAuthController::class, 'me'])->name('auth.me');
        Route::post('/auth/verify-password', [MobileAuthController::class, 'verifyPassword'])->name('auth.verify_password');
        Route::post('/auth/logout', [MobileAuthController::class, 'logout'])->name('auth.logout');

        Route::get('/dashboard', [MobileTradingDataController::class, 'dashboard'])->name('dashboard');
        Route::get('/trading-data', [MobileTradingDataController::class, 'index'])->name('trading_data');
        Route::post('/journals', [MobileTradingDataController::class, 'storeJournal'])->name('journals.store');
        Route::put('/journals/{journal}', [MobileTradingDataController::class, 'updateJournal'])->name('journals.update');
        Route::delete('/journals/{journal}', [MobileTradingDataController::class, 'destroyJournal'])->name('journals.destroy');
        Route::post('/journals/import', [MobileJournalImportController::class, 'store'])->name('journals.import');
        Route::post('/journals/{journal}/attachments', [MobileJournalAttachmentController::class, 'store'])->name('journals.attachments.store');
        Route::delete('/journals/{journal}/attachments/{attachment}', [MobileJournalAttachmentController::class, 'destroy'])->name('journals.attachments.destroy');
        Route::post('/capitals', [MobileTradingDataController::class, 'storeCapital'])->name('capitals.store');
        Route::put('/capitals/{capital}', [MobileTradingDataController::class, 'updateCapital'])->name('capitals.update');
        Route::delete('/capitals/{capital}', [MobileTradingDataController::class, 'destroyCapital'])->name('capitals.destroy');
        Route::get('/trading-accounts', [MobileTradingAccountController::class, 'index'])->name('trading_accounts.index');
        Route::post('/trading-accounts', [MobileTradingAccountController::class, 'store'])->name('trading_accounts.store');
        Route::put('/trading-accounts/{account}', [MobileTradingAccountController::class, 'update'])->name('trading_accounts.update');
        Route::delete('/trading-accounts/{account}', [MobileTradingAccountController::class, 'destroy'])->name('trading_accounts.destroy');

        Route::get('/positions', [MobileTradingDataController::class, 'positions'])->name('positions.index');
        Route::post('/positions', [MobileTradingDataController::class, 'storePosition'])->name('positions.store');
        Route::get('/exam', [MobileTradingDataController::class, 'exam'])->name('exam.index');
        Route::post('/exam/{attempt}/submit', [MobileTradingDataController::class, 'submitExam'])->name('exam.submit');
        Route::get('/final-exam', [MobileFinalExamController::class, 'status'])->name('final_exam.status');
        Route::post('/final-exam/start', [MobileFinalExamController::class, 'start'])->name('final_exam.start');
        Route::post('/final-exam/{attempt}/submit', [MobileFinalExamController::class, 'submit'])->name('final_exam.submit');
        Route::post('/final-exam/prop-firm', [MobileFinalExamController::class, 'selectPropFirm'])->name('final_exam.prop_firm');
        Route::get('/admin/overview', [MobileAdminController::class, 'overview'])->name('admin.overview');
        Route::get('/admin/users', [MobileAdminController::class, 'users'])->name('admin.users.index');
        Route::patch('/admin/users/{user}', [MobileAdminController::class, 'updateUser'])->name('admin.users.update');
        Route::get('/admin/onboarding', [MobileAdminController::class, 'onboarding'])->name('admin.onboarding.index');
        Route::post('/admin/onboarding/{application}/review', [MobileAdminController::class, 'reviewOnboarding'])->name('admin.onboarding.review');
        Route::get('/admin/positions', [MobileAdminController::class, 'positions'])->name('admin.positions.index');
        Route::post('/admin/positions/{application}/review', [MobileAdminController::class, 'reviewPosition'])->name('admin.positions.review');

        Route::get('/content', [MobileContentController::class, 'index'])->name('content');
        Route::post('/recordings/{recording}/access', [MobileContentController::class, 'accessRecording'])->name('recordings.access');
        Route::post('/marketing-resources/{resource}/access', [MobileContentController::class, 'accessMarketingResource'])->name('marketing_resources.access');

        Route::get('/profile', [MobileProfileController::class, 'show'])->name('profile.show');
        Route::patch('/profile', [MobileProfileController::class, 'update'])->name('profile.update');
        Route::put('/profile/password', [MobileProfileController::class, 'updatePassword'])->name('profile.password');
        Route::delete('/profile', [MobileProfileController::class, 'destroy'])->name('profile.destroy');
        Route::post('/profile/onboarding', [MobileProfileController::class, 'submitOnboarding'])->name('profile.onboarding');
    });

    Route::middleware('mobile.signal.auth')->prefix('signals')->name('signals.')->group(function (): void {
        Route::get('/', [MobileTradingSignalController::class, 'index'])
            ->middleware('throttle:signal-reads')
            ->name('index');
        Route::get('/stream', [MobileTradingSignalController::class, 'stream'])
            ->middleware('throttle:signal-streams')
            ->name('stream');
        Route::post('/', [MobileTradingSignalController::class, 'store'])
            ->middleware('throttle:signal-writes')
            ->name('store');
        Route::get('/{signal}', [MobileTradingSignalController::class, 'show'])
            ->middleware('throttle:signal-reads')
            ->name('show');
        Route::put('/{signal}', [MobileTradingSignalController::class, 'update'])
            ->middleware('throttle:signal-writes')
            ->name('update');
        Route::post('/{signal}/publish', [MobileTradingSignalController::class, 'publish'])
            ->middleware('throttle:signal-publish')
            ->name('publish');
        Route::post('/{signal}/cancel', [MobileTradingSignalController::class, 'cancel'])
            ->middleware('throttle:signal-writes')
            ->name('cancel');
        Route::post('/{signal}/close', [MobileTradingSignalController::class, 'close'])
            ->middleware('throttle:signal-writes')
            ->name('close');
        Route::post('/{signal}/lifecycle', [MobileTradingSignalController::class, 'lifecycle'])
            ->middleware('throttle:signal-writes')
            ->name('lifecycle');
    });

    Route::middleware('mobile.signal.auth')->prefix('market-insights')->name('market_insights.')->controller(MarketInsightController::class)->group(function (): void {
        Route::get('/instruments', 'instrumentsApi')->name('instruments');
        Route::get('/latest', 'latestApi')->name('latest');
          Route::get('/price', 'priceApi')->name('price');
          Route::get('/candles', 'candlesApi')->name('candles');
          Route::get('/status', 'statusApi')->name('status');
        Route::get('/history', 'historyApi')->name('history');
        Route::post('/refresh', 'refresh')->name('refresh');
        Route::get('/admin/settings', 'settingsApi')->name('admin.settings');
          Route::get('/admin/provider', 'providerApi')->name('admin.provider');
          Route::post('/admin/sync', 'syncData')->name('admin.sync');
        Route::put('/admin/settings', 'updateSettings')->name('admin.settings.update');
        Route::post('/admin/instruments', 'saveInstrument')->name('admin.instruments.store');
        Route::put('/admin/instruments/{id}', 'saveInstrument')->name('admin.instruments.update');
        Route::post('/admin/generate', 'generate')->name('admin.generate');
        Route::post('/admin/recalculate', 'recalculate')->name('admin.recalculate');
        Route::get('/admin/logs', 'executionLogs')->name('admin.logs');
        Route::patch('/admin/history/{id}/archive', 'archive')->name('admin.archive');
        Route::get('/{id}', 'showApi')->name('show');
    });

    Route::prefix('blogs')->name('blogs.')->group(function (): void {
        Route::get('/', [MobileTradingBlogController::class, 'index'])
            ->middleware('throttle:blog-reads')
            ->name('index');
        Route::get('/stream', [MobileTradingBlogController::class, 'stream'])
            ->middleware('throttle:blog-streams')
            ->name('stream');
        Route::post('/images', [MobileTradingBlogController::class, 'uploadImage'])
            ->middleware(['mobile.blog.auth', 'throttle:blog-writes'])
            ->name('images.store');
        Route::post('/', [MobileTradingBlogController::class, 'store'])
            ->middleware(['mobile.blog.auth', 'throttle:blog-writes'])
            ->name('store');
        Route::get('/{blog}', [MobileTradingBlogController::class, 'show'])
            ->middleware('throttle:blog-reads')
            ->name('show');
        Route::put('/{blog}', [MobileTradingBlogController::class, 'update'])
            ->middleware(['mobile.blog.auth', 'throttle:blog-writes'])
            ->name('update');
        Route::post('/{blog}/publish', [MobileTradingBlogController::class, 'publish'])
            ->middleware(['mobile.blog.auth', 'throttle:blog-publish'])
            ->name('publish');
        Route::post('/{blog}/unpublish', [MobileTradingBlogController::class, 'unpublish'])
            ->middleware(['mobile.blog.auth', 'throttle:blog-publish'])
            ->name('unpublish');
        Route::delete('/{blog}', [MobileTradingBlogController::class, 'destroy'])
            ->middleware(['mobile.blog.auth', 'throttle:blog-writes'])
            ->name('destroy');
    });
});
