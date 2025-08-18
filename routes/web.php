<?php

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
    Route::get('/all/agent','AllAgent')->name('all.agent.account');
    Route::get('/edit/agent/account/{id}','EditAgentAccount')->name('edit.agent.account');
    Route::post('/update/agent/account','UpdateAgent')->name('update.agent');
    Route::post('/update','AccountController@update')->name('updateAgentStatus');
    Route::get('/all/customer','AllCustomer')->name('all.customer.account');
    Route::get('/edit/customer/account/{id}','EditCustomerAccount')->name('edit.customer.account');
    Route::post('/update/customer/account','UpdateCustomer')->name('update.customer');
    Route::post('/update','AccountController@update')->name('updateCustomerStatus');
    Route::get('/all/admin','AllAdmin')->name('all.admin.account');
    Route::get('/edit/admin/account/{id}','EditAdminAccount')->name('edit.admin.account');
    Route::post('/update/admin/account','UpdateAdmin')->name('update.admin');
    Route::post('/update','AccountController@update')->name('updateAdminStatus');
    Route::post('/update/account','UpdateAccount')->name('update.account');
    Route::get('/edit/account/{id}','EditAccount')->name('edit.account');
    Route::post('/update','AccountController@update')->name('updateAccountStatus');
    Route::get('/delete/account/{id}','DeleteAccount')->name('delete.account');

     Route::get('/all/traders','AllTraders')->name('all.traders.account');
    Route::get('/edit/traders/account/{id}','EditTradersAccount')->name('edit.traders.account');
    Route::post('/update/traders/account','UpdateTraders')->name('update.traders');
    Route::post('/update','AccountController@update')->name('updateTradersStatus');

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

Route::controller(FeatureToggleController::class)->prefix('admin')->name(config('routes.admin_name_prefix', 'admin.'))->group(function () {

    // List features (no middleware needed)
    Route::get('/features', 'index')->name('features.index');
    // Update feature toggle status (maybe protect with auth/admin middleware)
    Route::post('/features/{id}/update', 'update')->name('features.update');

});


Route::controller(FeatureManagementController::class)->group(function () {
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


// Trading Pair Routes
Route::controller(TradingPairController::class)->group(function (): void {
    Route::get('/all/trading-pairs', 'AllTradingPairs')->name('all.trading.pairs');
    Route::get('/add/trading-pair', 'AddTradingPair')->name('add.trading.pair');
    Route::post('/store/trading-pair', 'StoreTradingPair')->name('store.trading.pair');
    Route::get('/edit/trading-pair/{id}', 'EditTradingPair')->name('edit.trading.pair');
    Route::post('/update/trading-pair/{id}', 'UpdateTradingPair')->name('update.trading.pair');
    Route::get('/delete/trading-pair/{id}', 'DeleteTradingPair')->name('delete.trading.pair');
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
Route::controller(DashboardController::class)->group(function(){
    Route::get('/all/dashboard/statistics','AllStatistics')->name('all.statistics');
    // Route::get('/all/dashboard/statistics/latestorder','LatestShippingOrders')->name('all.statistics');

});



Route::get('/dashboard', function () {
    return view('admin.dashboard.dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
