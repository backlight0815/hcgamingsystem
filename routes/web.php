<?php
use App\Models\SignalProviderCertificate; // Make sure you have this model

use App\Http\Controllers\Cart\CartController;
use App\Http\Controllers\Dealer\AddressController;
use App\Http\Controllers\Dealer\CustomerController;
use App\Http\Controllers\Dealer\EWalletController;
use App\Http\Controllers\Demo\demoController;
use App\Http\Controllers\Home\About_Page\AcknowledgementController;
use App\Http\Controllers\Home\About_Page\EducationController;
use App\Http\Controllers\Home\About_Page\SkillController;
use App\Http\Controllers\Home\DashboardController;
use App\Http\Controllers\Order\OrderController;
use App\Http\Controllers\Order\OrderItemController;
use App\Http\Controllers\Order\TransactionsController;
use App\Http\Controllers\Product\ProductCategoryController;
use App\Http\Controllers\Product\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Sales_Performances\SalesController;
use App\Http\Controllers\Stock\DealerProductCategoryController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Home\HomeSliderController;
use App\Http\Controllers\Home\About_Page\AboutController;
use App\Http\Controllers\Home\PortfolioController;
use App\Http\Controllers\Home\BlogCategoryController;
use App\Http\Controllers\Home\BlogController;
use App\Http\Controllers\Home\FooterController;
use App\Http\Controllers\Home\ContactController;
use App\Http\Controllers\Home\CommunityShowcaseController;
use App\Http\Controllers\Home\ServiceController;
use App\Http\Controllers\Home\RecruitmentController;
use App\Http\Controllers\Stock\StockController;
use App\Http\Controllers\Home\AccountController;
use App\Http\Controllers\Stock\DealerStockController;
use App\Http\Controllers\Home\ReferralController;
use App\Http\Controllers\Home\UserController;
use App\Http\Controllers\Dealer\ShippingController;
use App\Http\Controllers\Transaction\TransactionController;
use App\Http\Controllers\Commission\CommissionController;
use App\Http\Controllers\Trading\TradingJournalController;
use App\Http\Controllers\Trading\TradingPairController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\Event\EventController;
use App\Http\Controllers\Capital\CapitalController;
use App\Http\Controllers\Trading\TradersPerformancesController;
use App\Http\Controllers\Config\FeatureToggleController;
use App\Http\Controllers\Config\RoleManagementController;
use App\Http\Controllers\Config\FeatureManagementController;
use App\Http\Controllers\Trading\MarketAnalystController;
use App\Http\Controllers\Trading\FundedTraderController;
use App\Http\Controllers\Trading\LeaderboardController;
use App\Http\Controllers\Trading\TradingSignalController;
use App\Http\Controllers\Trading\KnowledgeCentreController;
use App\Http\Controllers\Trading\CommunityManagementController;
use App\Http\Controllers\Trading\SignalPerformanceController;
use App\Http\Controllers\Trading\NewsController;
use App\Http\Controllers\Trading\TradingReasonController;
use App\Http\Controllers\Trading\SignalProviderCertificateController;
use App\Http\Controllers\Trading\TradingStatisticsController;
use App\Http\Controllers\Trading\TradingBacktestController;
use App\Http\Controllers\Trading\TradingRecordingController;
use App\Http\Controllers\Trading\TradingBlogController;
use App\Http\Controllers\Trading\TraderOnboardingController;
use App\Http\Controllers\Trading\TradingPositionApplicationController;
use App\Http\Controllers\Trading\TraderReadinessChecklistController;
use App\Http\Controllers\Trading\MarketingResourceController;
use App\Http\Controllers\Trading\TradingAppointmentController;
use App\Http\Controllers\Trading\TradingExaminationController;
use App\Http\Controllers\Support\SupportTicketController;
use App\Http\Controllers\AppNotificationController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Route::get('/', function () {
//     return view('frontend.index');
// });

Route::controller(demoController::class)->group(function(){
    Route::get('/about','Index')->name('about.page')->middleware('check');
    Route::get('/contact','ContactMethod')->name('contact.page');
    Route::get('/','HomeMain')->name('home');

});

Route::get('/hc-trading-community', [CommunityShowcaseController::class, 'show'])
    ->name('community.showcase');

//Admin All Route

Route::middleware(['auth'])->group(function(){

    Route::controller(AdminController::class)->group(function(){
        Route::get('/admin/logout','destroy')->name('admin.logout');
        Route::get('/admin/profile','Profile')->name('admin.profile');
        Route::get('/edit/profile','EditProfile')->name('edit.profile');
        Route::post('/store/profile','StoreProfile')->name('store.profile');
        Route::get('/change/password','ChangePassword')->name('change.password');
        Route::post('/update/password','UpdatePassword')->name('update.password');
       // Route::get('/users/status/{user_id}/{status_code}','UpdateStatus')->name('update.status');

});

});



//Home Slide All Route
Route::controller(HomeSliderController::class)->group(function(){
    Route::get('/home/slide','HomeSlider')->name('home.slide');
    Route::get('/home/slide/setup', 'SetupHomeSlider')->name('setup.home.slide');
    Route::post('/update/slider','UpdateSlider')->name('update.slider');


});


//About Page All Route
Route::controller(AboutController::class)->group(function(){
    Route::get('/about/page','AboutPage')->name('about.page');
    Route::get('/about/setup','SetupAboutPage')->name('setup.about.page');

    Route::post('/update/about','UpdateAbout')->name('update.about');
    Route::get('/about','HomeAbout')->name('home.about');
    Route::get('/about/multi/image','AboutMultiImage')->name('about.multi.image');
    Route::post('/store/multi/image','StoreMultiImage')->name('store.multi.image');
    Route::get('/all/multi/image','AllMultiImage')->name('all.multi.image');
    Route::get('/edit/multi/image/{id}','EditMultiImage')->name('edit.multi.image');
    Route::post('/update/multi/image','UpdateMultiImage')->name('update.multi.image');
    Route::get('/delete/multi/image/{id}','DeleteMultiImage')->name('delete.multi.image');


});

//Skill Controller All Route

Route::controller(SkillController::class)->group(function(){
    Route::get('/all/skill','AllSkill')->name('all.skill');
    Route::get('/add/skill','AddSkill')->name('add.skill');
    Route::post('/store/skill','StoreSkill')->name('store.skill');
    Route::get('/edit/skill/{id}','EditSkill')->name('edit.skill');
    Route::post('/update/skill/{id}','UpdateSkill')->name('update.skill');
    Route::get('/delete/skill/{id}','DeleteSkill')->name('delete.skill');
});

//Acknowlegement All Route
Route::controller(AcknowledgementController::class)->group(function(){
    Route::get('/all/acknowledgement','AllAcknowledgement')->name('all.acknowledgement');
    Route::get('/add/acknowledgement','AddAcknowledgement')->name('add.acknowledgement');
    Route::post('/store/acknowledgement','StoreAcknowledgement')->name('store.acknowledgement');
    Route::get('/edit/acknowledgement/{id}','EditAcknowledgement')->name('edit.acknowledgement');
    Route::post('/update/acknowledgement/{id}','UpdateAcknowledgement')->name('update.acknowledgement');
    Route::get('/delete/acknowledgement/{id}','DeleteAcknowledgement')->name('delete.acknowledgement');

});
//Education All Route
Route::controller(EducationController::class)->group(function(){
    Route::get('/all/education','AllEducation')->name('all.education');
    Route::get('/add/education','AddEducation')->name('add.education');
    Route::post('/store/education','StoreEducation')->name('store.education');
    Route::get('/edit/education/{id}','EditEducation')->name('edit.education');
    Route::post('/update/education/{id}','UpdateEducation')->name('update.education');
    Route::get('/delete/education/{id}','DeleteEducation')->name('delete.education');


});

//Portfolio All Route
Route::controller(PortfolioController::class)->group(function(){
    Route::get('/all/portfolio','AllPortfolio')->name('all.portfolio');
    Route::get('/add/portfolio','AddPortfolio')->name('add.portfolio');
    Route::post('/store/portfolio','StorePortfolio')->name('store.portfolio');

    Route::get('/edit/portfolio/{id}','EditPortfolio')->name('edit.portfolio');
    Route::post('/update/portfolio','UpdatePortfolio')->name('update.portfolio');
    Route::get('/delete/portfolio/{id}','DeletePortfolio')->name('delete.portfolio');
    Route::get('/portfolio/details/{id}','PortfolioDetails')->name('portfolio.details');
    Route::get('/portfolio','HomePortfolio')->name('home.portfolio');


});
//Account All Route
Route::controller(AccountController::class)->group(function(){
    Route::get('/all/account','AllAccount')->name('all.account');
    Route::get('/all/signalprovider','AllSignalProvider')->name('all.signal_provider');
    Route::get('/edit/signalprovider/account/{id}','EditSignalProviderAccount')->name('edit.signal_provider.account');
    Route::post('/update/signalprovider/account','UpdateSignalProvider')->name('update.signal_provider');

    Route::get('/all/agent','AllAgent')->name('all.agent.account');
    Route::get('/edit/agent/account/{id}','EditAgentAccount')->name('edit.agent.account');
    Route::post('/update/agent/account','UpdateAgent')->name('update.agent');
    Route::post('/update','updateAgentStatus')->name('updateAgentStatus');
    Route::get('/all/customer','AllCustomer')->name('all.customer.account');
    Route::get('/edit/customer/account/{id}','EditCustomerAccount')->name('edit.customer.account');
    Route::post('/update/customer/account','UpdateCustomer')->name('update.customer');
    Route::post('/update','updateCustomerStatus')->name('updateCustomerStatus');
    Route::get('/all/admin','AllAdmin')->name('all.admin.account');
    Route::get('/edit/admin/account/{id}','EditAdminAccount')->name('edit.admin.account');
    Route::post('/update/admin/account','UpdateAdmin')->name('update.admin');
    Route::post('/update','updateAdminStatus')->name('updateAdminStatus');
    Route::post('/update/account','UpdateAccount')->name('update.account');
    Route::get('/edit/account/{id}','EditAccount')->name('edit.account');
    Route::post('/update','updateAccountStatus')->name('updateAccountStatus');
    Route::get('/delete/account/{id}','DeleteAccount')->name('delete.account');

     Route::get('/all/traders','AllTraders')->name('all.traders.account');
    Route::get('/edit/traders/account/{id}','EditTradersAccount')->name('edit.traders.account');
    Route::post('/update/traders/account','UpdateTraders')->name('update.traders');
    Route::post('/update','updateTradersStatus')->name('updateTradersStatus');

});


//Blog Category All Route
Route::controller(BlogCategoryController::class)->group(function(){
    Route::get('/all/blog/category','AllBlogCategory')->name('all.blog.category');
    Route::get('/add/blog/category','AddBlogCategory')->name('add.blog.category');

    Route::post('/store/blog/category','StoreBlogCategory')->name('store.blog.category');
    Route::get('/edit/blog/category/{id}','EditBlogCategory')->name('edit.blog.category');
    Route::post('/update/blog/category/{id}','UpdateBlogCategory')->name('update.blog.category');
    Route::get('/delete/blog/category/{id}','DeleteBlogCategory')->name('delete.blog.category');

});

// Signal Performance
Route::controller(SignalPerformanceController::class)->group(function() {
    Route::get('/signal-performance', 'index')->name('signal.performance.index');
    Route::post('/signal-performance/send-discord', 'sendDiscord')

        ->name('signal.performance.sendDiscord'); // ✅ new route

           // Weekly Discord
    Route::post('/signal-performance/send-discord-weekly', 'sendDiscordWeekly')
        ->name('signal.performance.sendDiscordWeekly');

                   // Weekly Discord
    Route::post('/signal-performance/submit-weekly-performances', 'submitWeeklyPerformances')
        ->name('signal.performance.submitWeeklyPerformances');

          // ✅ Export Excel
    Route::get('/signal-performance/export', 'exportExcel')
        ->name('signal.performance.export');

    Route::get('/signal-performance/report/pdf', 'exportPdf')
        ->name('signal.performance.report.pdf');

         // ✅ Import Excel
    Route::post('/signal-performance/import', 'importExcel')
        ->name('signal.performance.import'); // new

        Route::get('/signal-performance/template',  'downloadTemplate')
    ->name('signal.performance.template');

});

Route::controller(NewsController::class)->group(function () {

    // List all news
    Route::get('/news', 'index')->name('trading.news.index');

    // Show create form
    Route::get('/news/create', 'create')->name('trading.news.create');

    // Store new news
    Route::post('/news/store', 'store')->name('trading.news.store');

    // Show edit form
    Route::get('/news/{id}/edit', 'edit')->name('trading.news.edit');

    // Update news
    Route::post('/news/{id}/update', 'update')->name('trading.news.update');

    // Delete news
    Route::delete('/news/{id}/delete', 'destroy')->name('trading.news.destroy');

    // Send a single news to Discord
    Route::post('/news/{id}/send-discord', 'sendToDiscord')->name('trading.news.sendDiscord');
});

Route::controller(KnowledgeCentreController::class)->group(function() {
    // CRUD
    Route::get('/knowledge-centre', 'index')->name('knowledge.centre.index');
    Route::get('/knowledge-centre/create', 'create')->name('knowledge.centre.create');
    Route::post('/knowledge-centre', 'store')->name('knowledge.centre.store');
    Route::get('/knowledge-centre/{id}/edit', 'edit')->name('knowledge.centre.edit');
    Route::post('/knowledge-centre/{id}', 'update')->name('knowledge.centre.update');
    Route::delete('/knowledge-centre/{id}', 'destroy')->name('knowledge.centre.destroy');
    Route::post('/knowledge-centre/{knowledge}/approve', 'approve')->name('knowledge.centre.approve');
    Route::get('/trading/knowledge-centre', 'traderIndex')->middleware('auth')->name('trading.knowledge.centre.index');

    // Discord send (POST)
    Route::post('/knowledge-centre/send-discord/{id}', 'sendToDiscord')
        ->name('knowledge.centre.sendDiscord'); // ✅ new route

            // Bulk download all PDFs as ZIP
    Route::get('/knowledge-centre/download-zip', 'downloadZip')
        ->name('knowledge.centre.downloadZip'); // 📦 new route
});
// ===== Trading Signal Routes =====
Route::controller(TradingSignalController::class)->group(function () {

    // 📄 List & Manage Trading Signals
    Route::get('/all/trading/signals', 'index')->name('all.trading.signals');
    Route::get('/add/trading/signal', 'create')->name('add.trading.signal');
    Route::post('/store/trading/signal', 'store')->name('store.trading.signal');
    Route::get('/edit/trading/signal/{id}', 'edit')->name('edit.trading.signal');
Route::post('/update/trading/signal/{id}', 'update')->name('update.trading.signal');
    Route::get('/delete/trading/signal/{id}', 'destroy')->name('delete.trading.signal');
Route::get('/view/trading/signal/{id}', 'show')->name('view.trading.signal');

    // 🔴 Cancel Trading Signal
    Route::post('/cancel/trading/signal/{id}', 'cancel')->name('cancel.trading.signal');

    // ✅ Activate Trading Signal
    Route::post('/activate/trading/signal/{id}', 'activate')->name('activate.trading.signal');

    // 🛑 Stop Loss (SL) Trading Signal
    Route::post('/sl/trading/signal/{id}', 'sl')->name('sl.trading.signal');

    // ⚖️ Breakeven Routes
    Route::post('/set-be/trading/signal/{id}', 'setBE')->name('setbe.trading.signal');      // Announce Set BE
    Route::post('/be-hitted/trading/signal/{id}', 'beHitted')->name('behitted.trading.signal');  // BE actually hit

    // 🎯 Take Profit (TP) Trading Signals
    Route::post('/tp1/trading/signal/{id}', 'tp1')->name('tp1.trading.signal');
    Route::post('/tp2/trading/signal/{id}', 'tp2')->name('tp2.trading.signal');
    Route::post('/tp3/trading/signal/{id}', 'tp3')->name('tp3.trading.signal');
    Route::post('/tp4/trading/signal/{id}', 'tp4')->name('tp4.trading.signal');
    Route::post('/tp5/trading/signal/{id}', 'tp5')->name('tp5.trading.signal');
    Route::post('/tp6/trading/signal/{id}', 'tp6')->name('tp6.trading.signal');
    Route::post('/tp7/trading/signal/{id}', 'tp7')->name('tp7.trading.signal');
    Route::post('/tp8/trading/signal/{id}', 'tp8')->name('tp8.trading.signal');
    Route::post('/tp9/trading/signal/{id}', 'tp9')->name('tp9.trading.signal');
    Route::post('/tp10/trading/signal/{id}', 'tp10')->name('tp10.trading.signal');

  // ✅ Mark Trading Signal as Done
Route::post('/close/trading/signal/{id}', 'markDone')
    ->name('close.trading.signal');

    // 📊 Signal Dashboard
    Route::get('/signals/dashboard', 'memberDashboard')
        ->name('member.signals.dashboard');

    // 📡 Active Signals
    Route::get('/signals/active', 'memberActiveSignals')
        ->name('member.signals.active');

    // ✅ Closed Signals
    Route::get('/signals/closed', 'memberClosedSignals')
        ->name('member.signals.closed');

    // 📜 Signal History
    Route::get('/signals/history', 'memberSignalHistory')
        ->name('member.signals.history');

    // 🔎 View Signal Details
    Route::get('/signals/view/{id}', 'memberViewSignal')
        ->name('member.signals.view');

});

Route::controller(TradingReasonController::class)->group(function() {
    // List all reasons
    Route::get('/all/trading/reason', 'index')->name('all.trading.reason');

    // Create new reason
    Route::get('/add/trading/reason', 'create')->name('add.trading.reason');
    Route::post('/store/trading/reason', 'store')->name('store.trading.reason');

    // Edit existing reason
    Route::get('/edit/trading/reason/{id}', 'edit')->name('edit.trading.reason');
    Route::post('/update/trading/reason/{id}', 'update')->name('update.trading.reason');

    // Delete reason
    Route::get('/delete/trading/reason/{id}', 'destroy')->name('delete.trading.reason');
});

Route::controller(CommunityManagementController::class)->group(function() {
    // 🔹 List all communities
    Route::get('/all/communities', 'index')->name('communities.index');

    // 🔹 Add new community
    Route::get('/add/community', 'create')->name('communities.create');
    Route::post('/store/community', 'store')->name('communities.store');

    // 🔹 Edit existing community
    Route::get('/edit/community/{id}', 'edit')->name('communities.edit');
    Route::post('/update/community/{id}', 'update')->name('communities.update');

    // 🔹 Delete community
    Route::get('/delete/community/{id}', 'destroy')->name('communities.destroy');

    // 🔹 Community documentation library
    Route::get('/community-documents', 'documentsIndex')->middleware('auth')->name('communities.documents.index');
    Route::post('/community-documents', 'storeDocument')->middleware('auth')->name('communities.documents.store');
    Route::post('/community-documents/{document}/view', 'viewDocument')->middleware('auth')->name('communities.documents.view');
    Route::post('/community-documents/{document}/download', 'downloadDocument')->middleware('auth')->name('communities.documents.download');
    Route::delete('/community-documents/{document}', 'destroyDocument')->middleware('auth')->name('communities.documents.destroy');

    // 🔹 TP Notification Dashboard
    Route::get('/tp-settings-dashboard', 'tpSettingsDashboard')->name('communities.tp_settings');
    Route::post('/tp-settings-dashboard', 'updateTpSettingsDashboard')->name('communities.tp_settings_dashboard.update');

     // 🔹 Discord @everyone toggle update
    Route::post('/everyone-toggle', 'updateEveryoneToggle')->name('communities.everyone_toggle.update');
});

Route::middleware(['auth'])->controller(CommunityShowcaseController::class)->group(function () {
    Route::get('/admin/community-showcase', 'edit')->name('admin.community.showcase.edit');
    Route::post('/admin/community-showcase', 'update')->name('admin.community.showcase.update');
});

Route::controller(MarketAnalystController::class)->group(function() {

    // 🔹 List all market analyses
    Route::get('/market-analyst/all', 'index')->name('market-analyst.index');

    // 🔹 Add new market analysis
    Route::get('/market-analyst/create', 'create')->name('market-analyst.create');
    Route::post('/market-analyst/store', 'store')->name('market-analyst.store');

    // 🔹 Edit market analysis
    Route::get('/market-analyst/edit/{id}', 'edit')->name('market-analyst.edit');
    Route::post('/market-analyst/update/{id}', 'update')->name('market-analyst.update');

    // 🔹 Delete market analysis
    Route::get('/market-analyst/delete/{id}', 'destroy')->name('market-analyst.destroy');
// 🔹 View single market analysis details
Route::get('/market-analyst/show/{id}', 'show')->name('market-analyst.show');

    // 🔹 Send market analysis to Discord
    Route::post('/market-analyst/send-discord/{id}', 'sendToDiscord')->name('market-analyst.sendDiscord');

    Route::get('/trading/market-analyst', 'traderIndex')->name('trading.market-analyst.index');
    Route::get('/trading/market-analyst/{analysis}', 'traderShow')->name('trading.market-analyst.show');
});



Route::middleware(['auth'])->controller(SignalProviderCertificateController::class)->group(function() {

    // Admin: show certificate management page.
    Route::get('/certificate/all', 'index')->name('certificate.index');

    // Eligible members: show own published certificates.
    Route::get('/certificate/my', 'providerindex')->name('provider.certificate.index');

    // Admin: show certificate generator form.
    Route::get('/certificate/add', 'create')->name('certificate.create');

    // Admin: generate certificate image for selected user and level.
    Route::post('/certificate/upload/{userId}/{level}', 'upload')->name('certificate.upload');

    // Admin: compatibility route for certificate creation.
    Route::post('/certificate/add', 'addCertificate')->name('certificate.add');

    // Certificate workflow and password-confirmed access.
    Route::post('/certificate/{certificate}/approve', 'approve')->name('certificate.approve');
    Route::post('/certificate/{certificate}/publish', 'publish')->name('certificate.publish');
    Route::post('/certificate/{certificate}/regenerate', 'regenerate')->name('certificate.regenerate');
    Route::post('/certificate/{certificate}/revoke', 'revoke')->name('certificate.revoke');
    Route::post('/certificate/{certificate}/view', 'view')->name('certificate.view');
    Route::post('/certificate/{certificate}/download', 'download')->name('certificate.download');
    Route::delete('/certificate/{certificate}', 'destroy')->name('certificate.destroy');

    // Optional: list all certificates for a specific user.
    Route::get('/certificates/{userId}', 'index')->name('certificates.index');
});
//Blog All Route
Route::controller(BlogController::class)->group(function(){
    Route::get('/all/blog','AllBlog')->name('all.blog');
    Route::get('/add/blog','AddBlog')->name('add.blog');
    Route::post('/store/blog','StoreBlog')->name('store.blog');
    Route::get('/edit/blog/{id}','EditBlog')->name('edit.blog');
    Route::post('/update/blog','UpdateBlog')->name('update.blog');
    Route::get('/delete/blog/{id}','DeleteBlog')->name('delete.blog');
    Route::get('/blog/details/{id}','BlogDetails')->name('blog.details');
    Route::get('/category/blog/{id}','CategoryBlog')->name('category.blog');

    Route::get('/blog','HomeBlog')->name('home.blog');

    Route::get('/blog/details/{id}', [BlogController::class, 'blogDetails'])
    ->name('blog.details')
    ->middleware('pageview');


});

//Footer  All Route
Route::controller(FooterController::class)->group(function(){
    Route::get('/footer/setup','FooterSetup')->name('footer.setup');
    Route::post('/update/footer','UpdateFooter')->name('update.footer');


});


//Contact  All Route
Route::controller(ContactController::class)->group(function(){
    Route::get('/contact','Contact')->name('contact.me');
    Route::post('/store/message','StoreMessage')->name('store.message');
    Route::get('/contact/message','ContactMessage')->name('contact.message');
    Route::get('/delete/message/{id}','DeleteMessage')->name('delete.message');


});
//Recruitment Route
Route::middleware(['auth'])->group(function(){

    Route::controller(RecruitmentController::class)->group(function(){
        Route::get('/recruitment/agent','Agent')->name('all.agent');
        // Route::get('/recruitment/agentmanagement','AllAgent')->name('admin.all.agent');

       // Route::get('/users/status/{user_id}/{status_code}','UpdateStatus')->name('update.status');
});

});
//Recruitment Route
Route::middleware(['auth'])->group(function(){

    Route::controller(CustomerController::class)->group(function(){
        Route::get('/recruitment/customer','Customer')->name('all.customer');
       // Route::get('/users/status/{user_id}/{status_code}','UpdateStatus')->name('update.status');
});

});



//My Address Route
Route::middleware(['auth'])->group(function(){

    Route::controller(AddressController::class)->group(function(){
        Route::get('/my/address','MyAddress')->name('dealer.address');

        // Route::get('/store/address','StoreAddress')->name('store.address');
        Route::post('/update/address','Updateaddress')->name('update.address');



});

});





//Service All Route
Route::controller(ServiceController::class)->group(function(){
    Route::get('/all/service','AllService')->name('all.service');
    Route::get('/add/service',action: 'AddService')->name('add.service');
    Route::post('/store/service','StoreService')->name('store.service');
    Route::post('/update/service','UpdateService')->name('update.service');
    Route::get('/edit/service/{id}','EditService')->name('edit.service');
    Route::get('/delete/service/{id}','DeleteService')->name('delete.service');
    Route::get('/service','HomeService')->name('home.service');
    Route::get('/service/details/{id}','ServiceDetails')->name('service.details');


});



//DealerStock All Route

Route::controller(DealerStockController::class)->group(function () {
    Route::get('/dealer/all/dealerstock', 'AllDealerProduct')->name('all.dealer.products');
    Route::get('/dealer/add/product', 'AddDealerProduct')->name('add.dealer.product');
    Route::get('/dealer/delete/product/{id}', 'DeleteDealerProduct')->name('delete.dealer.product');
     Route::get('/dealer/edit/dealerstock/{id}', 'EditDealerProduct')->name('edit.dealer.product');
     Route::post('/dealer/update/product','UpdateDealerProduct')->name('update.dealer.product');
     Route::get('/stock_details/{id}','DealerStockDetails')->name('product.details');

     Route::get('/dealer/dealer-stock-update/{id}/publish','UpdateShippingPublishStatus')->name('update.product.to.publish.status');

    // Route::get('/product_details/{id}', 'ProductDetails')->name('product.details');

});


//Dealer Product Category All Route
Route::controller(DealerProductCategoryController::class)->group(function(){
    Route::get('/all/dealer/product/category','AllDealerProductCategory')->name('all.dealer.product.category');
    Route::get('/add/dealer/product/category','AddDealerProductCategory')->name('add.dealer.product.category');
    Route::post('/store/dealer/product/category','StoreDealerProductCategory')->name('store.dealer.product.category');
    Route::get('/edit/dealer/product/category/{id}','EditDealerProductCategory')->name('edit.dealer.product.category');
    Route::post('/update/dealer/product/category/{id}','UpdateDealerProductCategory')->name('update.dealer.product.category');
    Route::get('/delete/dealer/product/category/{id}','DeleteDealerProductCategory')->name('delete.dealer.product.category');

});

//Product All Route
Route::controller(ProductController::class)->group(function () {
    Route::get('/admin/product', action: 'AllProduct')->name('all.product');
    Route::get('/add/product', action: 'AddProduct')->name('add.product');
    Route::get('/delete/product/{id}', 'DeleteProduct')->name('delete.product');
    Route::get('/edit/product/{id}', 'EditProduct')->name('edit.product');
    Route::get('/product_details/{id}', 'ProductDetails')->name('product.details');
    Route::post('/update/product','UpdateProduct')->name('update.product');
    // Store Product with Throttle Middleware
    Route::post('/store/product', 'StoreProduct')
        ->name('store.product')
        ->middleware('throttle:60,1');

    // // Update Product with Throttle Middleware
    // Route::post('/update/product', 'UpdateProduct')
    //     ->name('update.product')
    //     ->middleware('throttle:60,1');
});
// Stock All Route
Route::controller(StockController::class)->group(function(){
    Route::get('/my-stock','MyStock')->name('my.stock');
    Route::get('/product','HomeProduct')->name('home.product');
    //This is the e-storefront product details route
    Route::get('/stock_details/{id}','StockDetails')->name('stock.details');

});

// Order Centre All Route
Route::controller(ShippingController::class)->group(function(){
    Route::get('/dealer/my-shipping-orders','MyShippingOrders')->name('my.shipping.order');
    Route::get('/admin/shipping-orders',"AllShippingOrders")->name('all.shipping.order');
    Route::get('/admn/shipping-orders-update/{id}/approve','UpdateShippingApprovedStatus')->name('update.shipping.to.approve.status');
    Route::get('/admn/shipping-orders-update/{id}/delivery','UpdateShippingDeliveryStatus')->name('update.shipping.to.delivery.status');
    Route::get('/admn/shipping-orders-update/{id}/complete','UpdateShippingCompleteStatus')->name('update.shipping.to.complete.status');
    Route::get('/admn/shipping-orders-update/{id}/reject','UpdateShippingRejectStatus')->name('update.shipping.to.reject.status');
    Route::get('/dealers/shipping-orders',"AllDealerShippingOrders")->name('all.dealers.shipping.orders');

    Route::get('/dealers/shipping-orders-update/{id}/approve','UpdateDealerShippingApprovedStatus')->name('update.dealer.shipping.to.approve.status');
    Route::get('/dealers/shipping-orders-update/{id}/delivery','UpdateDealerShippingDeliveryStatus')->name('update.dealer.shipping.to.delivery.status');
    Route::get('/dealers/shipping-orders-update/{id}/complete','UpdateDealerShippingCompleteStatus')->name('update.dealer.shipping.to.complete.status');
    Route::get('/dealers/shipping-orders-update/{id}/reject','UpdateDealerShippingRejectStatus')->name('update.dealer.shipping.to.reject.status');
    Route::get('/order-items/{orderId}', 'getOrderItems')->name('admin.order.items');

});

//Dealer Order Centre




// Cart All Route
Route::controller(CartController::class)->group(function(){
    Route::post('/cart/add','addToCart')->name('cart.add');
    Route::post('/e-storefront/cart/add','GuestAddToCart')->name('guest.cart.add');

    Route::get('/cart/total', 'getCartTotal')->name('cart.total')->middleware('auth');
    Route::get('/guest/cart/total','GuestGetCartTotal')->name('guest.cart.total');
    // Route::post('/cart/update','updateToCart')->name('cart.update');
    // Route::post('/cart/remove','removeToCart')->name('cart.remove');
    // Route::get('/cart','indexCart')->name('cart.index');
    Route::get('/cart', 'getCart')->name('cart.summary');
    Route::get('/e-storefront/cart','getCartGuest')->name('guest.cart.summary');
    Route::get('/remove/cart/{id}','RemoveCart')->name('remove.cart');
    Route::patch('/cart/{cartItem}', 'updateCartItem')->name('cart.update');
    Route::post('/checkout', 'checkout')->name('checkout');
    Route::post('/payment', 'payment')->name('payment');

    Route::get('/success','CheckoutSuccessfully')->name('success.checkout');
    //Route::post('/empty-cart', 'CartController@emptyCart')->name('cart.empty');
    Route::post('/empty-cart','emptyCart')->name('cart.empty');

});

//Commission All Route
Route::controller(CommissionController::class)->group(function(){
    Route::get('/mycommission','MyCommission')->name('My.Commission');
    Route::get('/commissiontutorial','CommissionTutorial')->name('Commission.Tutorial');
    Route::get('/admin/dealercommission',"AllDealerCommission")->name('all.dealer.commission');
    Route::get('/admin/commission/setup',"showCommissionSetupForm")->name('admin.commission.setup');
    Route::post('/admin/commission/setup',"saveCommissionSetup")->name('admin.commission.save');

});


//Order Item All Route
Route::controller(OrderItemController::class)->group(function(){

});



//Order All Route
Route::controller(OrderController::class)->group(function(){

});



//Transactions All Route  (New)
Route::controller(TransactionsController::class)->group(function(){

});


//Transaction All Route (Old)
Route::controller(TransactionController::class)->group(function(){
    Route::post('/transactions','store')->name('transactions.store');
    Route::post('/transaction-update','update')->name('transactions.update');

});


//Product Category All Route
Route::controller(ProductCategoryController::class)->group(function(){
    Route::get('/all/product/category','AllProductCategory')->name('all.product.category');
    Route::get('/add/product/category','AddProductCategory')->name('add.product.category');
    Route::post('/store/product/category','StoreProductCategory')->name('store.product.category');
    Route::get('/edit/product/category/{id}','EditProductCategory')->name('edit.product.category');
    Route::post('/update/product/category/{id}','UpdateProductCategory')->name('update.product.category');
    Route::get('/delete/product/category/{id}','DeleteProductCategory')->name('delete.product.category');

});



// User All Route
Route::controller(UserController::class)->group(function(){
    Route::post('/referral/check','Check')->name('referral.check');



});


//Sales Performances All Route
Route::controller(SalesController::class)->group(function(){
    Route::get('/salesperformances','SalesPerformances')->name('Sales.Performances');

});


///EWallet All Route
Route::controller(EWalletController::class)->group(function(){

    Route::get('/mywallet','MyEWallet')->name('My.Wallet');
    Route::get('/wallelt-transaction-history','MyWalletHistory')->name('My.Wallet.History');

    // Protect top-up routes with feature toggle middleware
Route::get('/add/wallet', 'TopUpWallet')->name('add.wallet');
Route::post('/store/wallet', 'StoreWallet')->name('store.wallet');


    Route::get('/admin/dealerwallets', action: "AllDealerWallets")->name('all.dealer.wallets');
    Route::get('/admn/dealerwallets-update/{id}/approve','UpdateDealerWalletsApprovedStatus')->name('update.wallets.to.approve.status');
    Route::get('/admn/dealerwallets-update/{id}/reject','UpdateDealerWalletsRejectStatus')->name('update.wallets.to.reject.status');
});

Route::controller(FeatureToggleController::class)->prefix('admin')->middleware(['auth'])->name(config('routes.admin_name_prefix', 'admin.'))->group(function () {

    // List features (no middleware needed)
    Route::get('/features', 'index')->name('features.index');
    // Update feature toggle status (maybe protect with auth/admin middleware)
    Route::post('/features/{id}/update', 'update')->name('features.update');

});


Route::controller(FeatureManagementController::class)->middleware(['auth'])->group(function () {
    Route::get('/all/features', 'AllFeatures')->name('all.features');
    Route::get('/add/feature', 'AddFeature')->name('add.feature');
    Route::post('/store/feature', 'StoreFeature')->name('store.feature');
    Route::get('/edit/feature/{id}', 'EditFeature')->name('edit.feature');
    Route::post('/update/feature/{id}', 'UpdateFeature')->name('update.feature');
    Route::get('/delete/feature/{id}', 'DeleteFeature')->name('delete.feature');
});
Route::prefix('chatbot')->group(function () {
    // Handle POST requests using ChatbotController
    Route::post('/', [ChatbotController::class, 'handleRequest']);

    // Define resourceful routes for ChatbotController
    Route::get('/', [ChatbotController::class, 'index'])->name('chatbot.index');
    Route::get('/create', [ChatbotController::class, 'create'])->name('chatbot.create');
    Route::post('/store', [ChatbotController::class, 'store'])->name('chatbot.store');
    Route::get('/{id}', [ChatbotController::class, 'show'])->name('chatbot.show');
});

//Event All Route
Route::controller(EventController::class)->group(function(): void{
    Route::get('/all/events','AllEvent')->name('all.events');
    Route::post('/store/events','StoreEvents')->name('store.events');
    Route::get('/add/events', action: 'AddEvents')->name('add.events');
    Route::get('/edit/events/{id}','EditEvents')->name('edit.events');
    Route::post('/update/events/{id}','UpdateEvents')->name('update.events');
    Route::get('/delete/events/{id}','DeleteEvents')->name('delete.events');
    Route::get('/events','HomeEvents')->name('home.event');
    Route::get('/event/details/{id}','EventsDetails')->name('event.details');

});

// ✅ Trading Statistics Routes
Route::get('/statistics', [TradingStatisticsController::class, 'index'])->name('statistics.index');
Route::middleware(['auth'])->group(function (): void {
    Route::get('/trading/backtest', [TradingBacktestController::class, 'index'])->name('trading.backtest.index');
    Route::post('/trading/backtest/upload', [TradingBacktestController::class, 'upload'])->name('trading.backtest.upload');
});
// Trading Recording Classes
Route::middleware(['auth'])->controller(TradingRecordingController::class)->group(function (): void {
    Route::get('/admin/trading-recordings', 'adminIndex')->name('admin.trading.recordings.index');
    Route::get('/admin/trading-recordings/create', 'create')->name('admin.trading.recordings.create');
    Route::post('/admin/trading-recordings', 'store')->name('admin.trading.recordings.store');
    Route::get('/admin/trading-recordings/{recording}', 'show')->name('admin.trading.recordings.show');
    Route::get('/admin/trading-recordings/{recording}/edit', 'edit')->name('admin.trading.recordings.edit');
    Route::put('/admin/trading-recordings/{recording}', 'update')->name('admin.trading.recordings.update');
    Route::post('/admin/trading-recordings/{recording}/approve', 'approve')->name('admin.trading.recordings.approve');
    Route::get('/admin/trading-recordings/{recording}/materials/{material}/download', 'adminDownloadMaterial')->name('admin.trading.recordings.materials.download');
    Route::delete('/admin/trading-recordings/{recording}/materials/{material}', 'destroyMaterial')->name('admin.trading.recordings.materials.destroy');
    Route::delete('/admin/trading-recordings/{recording}', 'destroy')->name('admin.trading.recordings.destroy');

    Route::get('/trading/recordings', 'traderIndex')->name('trading.recordings.index');
    Route::post('/trading/recordings/{recording}/view', 'traderView')
        ->middleware('throttle:6,1')
        ->name('trading.recordings.view');
    Route::post('/trading/recordings/{recording}/download', 'traderDownload')
        ->middleware('throttle:6,1')
        ->name('trading.recordings.download');
    Route::post('/trading/recordings/{recording}/materials/{material}/download', 'traderDownloadMaterial')
        ->middleware('throttle:6,1')
        ->name('trading.recordings.materials.download');
});

// Trading Blog
Route::middleware(['auth'])->controller(TradingBlogController::class)->group(function (): void {
    Route::get('/admin/trading-blogs', 'adminIndex')->name('admin.trading.blogs.index');
    Route::get('/admin/trading-blogs/create', 'create')->name('admin.trading.blogs.create');
    Route::post('/admin/trading-blogs', 'store')->name('admin.trading.blogs.store');
    Route::get('/admin/trading-blogs/{blog}/edit', 'edit')->name('admin.trading.blogs.edit');
    Route::put('/admin/trading-blogs/{blog}', 'update')->name('admin.trading.blogs.update');
    Route::delete('/admin/trading-blogs/{blog}', 'destroy')->name('admin.trading.blogs.destroy');

    Route::get('/trading/blogs', 'index')->name('trading.blogs.index');
    Route::get('/trading/blogs/{blog:slug}', 'show')->name('trading.blogs.show');
});

// Marketing Resources
Route::middleware(['auth'])->controller(MarketingResourceController::class)->group(function (): void {
    Route::get('/admin/marketing-resources', 'adminIndex')->name('admin.marketing.resources.index');
    Route::get('/admin/marketing-resources/create', 'create')->name('admin.marketing.resources.create');
    Route::post('/admin/marketing-resources', 'store')->name('admin.marketing.resources.store');
    Route::get('/admin/marketing-resources/{resource}/edit', 'edit')->name('admin.marketing.resources.edit');
    Route::put('/admin/marketing-resources/{resource}', 'update')->name('admin.marketing.resources.update');
    Route::delete('/admin/marketing-resources/{resource}', 'destroy')->name('admin.marketing.resources.destroy');
    Route::get('/admin/marketing-resources/{resource}/download', 'adminDownload')->name('admin.marketing.resources.download');

    Route::get('/marketing-resources', 'leaderIndex')->name('marketing.resources.index');
    Route::post('/marketing-resources/{resource}/view', 'view')->middleware('throttle:8,1')->name('marketing.resources.view');
    Route::post('/marketing-resources/{resource}/download', 'download')->middleware('throttle:8,1')->name('marketing.resources.download');
});

// Support Tickets
Route::middleware(['auth'])->controller(SupportTicketController::class)->group(function (): void {
    Route::get('/support/tickets', 'index')->name('support.tickets.index');
    Route::get('/support/tickets/create', 'create')->name('support.tickets.create');
    Route::post('/support/tickets', 'store')->name('support.tickets.store');
    Route::get('/support/tickets/{ticket}', 'show')->name('support.tickets.show');
    Route::post('/support/tickets/{ticket}/reply', 'reply')->name('support.tickets.reply');
    Route::post('/support/tickets/{ticket}/close', 'close')->name('support.tickets.close');
    Route::get('/support/tickets/attachments/{attachment}/download', 'downloadAttachment')->name('support.tickets.attachments.download');
});

// Notifications
Route::middleware(['auth'])->controller(AppNotificationController::class)->group(function (): void {
    Route::get('/notifications', 'index')->name('notifications.index');
    Route::post('/notifications/read-all', 'markAllRead')->name('notifications.read_all');
    Route::post('/notifications/{notification}/read', 'markRead')->name('notifications.read');

    Route::get('/admin/notifications', 'adminIndex')->name('admin.notifications.index');
    Route::get('/admin/notifications/create', 'create')->name('admin.notifications.create');
    Route::post('/admin/notifications', 'store')->name('admin.notifications.store');
    Route::get('/admin/notifications/{notification}/edit', 'edit')->name('admin.notifications.edit');
    Route::put('/admin/notifications/{notification}', 'update')->name('admin.notifications.update');
    Route::delete('/admin/notifications/{notification}', 'destroy')->name('admin.notifications.destroy');
});

// Trading Appointments
Route::middleware(['auth'])->controller(TradingAppointmentController::class)->group(function (): void {
    Route::get('/admin/trading-appointments', 'adminIndex')->name('admin.trading.appointments.index');
    Route::post('/admin/trading-appointments/slots', 'storeSlot')->name('admin.trading.appointments.slots.store');
    Route::put('/admin/trading-appointments/slots/{slot}', 'updateSlot')->name('admin.trading.appointments.slots.update');
    Route::delete('/admin/trading-appointments/slots/{slot}', 'destroySlot')->name('admin.trading.appointments.slots.destroy');
    Route::post('/admin/trading-appointments/{appointment}/approve', 'approve')->name('admin.trading.appointments.approve');
    Route::post('/admin/trading-appointments/{appointment}/reject', 'reject')->name('admin.trading.appointments.reject');

    Route::get('/trading/appointments', 'index')->name('trading.appointments.index');
    Route::post('/trading/appointments/slots/{slot}/book', 'bookSlot')->name('trading.appointments.slots.book');
    Route::post('/trading/appointments/preferred', 'storePreferred')->name('trading.appointments.preferred.store');
    Route::post('/trading/appointments/{appointment}/cancel', 'cancel')->name('trading.appointments.cancel');
});

// Trading Examination
Route::middleware(['auth'])->controller(TradingExaminationController::class)->group(function (): void {
    Route::get('/trading/exams', 'index')->name('trading.exams.index');
    Route::post('/trading/exams/{attempt}/submit', 'submitDaily')->name('trading.exams.submit');

    Route::get('/admin/trading-exams', 'questionBank')->name('admin.trading.exams.index');
    Route::post('/admin/trading-exams/questions', 'storeQuestion')->name('admin.trading.exams.questions.store');
    Route::get('/admin/trading-exams/questions/{question}/edit', 'editQuestion')->name('admin.trading.exams.questions.edit');
    Route::put('/admin/trading-exams/questions/{question}', 'updateQuestion')->name('admin.trading.exams.questions.update');
    Route::delete('/admin/trading-exams/questions/{question}', 'destroyQuestion')->name('admin.trading.exams.questions.destroy');
    Route::post('/admin/trading-exams/questions/{question}/approve', 'approveQuestion')->name('admin.trading.exams.questions.approve');
    Route::post('/admin/trading-exams/questions/{question}/reject', 'rejectQuestion')->name('admin.trading.exams.questions.reject');
    Route::post('/admin/trading-exams/quota-requests', 'requestQuota')->name('admin.trading.exams.quota.request');
    Route::post('/admin/trading-exams/quota-requests/{quotaRequest}/approve', 'approveQuota')->name('admin.trading.exams.quota.approve');
    Route::post('/admin/trading-exams/quota-requests/{quotaRequest}/reject', 'rejectQuota')->name('admin.trading.exams.quota.reject');
});

Route::middleware(['auth'])->controller(TraderOnboardingController::class)->group(function (): void {
    Route::get('/trader-onboarding', 'show')->name('trader.onboarding.show');
    Route::post('/trader-onboarding', 'store')->name('trader.onboarding.store');

    Route::get('/admin/trader-onboarding', 'adminIndex')->name('admin.trader_onboarding.index');
    Route::post('/admin/trader-onboarding/{application}/approve', 'approve')->name('admin.trader_onboarding.approve');
    Route::post('/admin/trader-onboarding/{application}/reject', 'reject')->name('admin.trader_onboarding.reject');
    Route::post('/admin/trader-onboarding/{application}/reopen', 'reopen')->name('admin.trader_onboarding.reopen');
    Route::get('/admin/trader-onboarding/{application}/document', 'downloadDocument')->name('admin.trader_onboarding.download');
});

Route::middleware(['auth'])->controller(TradingPositionApplicationController::class)->group(function (): void {
    Route::get('/trading/positions', 'index')->name('trading.positions.index');
    Route::post('/trading/positions', 'store')->name('trading.positions.store');

    Route::get('/admin/trading-positions', 'adminIndex')->name('admin.trading_positions.index');
    Route::post('/admin/trading-positions/{application}/approve', 'approve')->name('admin.trading_positions.approve');
    Route::post('/admin/trading-positions/{application}/reject', 'reject')->name('admin.trading_positions.reject');
    Route::get('/admin/trading-positions/{application}/document', 'downloadDocument')->name('admin.trading_positions.download');
});

Route::middleware(['auth'])->controller(TraderReadinessChecklistController::class)->group(function (): void {
    Route::get('/trading/readiness-checklist', 'index')->name('trader.readiness.index');
    Route::post('/trading/readiness-checklist/reset', 'reset')->name('trader.readiness.reset');
    Route::post('/trading/readiness-checklist/{item}', 'update')->name('trader.readiness.update');
});

// ✅ Trading Journal Routes
Route::controller(TradingJournalController::class)->group(function (): void {
    
    // --- User Trading Journal ---
    Route::get('/all/trading-journals', 'AllTradingJournal')->name('all.trading.journals');
    Route::get('/add/trading-journal', 'AddTradingJournal')->name('add.trading.journal');
    Route::post('/store/trading-journal', 'StoreTradingJournal')->name('store.trading.journal');
    Route::get('/edit/trading-journal/{id}', 'EditTradingJournal')->name('edit.trading.journal');
    Route::post('/update/trading-journal/{id}', 'UpdateTradingJournal')->name('update.trading.journal');
    Route::get('/delete/trading-journal/{id}', 'DeleteTradingJournal')->name('delete.trading.journal');
    Route::get('/trading-journal/details/{id}', 'TradingJournalDetails')->name('trading.journal.details');
    Route::get('/trading-journal/download', 'exportTraderJournal')->name('trading-journal.export');
    Route::post('/trading-journal/deposit', 'StoreDeposit')->name('store.trading.deposit');


    // --- Excel Import / Export for Trading Journal ---
    Route::get('/trading-journal/template/download', 'DownloadTemplate')->name('download.trades.template'); // download Excel template
    Route::post('/trading-journal/import', 'ImportTrades')->name('import.trading.journal'); // import trades from Excel
    Route::post('/trading-journal/prop-firm-questions/{question}/answer', 'answerPropFirmQuestion')
        ->name('trading.propfirm.questions.answer');
});
// Funded Traders Routes
Route::middleware(['auth'])->controller(FundedTraderController::class)->group(function () {

    // View all funded traders (Pending, Approved, Rejected, Suspended)
    Route::get('/admin/funded-traders', 'AllFundedTrader')
        ->name('admin.funded_traders.index');

    // Approve funded trader (POST)
    Route::post('/admin/funded-traders/{id}/approve', 'approve')
        ->name('admin.funded_traders.approve');

    // Reject funded trader (POST)
    Route::post('/admin/funded-traders/{id}/reject', 'reject')
        ->name('admin.funded_traders.reject');

    // Suspend funded trader (POST)
    Route::post('/admin/funded-traders/{id}/suspend', 'suspend')
        ->name('admin.funded_traders.suspend');

    Route::post('/admin/funded-traders/{id}/questions', 'askQuestion')
        ->name('admin.funded_traders.questions.store');

    Route::post('/admin/funded-traders/questions/{question}/resolve', 'resolveQuestion')
        ->name('admin.funded_traders.questions.resolve');
});

Route::prefix('trading')->group(function () {
    Route::get('/leaderboard', [App\Http\Controllers\Trading\LeaderboardController::class, 'index'])
        ->name('trading.leaderboard.index');
});


// ✅ Traders Performance Routes
Route::controller(TradersPerformancesController::class)->group(function (): void {

    // --- User Performance ---
    Route::get('/all/traders-performance', 'AllTradersPerformance')->name('all.traders.performance');
    Route::get('/add/trader-performance', 'AddTraderPerformance')->name('add.trader.performance');
    Route::post('/store/trader-performance', 'StoreTraderPerformance')->name('store.trader.performance');
    Route::get('/edit/trader-performance/{id}', 'EditTraderPerformance')->name('edit.trader.performance');
    Route::post('/update/trader-performance/{id}', 'UpdateTraderPerformance')->name('update.trader.performance');
    Route::get('/delete/trader-performance/{id}', 'DeleteTraderPerformance')->name('delete.trader.performance');
    Route::get('/trader-performance/details/{id}', 'TraderPerformanceDetails')->name('trader.performance.details');
  // ✅ Export trader's journal (with filters)
Route::get('/trader-performance/export', 'AdminTradingJournalExport')->name('traders-performance.export');

        
        // View individual trader’s journals & performance
    Route::get('/traders/journals', 'tradersJournals')
        ->name('admin.trader.journals.index');

    // View all traders performance (paginated list)
    Route::get('/traders-performance', 'ViewAllTradersPerformance')
        ->name('admin.trader.performance.index');

});


Route::controller(LeaderboardController::class)->group(function (): void {

    // --- Leaderboard Main (RRR Ranking) ---
    Route::get('/leaderboard', 'index')->name('trading.leaderboard.index');

    // (Optional) Filter Leaderboard by Month/Year/User
    Route::get('/leaderboard/filter', 'filter')->name('trading.leaderboard.filter');

    // (Optional) Export Leaderboard (Excel/PDF etc.)
    Route::get('/leaderboard/export', 'export')->name('trading.leaderboard.export');

    // (Optional) View Individual Trader’s Leaderboard Entry
Route::get('/leaderboard/trader/{id}', 'showTrader')
    ->name('trading.leaderboard.showTrader');
});

// Trading Pair Routes
Route::controller(TradingPairController::class)->group(function (): void {
    Route::get('/all/trading-pairs', 'AllTradingPairs')->name('all.trading.pairs');
    Route::get('/add/trading-pair', 'AddTradingPair')->name('add.trading.pair');
    Route::post('/store/trading-pair', 'StoreTradingPair')->name('store.trading.pair');
    Route::get('/edit/trading-pair/{id}', 'EditTradingPair')->name('edit.trading.pair');
    Route::post('/update/trading-pair/{id}', 'UpdateTradingPair')->name('update.trading.pair');
    Route::get('/delete/trading-pair/{id}', 'DeleteTradingPair')->name('delete.trading.pair');

    // ✅ Import Excel route
    Route::post('/import/trading-pairs', 'ImportTradingPairs')->name('import.trading.pairs');
    // ✅ Template download
    Route::get('/download/trading-pairs-template', 'DownloadTemplate')->name('download.trading.pairs.template');
});

// Role Management Routes

Route::controller(RoleManagementController::class)->group(function (): void {
    Route::get('/all/roles', 'AllRoles')->name('all.roles');
    Route::get('/add/role', 'AddRole')->name('add.role');
    Route::post('/store/role', 'StoreRole')->name('store.role');
    Route::get('/edit/role/{id}', 'EditRole')->name('edit.role');
    Route::post('/update/role/{id}', 'UpdateRole')->name('update.role');
    Route::get('/delete/role/{id}', 'DeleteRole')->name('delete.role');
});



// Capital Routes
Route::controller(CapitalController::class)
    ->prefix('capital')
    ->middleware(['auth']) // Optional: only allow logged-in users
    ->group(function () {
        Route::get('/all', 'index')->name('capital.index');             // List all capital transactions
        Route::get('/add', 'create')->name('capital.create');           // (Optional) Show add form
        Route::post('/store', 'store')->name('capital.store');          // Handle deposit/withdraw
        Route::get('/edit/{id}', 'edit')->name('capital.edit');         // (Optional) Edit a capital entry
        Route::post('/update/{id}', 'update')->name('capital.update');  // (Optional) Update deposit/withdraw
        Route::get('/delete/{id}', 'destroy')->name('capital.delete');  // (Optional) Delete a capital entry
    });



// Dashboard All Route
Route::controller(DashboardController::class)->middleware(['auth'])->group(function(){
    Route::get('/all/dashboard/statistics','AllStatistics')->name('all.statistics');
    // Route::get('/all/dashboard/statistics/latestorder','LatestShippingOrders')->name('all.statistics');

});

Route::get('/all/dashboard/trading-statistics', [TradingStatisticsController::class, 'index'])->name('all.trading.statistics');



Route::get('/dashboard', [DashboardController::class, 'AllStatistics'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
        // ✅ New route for Connect Discord
    Route::get('/discord/connect', [ProfileController::class, 'connectDiscord'])->name('discord.connect');
});

require __DIR__.'/auth.php';
