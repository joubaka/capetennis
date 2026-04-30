<?php

use App\Http\Controllers\Backend\AnnouncementController;
use App\Http\Controllers\Backend\CategoryEventController;
use App\Http\Controllers\Backend\ChartController;
use App\Http\Controllers\Backend\ClothingOrderController;
use App\Http\Controllers\Backend\ConvenorController;
use App\Http\Controllers\Backend\DashboardController;
use App\Http\Controllers\Backend\DrawController;
use App\Http\Controllers\Backend\EmailController;
use App\Http\Controllers\Backend\UserPlayerController;
use App\Http\Controllers\Backend\EventSettingsController;
use App\Http\Controllers\Backend\EvaluationController;
use App\Http\Controllers\Backend\EventAdminController;
use App\Http\Controllers\Backend\RoundRobinController;
use App\Http\Controllers\Backend\EventPhotoController;
use App\Http\Controllers\Backend\EventRegionController;
use App\Http\Controllers\Backend\EventVenueController;
use App\Http\Controllers\Backend\FileController;
use App\Http\Controllers\Backend\FixtureController;
use App\Http\Controllers\Backend\GoalController;
use App\Http\Controllers\Backend\HeadOfficeController;
use App\Http\Controllers\Backend\ImportExportController;
use App\Http\Controllers\Backend\LeagueController;
use App\Http\Controllers\Backend\ManageDrawController;
use App\Http\Controllers\Backend\NominateController;
use App\Http\Controllers\Backend\OrderController;
use App\Http\Controllers\Backend\PhotoController;
use App\Http\Controllers\Backend\PhotoFolderController;
use App\Http\Controllers\Backend\PlayerController;
use App\Http\Controllers\Backend\EventCategoryController;
use App\Http\Controllers\Backend\PracticeController;
use App\Http\Controllers\Backend\RankingController;
use App\Http\Controllers\Backend\RegionController;
use App\Http\Controllers\Backend\EventCategoryResultController;
use App\Http\Controllers\Backend\EventResultsController;
use App\Http\Controllers\Backend\RegionTeamController;
use App\Http\Controllers\Backend\RegistrationController;
use App\Http\Controllers\Backend\ResultController;
use App\Http\Controllers\Backend\EventAnnouncementController;
use App\Http\Controllers\Backend\ScheduleController;
use App\Http\Controllers\Backend\ScoreboardController;
use App\Http\Controllers\Backend\SeriesController;
use App\Http\Controllers\Backend\SettingsController;
use App\Http\Controllers\Backend\TeamController;
use App\Http\Controllers\Backend\TeamFixtureController;
use App\Http\Controllers\Backend\TeamScheduleController;
use App\Http\Controllers\Backend\TeamSelectionController as BackendTeamSelectionController;
use App\Http\Controllers\Backend\UserController;
use App\Http\Controllers\Backend\VenueController;
use App\Http\Controllers\Backend\WalletController;
use App\Http\Controllers\Backend\WalletTransactionController;
use App\Http\Controllers\Frontend\RegistrationWithdrawController;
use App\Http\Controllers\Frontend\RegistrationRefundController;
use App\Http\Controllers\Frontend\RegistrationPaymentController;
use App\Http\Controllers\Frontend\EventController;
use App\Http\Controllers\Frontend\FrontFixtureController;
use App\Http\Controllers\Frontend\HomeController;
use App\Http\Controllers\Frontend\PublicRoundRobinController;
use App\Http\Controllers\Backend\EventEntryController;
use App\Http\Controllers\Frontend\PhotoController as FrontendPhotoController;
use App\Http\Controllers\Frontend\PlayerController as FrontendPlayerController;
use App\Http\Controllers\Frontend\RegisterController;
use App\Http\Controllers\TeamSelectionController;
use App\Http\Controllers\Frontend\AgreementController as FrontendAgreementController;
use App\Http\Controllers\Backend\AgreementController as BackendAgreementController;
use App\Models\ClothingOrder;
use App\Models\Draw;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\DB;
use App\Models\Player;
use App\Http\Controllers\Backend\EventController as BackendEventController;
use App\Http\Controllers\Backend\EventTransactionController;
use App\Http\Controllers\Backend\SeriesRankingController;
// ✅ NEW: Region Clothing admin controller
use App\Http\Controllers\Backend\RegionClothingController;
use App\Http\Controllers\Frontend\TeamFixtureFrontendController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

$controller_path = 'App\Http\Controllers';

// Main Page Route
Route::get('/getplayers', [HomeController::class, 'get_players'])->name('get.players');
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/mergePlayers', [HomeController::class, 'mergePlayers'])->name('merge.players');
Route::get('/page-2', $controller_path . '\pages\Page2@index')->name('pages-page-2');
Route::get(
  '/events/ajax/home',
  [HomeController::class, 'homeEvents']
)->name('events.ajax.home');

// pages
Route::get('/pages/misc-error', $controller_path . '\pages\MiscError@index')->name('pages-misc-error');

//schedule controller
Route::post('schedule/create', [ScheduleController::class, 'create'])->name('schedule.create');
Route::get('schedule/save', [ScheduleController::class, 'save'])->name('schedule.save');
Route::get('schedule/update/time', [ScheduleController::class, 'updateFixtureSchedule'])->name('schedule.update.time');

// authentication
Route::get('/auth/login-basic', $controller_path . '\authentications\LoginBasic@index')->name('auth-login-basic');
Route::get('/auth/register-basic', $controller_path . '\authentications\RegisterBasic@index')->name('auth-register-basic');

Route::get('/home/get_events', [HomeController::class, 'get_events'])->name('home.events.get');

//register
Route::get('register/register/{id}', [RegisterController::class, 'register'])->middleware(['auth', 'agreement', 'profile.updated'])->name('register.register');
Route::get('register/search-players', [RegisterController::class, 'searchPlayers'])->middleware('auth')->name('register.search.players');
Route::post('register/player-details', [RegisterController::class, 'getPlayerDetails'])->middleware('auth')->name('register.player.details');
Route::post('register/update-player-details', [RegisterController::class, 'updatePlayerDetails'])->middleware('auth')->name('register.update.player.details');
Route::post('register/registerAdmin', [RegisterController::class, 'registerPlayerInCategoryFromAdmin'])->middleware('auth')->name('register.admin');
Route::post('register/payNowPayfast', [RegisterController::class, 'payNowPayfast'])->middleware(['auth', 'agreement', 'profile.updated'])->name('pay.now.payfast');
Route::post('register/payNowPayfastOrder', [RegisterController::class, 'payOrderPayfast'])->middleware(['auth', 'agreement', 'profile.updated'])->name('pay.order.payfast');

// Player Profile Update (frontend)
Route::middleware('auth')->group(function () {
  Route::get('/player/profiles/pending', [\App\Http\Controllers\Frontend\PlayerProfileController::class, 'pending'])->name('player.profiles.pending');
  Route::get('/player/{player}/profile/edit', [\App\Http\Controllers\Frontend\PlayerProfileController::class, 'edit'])->name('player.profile.edit');
  Route::put('/player/{player}/profile', [\App\Http\Controllers\Frontend\PlayerProfileController::class, 'update'])->name('player.profile.update');
  Route::post('/player/{player}/profile/confirm', [\App\Http\Controllers\Frontend\PlayerProfileController::class, 'confirm'])->name('player.profile.confirm');
  Route::get('/player/{player}/profile/status', [\App\Http\Controllers\Frontend\PlayerProfileController::class, 'status'])->name('player.profile.status');
  Route::delete('/player/{player}/remove', [\App\Http\Controllers\Frontend\PlayerProfileController::class, 'remove'])->name('player.profile.remove');
});

// Code of Conduct / Agreements (frontend)
Route::middleware('auth')->group(function () {
  Route::get('/agreements', [FrontendAgreementController::class, 'show'])->name('agreements.show');
  Route::post('/agreements/accept', [FrontendAgreementController::class, 'accept'])->name('agreements.accept');
  Route::post('/agreements/check', [FrontendAgreementController::class, 'check'])->name('agreements.check');
});
Route::get('/register/success/{order}', [RegisterController::class, 'registrationSuccess'])
  ->name('frontend.registration.success');
Route::post(
  '/registration/hybrid/pay',
  [RegistrationPaymentController::class, 'hybridPay']
)->middleware(['auth', 'agreement', 'profile.updated'])->name('registration.hybrid.pay');

Route::post(
  '/registration/hybrid/apply-wallet',
  [RegistrationPaymentController::class, 'applyWallet']
)->middleware('auth')->name('registration.hybrid.apply-wallet');

Route::get(
  '/registration/hybrid/complete/{orderId}',
  [RegistrationPaymentController::class, 'hybridComplete']
)->name('registration.hybrid.complete');

Route::get(
  '/registration/hybrid/cancel/{orderId}',
  [RegistrationPaymentController::class, 'hybridCancel']
)->name('registration.hybrid.cancel');

Route::get(
  '/team/checkout/{order}',
  [TeamController::class, 'checkout']
)->name('team.checkout')->middleware('auth');
Route::prefix('team')->group(function () {

  Route::post(
    '/hybrid/pay',
    [\App\Http\Controllers\Frontend\RegistrationPaymentController::class, 'teamHybridPay']
  )->name('team.hybrid.pay');

  Route::post(
    '/hybrid/complete/{order}',
    [\App\Http\Controllers\Frontend\RegistrationPaymentController::class, 'teamHybridComplete']
  )->name('team.hybrid.complete');

  Route::get(
    '/hybrid/cancel/{order}',
    [\App\Http\Controllers\Frontend\RegistrationPaymentController::class, 'teamHybridCancel']
  )->name('team.hybrid.cancel');

});

  // Team Fixtures — Admin HQ routes
Route::get('backend/team-fixtures/create', [TeamFixtureController::class, 'create'])
  ->name('backend.team-fixtures.create');

Route::post('backend/team-fixtures', [TeamFixtureController::class, 'store'])
  ->name('backend.team-fixtures.store');

Route::post('backend/team-fixtures/{team_fixture}/insert-score', [TeamFixtureController::class, 'insertScore'])
  ->name('backend.team-fixtures.insertScore');




Route::resource('reg', RegisterController::class);

//event
Route::get('events/success/{id}', [EventController::class, 'success'])->name('event.success');
Route::post('notify', [RegisterController::class, 'notify'])->name('notify');
Route::post('notifyClothing', [RegisterController::class, 'notifyClothing'])->name('notify.clothing');
Route::post('notify_order', [RegisterController::class, 'notify_order'])->name('notify_order');
Route::post('notify_team', [RegisterController::class, 'notify_team'])->name('notify.team');
Route::get('events/cancel', [EventController::class, 'cancel'])->name('event.cancel');
Route::get('events/ajax/userEvents/{id}', [EventController::class, 'userEventAjax'])->name('ajax.event.user');
Route::get('events/ajax/series', [RankingController::class, 'seriesAllAjax'])->name('ajax.series.all');
Route::resource('events', EventController::class);
Route::get('events/{event}/results', [EventController::class, 'results'])->name('events.results');
Route::post(
  '/registrations/{registration}/refund/process',
  [\App\Http\Controllers\Frontend\RegistrationRefundController::class, 'process']
)->middleware('auth')
  ->name('registrations.refund.process');
Route::post(
  '/registrations/{registration}/withdraw',
  [RegistrationWithdrawController::class, 'withdraw']
)
  ->middleware('auth')
  ->name('registrations.withdraw');

Route::get(
  '/registrations/{registration}/refund/choose',
  [\App\Http\Controllers\Frontend\RegistrationRefundController::class, 'choose']
)->middleware('auth')
  ->name('registrations.refund.choose');
Route::post(
  '/registrations/{registration}/refund/request',
  [\App\Http\Controllers\Frontend\RegistrationRefundController::class, 'store']
)->middleware('auth')
  ->name('registrations.refund.request');

Route::post(
  '/registrations/{entry}/move-category',
  [\App\Http\Controllers\Frontend\RegistrationCategoryMoveController::class, 'move']
)->middleware('auth')
  ->name('registrations.moveCategory');

// routes/web.php (admin section)




Route::middleware(['auth', 'role:super-user|admin'])
  ->prefix('admin/refunds')
  ->group(function () {

    Route::get(
      '/bank',
      [RegistrationRefundController::class, 'bankIndex']
    )->name('admin.registration.refunds.bank.index');

    Route::get(
      '/bank/{registration}',
      [RegistrationRefundController::class, 'bankShow']
    )->name('admin.registration.refunds.bank.show');

    Route::post(
      '/bank/{registration}/complete',
      [RegistrationRefundController::class, 'bankComplete']
    )->name('admin.registration.refunds.bank.complete');

    // Mark team bank refund as completed (admin)
    Route::post(
      '/bank/team/{order}/complete',
      [RegistrationRefundController::class, 'bankCompleteTeam']
    )->name('admin.registration.refunds.bank.complete.team');
  });




Route::middleware(['auth', 'role:super-user'])
  ->prefix('backend/refunds')
  ->name('admin.refunds.')
  ->group(function () {

    Route::get('bank', [
      App\Http\Controllers\Backend\BankRefundController::class,
      'index'
    ])->name('bank.index');

    Route::post('{registration}/complete', [
      App\Http\Controllers\Backend\BankRefundController::class,
      'complete'
    ])->name('bank.complete');
    
    // Mark a team refund as completed
    Route::post('team/{order}/complete', [
      App\Http\Controllers\Backend\BankRefundController::class,
      'completeTeam'
    ])->name('bank.complete.team');

    Route::get('{registration}/payfast-query', [
      App\Http\Controllers\Backend\BankRefundController::class,
      'queryPayfast'
    ])->name('bank.payfast-query');
  });



Route::middleware([
  'auth:sanctum',
  config('jetstream.auth_session'),
  'verified'
])->group(function () {
});

//backend
Route::prefix('backend')->middleware('auth')->group(function () {

  // Add these inside the backend/authenticated group (apply same middleware as other admin routes)
  Route::post('backend/users/{user}/add-role', [\App\Http\Controllers\Backend\UserController::class, 'addRole'])
    ->name('backend.users.addRole')
    ->middleware(['auth', 'role:super-user|admin']);

  Route::post('backend/users/{user}/remove-role', [\App\Http\Controllers\Backend\UserController::class, 'removeRole'])
    ->name('backend.users.removeRole')
    ->middleware(['auth', 'role:super-user|admin']);

  // Role management (create/delete roles)
  Route::post('backend/roles', [\App\Http\Controllers\Backend\RoleController::class, 'store'])
    ->name('backend.roles.store')
    ->middleware(['auth', 'role:super-user|admin']);

  Route::delete('backend/roles/{role}', [\App\Http\Controllers\Backend\RoleController::class, 'destroy'])
    ->name('backend.roles.destroy')
    ->middleware(['auth', 'role:super-user|admin']);

  // Code of Conduct / Agreements (admin)
  Route::prefix('agreements')->middleware('role:super-user|admin')->group(function () {
    Route::get('/', [BackendAgreementController::class, 'index'])->name('backend.agreements.index');
    Route::get('/create', [BackendAgreementController::class, 'create'])->name('backend.agreements.create');
    Route::post('/', [BackendAgreementController::class, 'store'])->name('backend.agreements.store');
    Route::get('/{agreement}', [BackendAgreementController::class, 'show'])->name('backend.agreements.show');
    Route::get('/{agreement}/edit', [BackendAgreementController::class, 'edit'])->name('backend.agreements.edit');
    Route::put('/{agreement}', [BackendAgreementController::class, 'update'])->name('backend.agreements.update');
    Route::post('/{agreement}/duplicate', [BackendAgreementController::class, 'duplicate'])->name('backend.agreements.duplicate');
    Route::post('/{agreement}/set-active', [BackendAgreementController::class, 'setActive'])->name('backend.agreements.setActive');
  });

  // Super Admin Dashboard
  Route::get('superadmin', [\App\Http\Controllers\Backend\SuperAdminController::class, 'index'])
    ->middleware('role:super-user')
    ->name('backend.superadmin.index');

  // Super Admin Financial Dashboard
  Route::middleware('role:super-user')->group(function () {
    Route::get('superadmin/finances', [\App\Http\Controllers\Backend\SuperAdminFinanceController::class, 'index'])
      ->name('superadmin.finances');

    Route::get('superadmin/finances/event/{event}', [\App\Http\Controllers\Backend\SuperAdminFinanceController::class, 'show'])
      ->name('superadmin.finances.event');

    Route::post('superadmin/finances/event/{event}/payout', [\App\Http\Controllers\Backend\SuperAdminFinanceController::class, 'storePayout'])
      ->name('superadmin.finances.payout.store');

    Route::delete('superadmin/finances/payout/{payout}', [\App\Http\Controllers\Backend\SuperAdminFinanceController::class, 'destroyPayout'])
      ->name('superadmin.finances.payout.destroy');

    Route::post('superadmin/finances/event/{event}/full-refund/registration/{registration}', [\App\Http\Controllers\Backend\SuperAdminFinanceController::class, 'storeFullRefund'])
      ->name('superadmin.finances.full-refund.registration');

    Route::post('superadmin/finances/event/{event}/full-refund/team-order/{order}', [\App\Http\Controllers\Backend\SuperAdminFinanceController::class, 'storeFullRefundTeam'])
      ->name('superadmin.finances.full-refund.team');

    Route::get('superadmin/orphaned-registrations', [\App\Http\Controllers\Backend\OrphanedRegistrationController::class, 'index'])
      ->name('superadmin.orphans.index');

    Route::post('superadmin/orphaned-registrations/{order}/repair', [\App\Http\Controllers\Backend\OrphanedRegistrationController::class, 'repair'])
      ->name('superadmin.orphans.repair');

    Route::delete('superadmin/orphaned-registrations/{order}/purge', [\App\Http\Controllers\Backend\OrphanedRegistrationController::class, 'purge'])
      ->name('superadmin.orphans.purge');

    Route::delete('superadmin/orphaned-registrations/{order}/delete-real', [\App\Http\Controllers\Backend\OrphanedRegistrationController::class, 'deleteReal'])
      ->name('superadmin.orphans.delete-real');

    // Super Admin Wallet CRUD
    Route::post('superadmin/wallets/users/{user}/transaction', [\App\Http\Controllers\Backend\SuperAdminWalletController::class, 'storeTransaction'])
      ->name('superadmin.wallets.transaction.store');

    Route::put('superadmin/wallets/transactions/{transaction}', [\App\Http\Controllers\Backend\SuperAdminWalletController::class, 'updateTransaction'])
      ->name('superadmin.wallets.transaction.update');

    Route::delete('superadmin/wallets/transactions/{transaction}', [\App\Http\Controllers\Backend\SuperAdminWalletController::class, 'destroyTransaction'])
      ->name('superadmin.wallets.transaction.destroy');

    Route::delete('superadmin/wallets/{wallet}', [\App\Http\Controllers\Backend\SuperAdminWalletController::class, 'destroyWallet'])
      ->name('superadmin.wallets.destroy');
  });


  Route::prefix('announcement')->group(function () {

    // List announcements for an event
    Route::get(
      'event/{event}',
      [EventAnnouncementController::class, 'index']
    )->name('admin.events.announcements');

    // Create announcement
    Route::post(
      'event/{event}',
      [EventAnnouncementController::class, 'store']
    )->name('admin.events.announcements.store');

    // Toggle hide / show (soft delete / restore)
    Route::patch(
      '{announcement}/toggle',
      [EventAnnouncementController::class, 'toggle']
    )->name('admin.announcements.toggle');

    // Fetch single announcement (edit)
    Route::get(
      '{announcement}',
      [EventAnnouncementController::class, 'show']
    )->name('admin.announcements.show');

    // Update announcement
    Route::patch(
      '{announcement}',
      [EventAnnouncementController::class, 'update']
    )->name('admin.announcements.update');

    // Delete announcement
    Route::delete(
      '{announcement}',
      [EventAnnouncementController::class, 'destroy']
    )->name('admin.announcements.destroy');
  });
  /*
 |--------------------------------------------------------------------------
 | EVENT ADMIN
 |--------------------------------------------------------------------------
 */
  Route::prefix('event')->group(function () {

 

    // 🔹 NEW: create single TEAM draw via HeadOfficeController
    Route::post('{event}/create-team-draw', [HeadOfficeController::class, 'createSingleDrawTeam'])
      ->name('headoffice.createSingleDraw.team');
  });

  Route::get(
    'team/available-players',
    [TeamController::class, 'availablePlayers']
  )->name('backend.team.availablePlayers');

  Route::post(
    'team/add-players',
    [TeamController::class, 'addPlayers']
  )->name('backend.team.addPlayers');
  Route::get(
    'team/roster/edit',
    [TeamController::class, 'editRoster']
  )->name('backend.team.roster.edit');

  Route::post(
    'team/roster/update',
    [TeamController::class, 'updateRoster']
  )->name('backend.team.roster.update');

  // Update no-profile player (name + surname only)
  Route::patch('team/noprofile/update/{id}', [TeamController::class, 'updateNoProfile'])
    ->name('backend.team.noprofile.update');



  Route::get('team-schedule/{draw}', [TeamFixtureController::class, 'schedulePage'])
    ->name('backend.team-schedule.page');

  ///new admin routes

  Route::get(
    'event/{event}/draws',
    [EventAdminController::class, 'draws']
  )->name('admin.events.draws');


  Route::get(
    'event/{event}/categories',
    [EventCategoryController::class, 'index']
  )->name('admin.events.categories');

  Route::delete(
    'event/category/{categoryEvent}',
    [EventCategoryController::class, 'destroy']
  )->name('admin.category.delete');

  Route::delete(
    'event/{event}/categories/cleanup',
    [EventCategoryController::class, 'cleanup']
  )->name('admin.categories.cleanup');


 
  Route::post(
    'event/{event}/categories/attach',
    [EventCategoryController::class, 'attach']
  )->name('admin.categories.attach');

  Route::post(
    'event/{event}/categories/create',
    [EventCategoryController::class, 'createAndAttach']
  )->name('admin.categories.create');

 

  //
  // Event Settings
  //

  Route::get(
    'event/{event}/settings',
    [EventSettingsController::class, 'index']
  )->name('admin.events.settings');

  Route::patch(
    'event/{event}/settings',
    [EventSettingsController::class, 'update']
  )->name('admin.events.settings.update');
  Route::patch(
    'event/category/{categoryEvent}/fee',
    [EventSettingsController::class, 'updateCategoryFee']
  )->name('admin.events.category-fee.update');
  Route::patch(
    'event/{event}/information',
    [EventController::class, 'updateInformation']
  )->name('admin.events.information.update');


  Route::post('events/{event}/settings/logo', [EventSettingsController::class, 'uploadLogo'])
    ->name('admin.events.settings.logo');

  /////////////////////////event transactions


Route::get(
  'event/{event}/transactions',
  [EventTransactionController::class, 'index']
)->name('admin.events.transactions');

/////////////////////////event finances (convenor)

Route::get(
  'event/{event}/finances',
  [\App\Http\Controllers\Backend\EventFinanceController::class, 'index']
)->name('admin.events.finances');

Route::post(
  'event/{event}/finances/expense',
  [\App\Http\Controllers\Backend\EventFinanceController::class, 'storeExpense']
)->name('admin.events.finances.expense.store');

Route::patch(
  'event/finances/expense/{expense}',
  [\App\Http\Controllers\Backend\EventFinanceController::class, 'updateExpense']
)->name('admin.events.finances.expense.update');

Route::delete(
  'event/finances/expense/{expense}',
  [\App\Http\Controllers\Backend\EventFinanceController::class, 'destroyExpense']
)->name('admin.events.finances.expense.destroy');

Route::post(
  'event/finances/expense/{expense}/approve',
  [\App\Http\Controllers\Backend\EventFinanceController::class, 'approveExpense']
)->name('admin.events.finances.expense.approve');

Route::post(
  'event/finances/expense/{expense}/reimburse',
  [\App\Http\Controllers\Backend\EventFinanceController::class, 'reimburseExpense']
)->name('admin.events.finances.expense.reimburse');

// Income items CRUD
Route::post(
  'event/{event}/finances/income',
  [\App\Http\Controllers\Backend\EventFinanceController::class, 'storeIncomeItem']
)->name('admin.events.finances.income.store');

Route::patch(
  'event/finances/income/{item}',
  [\App\Http\Controllers\Backend\EventFinanceController::class, 'updateIncomeItem']
)->name('admin.events.finances.income.update');

Route::delete(
  'event/finances/income/{item}',
  [\App\Http\Controllers\Backend\EventFinanceController::class, 'destroyIncomeItem']
)->name('admin.events.finances.income.destroy');


/////////////////result

  // Save final positions for a category (individual event)
// 🔹 View final results page (Blade)
  Route::get(
    'events/{event}/results/individual',
    [EventResultsController::class, 'individual']
  )->name('admin.events.results.individual');


  // 🔹 Save final positions for a category
// Save final positions for a category (individual event)
  Route::post(
    'events/{event}/categories/{categoryEvent}/results',
    [EventCategoryResultController::class, 'store']
  )->name('admin.events.categories.results.store');

  /////////////////////

  // =========================
  // EVENT ENTRIES (PAGE)
  // =========================

  Route::get(
    'event/{event}/entries',
    [EventEntryController::class, 'index']
  )->name('admin.events.entries.new');
  Route::get(
    '/category/{categoryEvent}/available-registrations',
    [EventEntryController::class, 'availableRegistrations']
  )->name('admin.category.availableRegistrations');

  Route::post(
    '/category/move/{entry}',
    [EventEntryController::class, 'movePlayer']
  )->name('admin.category.movePlayer');


  // =========================
  // CATEGORY ENTRY MANAGEMENT
  // =========================

  Route::post(
    'event/category/{categoryEvent}/lock',
    [EventEntryController::class, 'lock']
  )->name('admin.category.lock');

  Route::post(
    'event/category/{categoryEvent}/unlock',
    [EventEntryController::class, 'unlock']
  )->name('admin.category.unlock');

  Route::post(
    'event/category/{categoryEvent}/add-player',
    [EventEntryController::class, 'addPlayer']
  )->name('admin.category.addPlayer');

  Route::delete(
    'event/category/{categoryEvent}/remove-player/{registration}',
    [EventEntryController::class, 'removePlayer']
  )->name('admin.category.removePlayer');


  // =========================
  // EXPORTS
  // =========================

  Route::get(
    'event/{event}/entries/export',
    [EventEntryController::class, 'exportEvent']
  )->name('admin.events.entries.export');

  Route::get(
    'event/category/{categoryEvent}/entries/export',
    [EventEntryController::class, 'exportCategory']
  )->name('admin.category.entries.export');


  // =========================
  // EMAIL
  // =========================

  Route::post(
    'event/email',
    [EventEntryController::class, 'sendEmail']
  )->name('admin.events.email.send');




  //////////////////////////////

  Route::get('team-schedule/{draw}/data', [TeamFixtureController::class, 'scheduleData'])
    ->name('backend.team-schedule.data');

  Route::post('team-schedule/{draw}/save', [TeamFixtureController::class, 'scheduleSave'])
    ->name('backend.team-schedule.save');

  Route::post('team-schedule/{draw}/auto', [TeamFixtureController::class, 'scheduleAuto'])
    ->name('backend.team-schedule.auto');

  Route::post('draw/schedule/clear/{draw}', [TeamFixtureController::class, 'scheduleClear'])
    ->name('backend.draw.schedule.clear');

  Route::post('draw/schedule/reset/{draw}', [TeamFixtureController::class, 'scheduleReset'])
    ->name('backend.draw.schedule.reset');

  Route::post('draw/{draw}/rankvenues/save', [TeamFixtureController::class, 'saveRankVenues'])
    ->name('backend.draw.rankvenues.save');
  Route::post('draw/{draw}/venues', [DrawController::class, 'storeVenues'])
    ->name('backend.draw.venues.store');
  Route::get('draw/{draw}/venues/json', [DrawController::class, 'getVenues'])
    ->name('backend.draw.venues.json');


  // Individual schedule
// Individual schedule
  Route::get('individual-schedule/{draw}', [ScheduleController::class, 'schedulePage'])
    ->name('backend.individual-schedule.page');

  Route::get('individual-schedule/{draw}/data', [ScheduleController::class, 'scheduleData'])
    ->name('backend.individual-schedule.data');

  Route::post('individual-schedule/{draw}/save', [ScheduleController::class, 'saveFixture'])
    ->name('backend.individual-schedule.save');

  Route::post('individual-schedule/{draw}/auto', [ScheduleController::class, 'autoSchedule'])
    ->name('backend.individual-schedule.auto');

  Route::post('individual-schedule/{draw}/clear', [ScheduleController::class, 'clearSchedule'])
    ->name('backend.individual-schedule.clear');

  Route::post('individual-schedule/{draw}/reset', [ScheduleController::class, 'resetSchedule'])
    ->name('backend.individual-schedule.reset');


  // Cavaliers Trials auto-schedule (NEW)
  Route::post('cavaliers-trials/{draw}/auto', [ScheduleController::class, 'autoScheduleTrials'])
    ->name('backend.trials.auto');
  Route::delete(
    '/draw/{draw}/trials/reset',
    [ScheduleController::class, 'resetTrials']
  )
    ->name('backend.trials.reset');







  // routes/web.php
  Route::get('/backend/draw/{draw}/venues/edit', [DrawController::class, 'editVenues'])
    ->name('backend.draw.venues.edit');
  Route::patch('/teams/{id}/toggle-noprofile', [TeamController::class, 'toggleNoProfile'])
    ->name('backend.teams.toggle-noprofile');
  // Add inside your authenticated backend routes (prefix 'backend'):

  Route::post('backend/team-fixtures/replace-player', [\App\Http\Controllers\Backend\TeamFixtureController::class, 'replacePlayerInEvent'])
    ->name('backend.team-fixtures.replacePlayer')
    ->middleware(['auth', 'role:super-user|admin']);
  // Add this GET route (inside your backend prefix/middleware group) so admin can open the replace form.
// Place it near other backend/team-fixtures routes.

  Route::get('backend/team-fixtures/replace-player', [\App\Http\Controllers\Backend\TeamFixtureController::class, 'replacePlayerForm'])
    ->name('backend.team-fixtures.replacePlayerForm')
    ->middleware(['auth', 'role:super-user|admin']);

  // Add this route inside your backend prefix/middleware group (where other backend/team-fixtures routes live)
  Route::post('backend/team-fixtures/no-profile', [\App\Http\Controllers\Backend\TeamFixtureController::class, 'createNoProfile'])
    ->name('backend.team-fixtures.noProfile.create')
    ->middleware(['auth', 'role:super-user|admin']);

  // Add this route inside your backend prefix/middleware group (near other team-fixtures routes)

  Route::get('backend/team-fixtures/player-fixtures', [\App\Http\Controllers\Backend\TeamFixtureController::class, 'playerFixtures'])
    ->name('backend.team-fixtures.playerFixtures')
    ->middleware(['auth']);

  Route::name('backend.')->middleware('auth')->group(function () {
    Route::resource('team-fixtures', \App\Http\Controllers\Backend\TeamFixtureController::class)
      ->only(['index', 'show', 'edit', 'update', 'destroy'])
      ->names([
        'index' => 'team-fixtures.index',
        'show' => 'team-fixtures.show',
        'edit' => 'team-fixtures.edit',
        'update' => 'team-fixtures.update',
        'destroy' => 'team-fixtures.destroy',
      ]);
    Route::delete(
      'team-fixtures/{team_fixture}/result',
      [App\Http\Controllers\Backend\TeamFixtureController::class, 'destroyResult']
    )->name('team-fixtures.destroyResult');
    Route::put(
      'team-fixtures/{team_fixture}/players',
      [\App\Http\Controllers\Backend\TeamFixtureController::class, 'updatePlayers']
    )->name('team-fixtures.updatePlayers');

  });

  Route::get('roundrobin/{draw}/admin-scores', [RoundRobinController::class, 'adminScoresPage'])
    ->name('backend.roundrobin.admin.scores');

  // Store score for round-robin fixture
  Route::post('roundrobin/score/{fixture}', [RoundRobinController::class, 'saveScore'])
    ->name('backend.roundrobin.score.store');

  // Delete score for round-robin fixture
  Route::delete('roundrobin/score/{fixture}', [RoundRobinController::class, 'deleteScore'])
    ->name('backend.roundrobin.score.delete');

  // Show round-robin draw (backend)
  Route::get('draw/roundrobin/{draw}', [RoundRobinController::class, 'show'])
    ->name('backend.draw.roundrobin.show');

  // Round-robin bracket views (AJAX loaded)
  Route::get('draw/{draw}/main-bracket', [RoundRobinController::class, 'mainBracket'])
    ->name('backend.draw.main-bracket');

  Route::post('draw/{draw}/generate-main-bracket', [RoundRobinController::class, 'generateMainBracket'])
    ->name('backend.draw.generate-main-bracket');

  Route::get('draw/{draw}/plate-bracket', [RoundRobinController::class, 'plateBracket'])
    ->name('backend.draw.plate-bracket');

  Route::post('draw/{draw}/generate-second-third-bracket', [RoundRobinController::class, 'generateSecondThirdBracket'])
    ->name('backend.draw.generate-second-third-bracket');

  //wallet
  Route::get('/wallet/{id}/transaction/create', [WalletTransactionController::class, 'create'])->name('transaction.create');
  Route::get('/wallet/{id}', [WalletController::class, 'show'])->name('wallet.show');
  Route::post('/wallet/{id}/transaction', [WalletTransactionController::class, 'store'])->name('wallet.transaction.store');

  //event
  Route::post('event/saveCategories', [BackendEventController::class, 'saveCategories'])->name('save.categories');
  Route::get('event/getEventCategories/{id}', [BackendEventController::class, 'getEventCategories'])->name('get.event.categories');
  Route::get('/event/{event}/transactions/download-pdf', [BackendEventController::class, 'downloadTransactionsPDF'])->name('transactions.pdf');
  Route::post('/event/saveTeams', [BackendEventController::class, 'saveTeams'])
    ->name('backend.event.saveTeams');

  //Photo
  Route::post('photo/moveSelected', [PhotoController::class, 'moveSelected'])->name('move.selected.photos');
  Route::post('photo/deleteSelected', [PhotoController::class, 'deleteSelected'])->name('delete.elected.photos');
  Route::resource('photo', PhotoController::class);

  //PhotoFolder
  Route::resource('photoFolder', PhotoFolderController::class);

  // Event Photos
  Route::resource('eventPhoto', EventPhotoController::class);

  //dashboard
  Route::get('dashboard', [DashboardController::class, 'dashboard'])->name('dashboard');

  //user
  Route::get('user/addRole/{id}', [UserController::class, 'addRole'])->name('user.add.role');
  Route::get('user/removeRole/{id}', [UserController::class, 'removeRole'])->name('user.remove.role');
  Route::resource('user', UserController::class);

  Route::get('export-clothing-orders-excel/{id}', [ClothingOrderController::class, 'exportExcel'])->name('export.excel.clothing');
  Route::get('clothingOrder/exportClothingOrdersPdf/{id}', [ClothingOrderController::class, 'exportPdf'])->name('export.pdf.clothing.order');
  Route::get('clothingOrder/showRegionClothes/{id}', [ClothingOrderController::class, 'showRegionClothing'])->name('region.clothing.order');
  Route::patch('/region/{region}/clothing-order', [ClothingOrderController::class, 'toggleClothingOrder'])
    ->name('backend.region.clothing.toggle');

  Route::resource('clothingOrder', ClothingOrderController::class);

  // region
  Route::post('region/getRegionClothingItems', [RegionController::class, 'getRegionClothingItems'])->name('get.region.clothing.items');
  Route::resource('region', RegionController::class);

  // ✅ Region Clothing Management (items, prices, sizes per region)
  Route::get('region/{region}/clothing', [RegionClothingController::class, 'edit'])
    ->name('backend.region.clothing.edit');

  // Items
  Route::post('region/{region}/clothing/items', [RegionClothingController::class, 'storeItem'])
    ->name('backend.region.clothing.items.store');
  Route::patch('region/{region}/clothing/items/bulk', [RegionClothingController::class, 'bulkUpdate'])
    ->name('backend.region.clothing.items.bulkUpdate');
  Route::delete('region/{region}/clothing/items/{item}', [RegionClothingController::class, 'destroyItem'])
    ->name('backend.region.clothing.items.destroy');

  // Sizes
  Route::post('region/{region}/clothing/{item}/sizes', [RegionClothingController::class, 'storeSize'])
    ->name('backend.region.clothing.sizes.store');
  Route::delete('region/{region}/clothing/{item}/sizes/{size}', [RegionClothingController::class, 'destroySize'])
    ->name('backend.region.clothing.sizes.destroy');
  // ✅ Region Clothing Orders view (list & export orders per region)
  Route::get('region/{region}/clothing/orders', [RegionClothingController::class, 'orders'])
    ->name('backend.region.clothing.orders');

  //event region
  Route::resource('eventRegion', EventRegionController::class);

  //order
  Route::resource('order', OrderController::class);

  Route::post('/event/{event}/preview-team-draw', [HeadOfficeController::class, 'previewSingleDrawTeam'])
     ->name('headoffice.previewTeamDraw');

  // Team event scoreboard (admin/backend view)
  Route::get('event/{event}/team-scoreboard', [ScoreboardController::class, 'showScoreboard'])
    ->name('backend.scoreboard.team.show');



  //email
  // 📧 EmailController AJAX data routes
  Route::get('email/players/{event}', [EmailController::class, 'getPlayers'])->name('backend.email.players');
  Route::get('email/teams/{event}', [EmailController::class, 'getTeams'])->name('backend.email.teams');
  Route::get('email/regions/{event}', [EmailController::class, 'getRegions'])->name('backend.email.regions');
  // 🟠 Unregistered players (email sending)
  Route::post('email/send-unregistered-event', [EmailController::class, 'sendToAllUnregisteredInEvent'])
    ->name('backend.email.sendUnregisteredEvent');

  Route::post('email/send-unregistered-region', [EmailController::class, 'sendToUnregisteredInRegion'])
    ->name('backend.email.sendUnregisteredRegion');



  //team



  Route::post('team/publishTeam/{id}', [TeamController::class, 'publishTeam'])->name('publish.team');
  Route::post('team/category/change/{id}', [TeamController::class, 'changeCategory'])->name('team.change.category');
  Route::get('team/payment/{team}/{player}/{event}', [TeamController::class, 'team_payment_payfast'])->name('team.payment.payfast');
  Route::post('team/orderPlayerList', [TeamController::class, 'order_player_list'])->name('team.order.player.list');
  Route::post('team/insertPlayer', [TeamController::class, 'insertPlayer'])->name('team.insert.player');
  Route::get('team/import/view', [TeamController::class, 'importView'])->name('team.import.view');
  Route::post('team/import/action', [TeamController::class, 'importNoProfile'])->name('backend.team.import.no.profile');
  Route::get('team/import/template', [TeamController::class, 'downloadTemplate'])
    ->name('team.import.no.profile.template');
  Route::get('team/{team}/players-table', [TeamController::class, 'teamPlayersTable']);

  Route::post('team/change/payStatus', [TeamController::class, 'changePayStatus'])->name('team.change.pay.status');
  Route::post('team/addToRegion', [TeamController::class, 'addToRegion'])->name('team.addToRegion');
  Route::post('/team/replacePlayer', [TeamController::class, 'replacePlayer'])
    ->name('backend.team.replace.player');
  
  // Team player refund routes
  Route::get('/team/{team}/player/{player}/{event}/refund/choose', [\App\Http\Controllers\Frontend\TeamPlayerWithdrawController::class, 'chooseRefund'])
    ->name('team.player.refund.choose')->middleware('auth');

  Route::post('/team/{team}/player/{player}/{event}/refund/request', [\App\Http\Controllers\Frontend\TeamPlayerWithdrawController::class, 'storeRefund'])
    ->name('team.player.refund.request')->middleware('auth');

  Route::get(
    '/team/player/replace-form',
    [TeamController::class, 'replaceForm']
  )->name('backend.team.player.replace.form');


  Route::resource('team', TeamController::class);

  //fixtures
  Route::get('fixture/pdf/create', [FixtureController::class, 'fixtures_create_pdf'])->name('fixture.create.pdf');
  Route::get('fixture/pdf/create/venue', [FixtureController::class, 'fixtures_create_pdf_venue'])->name('fixture.create.pdf.venue');
  Route::get('fixture/insertResult', [FixtureController::class, 'insertResult'])->name('draw.insert.result');
  Route::get('fixture/updateResult', [FixtureController::class, 'updateResult'])->name('draw.update.result');
  Route::post('fixture/deleteResult/{id}', [FixtureController::class, 'deleteResult'])->name('draw.delete.result');
  Route::post('fixture/deleteIndResult/{id}', [FixtureController::class, 'deleteIndResult'])->name('draw.delete.ind.result');
  Route::get('fixture/update/player/names/{id}', [FixtureController::class, 'updatePlayersNames'])->name('update-player-names');

  Route::get('fixture/ajax/{id}', [FixtureController::class, 'ajax'])->name('fixture.ajax');
  Route::get('fixture/rounds', [FixtureController::class, 'rounds'])->name('fixture.rounds');
  Route::get('fixture/ties', [FixtureController::class, 'ties'])->name('fixture.ties');
  Route::get('fixture/updatePlayers', [FixtureController::class, 'updatePlayer'])->name('fixture.update.players');
  Route::post('fixtures/create/auto/{draw_id}', [FixtureController::class, 'autoScheduleFixtures'])->name('fixtures.auto.schedule');
  Route::resource('fixture', FixtureController::class);
  Route::get('/nomination/players/category/{id}', [\App\Http\Controllers\backend\NominateController::class, 'playersForCategory']);
  Route::get(
    '/admin/headoffice/{event}/venue/{venue}',
    [\App\Http\Controllers\Backend\HeadOfficeController::class, 'venueFixtures']
  )->name('headoffice.venue.fixtures');
  // nominations
  Route::get('nomination/players/category/{id}', [NominateController::class, 'nominationInCategory'])->name('nomination.category.players');
  Route::post('nomination/publish/toggle/{id}', [NominateController::class, 'togglePublish'])->name('nomination.publish.toggle');
  // 🟢 NEW: get already selected players for category (for Select2 preselect)
  Route::get('nomination/selected/{categoryId}', [NominateController::class, 'getSelected'])
    ->name('nomination.selected');
  Route::post('nomination/save', [NominateController::class, 'save']);
  Route::get('nominations/partial/{id}', [NominateController::class, 'partialTable']);
  Route::patch('nomination/publish/{id}', [NominateController::class, 'togglePublish'])
    ->name('backend.nomination.publish');
  Route::post('/nomination/remove', [NominateController::class, 'remove'])
    ->name('backend.nomination.remove');


  Route::resource('nominate', NominateController::class);

  //eventAdmin
  Route::post('eventAdmin/eventCategory/data', [EventAdminController::class, 'getEventCategoryData'])->name('get.event.category.data');
  Route::get('eventAdmin/main/{id}', [EventAdminController::class, 'main'])->name('event.admin.main');
  Route::resource('eventAdmin', EventAdminController::class);

  //schedule
  Route::resource('teamSchedule', TeamScheduleController::class);

  //venue
  Route::get('venue/list', [VenueController::class, 'venue_list'])->name('venue.list');
  Route::get('venue/save/draw/venue', [VenueController::class, 'saveDrawVenues'])->name('save.draw.venues');
  Route::resource('venue', VenueController::class);

  //eventVenue
  Route::resource('eventVenue', EventVenueController::class);

  //headOffice
  Route::post('headOffice/update/region/order', [HeadOfficeController::class, 'updateRegionOrder'])->name('update.region.order');
  Route::post('headOffice/create/team/fixtures', [HeadOfficeController::class, 'createFormatFixturesTeam'])->name('create.team.fixtures');



  Route::post('headOffice/createFixtures/{event}', [EventAdminController::class, 'generateFixtures'])
    ->name('headoffice.createFixtures');



  Route::post('/event/{event}/create-individual-draw', [EventAdminController::class, 'createIndividualDraw'])
    ->name('headoffice.createSingleDraw');

  Route::get('/event/{event}/print-draws-data', [HeadOfficeController::class, 'printDrawsData'])
    ->name('headoffice.printDrawsData');

  Route::get('/event/{event}/print-draws-pdf', [HeadOfficeController::class, 'printDrawsPdf'])
    ->name('headoffice.printDrawsPdf');


  Route::post('/backend/headoffice/recreateFixtures/{draw}', [TeamFixtureController::class, 'recreateFixturesForDraw'])
    ->name('headoffice.recreateFixturesForDraw');

  Route::post(
    'draw/{draw}/save-groups',
    [RoundRobinController::class, 'saveGroups']
  )->name('backend.draw.save-groups');

  Route::post(
    'draw/{draw}/regenerate-rr',
    [RoundRobinController::class, 'regenerateRR']
  )->name('backend.draw.regenerate-rr');

  Route::post(
    'draw/{draw}/toggle-lock',
    [RoundRobinController::class, 'toggleLock']
  )->name('backend.draw.toggle-lock');

  // Draw settings update (used by settings tab)
  Route::post('draw/{draw}/settings', [\App\Http\Controllers\Backend\ManageDrawController::class, 'updateSettings'])
    ->name('backend.draw.update-settings');

  // Playoff configuration update
  Route::post('draw/{draw}/playoff-config', [\App\Http\Controllers\Backend\ManageDrawController::class, 'updatePlayoffConfig'])
    ->name('backend.draw.update-playoff-config');

  // Draw notes/rules update
  Route::post('draw/{draw}/notes', [\App\Http\Controllers\Backend\ManageDrawController::class, 'updateNotes'])
    ->name('backend.draw.update-notes');

  // Generate playoff brackets from RR standings
  Route::post('draw/{draw}/generate-playoffs', [\App\Http\Controllers\Backend\ManageDrawController::class, 'generatePlayoffBrackets'])
    ->name('backend.draw.generate-playoffs');

  // Get playoff brackets data for display
  Route::get('draw/{draw}/playoff-brackets', [\App\Http\Controllers\Backend\ManageDrawController::class, 'getPlayoffBrackets'])
    ->name('backend.draw.playoff-brackets');

  Route::post(
    '/event/{event}/import-teams',
    [EventAdminController::class, 'importTeamCategoryEvents']
  );

  Route::post(
    '/fixture/{fixture}/save-score',
    [RoundRobinController::class, 'saveScore']
  )->name('rr.fixture.saveScore');



  Route::resource('headOffice', HeadOfficeController::class);

  //registration

  Route::post('registration/delete', [RegistrationController::class, 'delete'])->name('registration.delete');
  Route::post('registration/addPlayerToCategory', [RegistrationController::class, 'addPlayerToCategory'])
    ->name('registration.addPlayerToCategory');

  Route::resource('registration', RegistrationController::class);
  // Withdraw player from event
 




  //convenor
  Route::get('convenor/search-users', [ConvenorController::class, 'searchUsers'])->name('convenor.search-users');
  Route::resource('convenor', ConvenorController::class);

  /*
   |-----------------------------------------------------------------------
   | RANKING (UPDATED)
   |-----------------------------------------------------------------------
   */
  Route::prefix('ranking')->name('ranking.')->group(function () {
      Route::post(
        'lists/{rankingList}/add-category',
        [RankingController::class, 'add_category_to_ranklist']
      )->name('lists.add-category');

      Route::post(
        'lists/{rankingList}/order',
        [RankingController::class, 'update_ranklist_order']
      )->name('lists.order');

    Route::delete('/ranking/lists/{list}/remove-category', [RankingController::class, 'removeCategory'])
      ->name('backend.ranking.lists.remove-category');
    /*
    |--------------------------------------------------------------------------
    | Frontend leaderboard
    |--------------------------------------------------------------------------
    */
    Route::get(
      'frontend/show/{series}',
      [RankingController::class, 'ranking_frontend_show']
    )->name('frontend.show');


    /*
    |--------------------------------------------------------------------------
    | Series settings (SERIES responsibility)
    |--------------------------------------------------------------------------
    */
    Route::get(
      'series/{series}/settings',
      [SeriesController::class, 'settings']
    )->name('series.settings');

    Route::post(
      'series/{series}/settings',
      [SeriesController::class, 'update']
    )->name('series.update');


    /*
    |--------------------------------------------------------------------------
    | Points template (RANKING responsibility)
    |--------------------------------------------------------------------------
    */
    Route::get(
      'series/{series}/points',
      [RankingController::class, 'points']
    )->name('points');

    Route::post(
      'series/{series}/points',
      [RankingController::class, 'updatePoints']
    )->name('points.update');


    Route::get('series/{series}/list', [SeriesRankingController::class, 'index'])
      ->name('series.list');

    Route::get('series/{series}/audit', [SeriesRankingController::class, 'audit'])
      ->name('series.audit');

    Route::post('series/{series}/rebuild', [SeriesRankingController::class, 'rebuild'])
      ->name('series.rebuild');


    /*
    |--------------------------------------------------------------------------
    | Ranking calculation
    |--------------------------------------------------------------------------
    */
    Route::post(
      'calculate/{series}',
      [RankingController::class, 'calculate']
    )->name('calculate');


    /*
    |--------------------------------------------------------------------------
    | Player ranking details
    |--------------------------------------------------------------------------
    */
    Route::get(
      'details/{player}',
      [RankingController::class, 'details']
    )->name('details');


    /*
    |--------------------------------------------------------------------------
    | Ranking lists management
    |--------------------------------------------------------------------------
    */
    Route::post(
      'lists/{series}',
      [RankingController::class, 'add_ranking_list']
    )->name('lists.store');

    Route::post(
      'lists/category/add/{rankingList}',
      [RankingController::class, 'add_category_to_ranklist']
    )->name('lists.addCategory');

    Route::post(
      'lists/category/delete/{rankingList}',
      [RankingController::class, 'delete_category_from_ranklist']
    )->name('lists.deleteCategory');

  });

  // ❌ REMOVE THIS if not strictly required
// Route::resource('ranking', RankingController::class);



  //draw
  Route::post('draw/publishToggle/{id}', [DrawController::class, 'togglePublish'])->name('draw.toggle.publish');
  Route::post('draw/publishToggleSchedule/{id}', [DrawController::class, 'togglePublishSchedule'])->name('draw.toggle.publish.schedule');
  Route::post('draw/registration/addPlayer/{id}', [DrawController::class, 'add_draw_registration'])->name('add.draw.registration');
  Route::post('draw/registration/removePlayer/{id}', [DrawController::class, 'remove_draw_registration'])->name('remove.draw.registration');
  Route::post('draw/changeSeed/{id}', [DrawController::class, 'change_seed'])->name('change.seed');
  Route::post('draw/changeAllSeed', [DrawController::class, 'changeAllSeeds'])->name('change.all.seeds');
  Route::post('draw/registration/addCategory/{id}', [DrawController::class, 'add_draw_registration_category'])->name('add.draw.registration.category');
  Route::get('draw/index/{id}', [DrawController::class, 'draw_index'])->name('event.draw.index');
  Route::get('draw/getPdf/{id}', [DrawController::class, 'getPDF'])->name('event.draw.get.pdf');
  Route::get('draw/ajax/venues/{eventId}', [DrawController::class, 'getAjaxVenues'])->name('get.ajax.venues');
  Route::get('draw/venue/add/{drawId}', [DrawController::class, 'addVenueDraw'])->name('add.venue.draw');
  Route::get('draw/venue/remove/{drawId}', [DrawController::class, 'removeVenueDraw'])->name('remove.venue.draw');

  Route::post('draw/{event}/create', [DrawController::class, 'createDraw'])
    ->name('backend.draw.create');




  Route::get('/draws/{drawid}/players', [DrawController::class, 'getDrawPlayers']);
  Route::post('/draws/import-category', [DrawController::class, 'importFromCategory']);
  Route::post('/draws/add-player', [DrawController::class, 'addPlayerDraw']);
  Route::get('/admin/draws/{id}/preview', [DrawController::class, 'getDrawPreview']);

  Route::post('/admin/draws/remove-player', [DrawController::class, 'removePlayer'])->name('admin.draws.removePlayer');
  Route::get('/draws/clear-all-players', [DrawController::class, 'clearPlayers'])->name('draws.clear-players');
  Route::get('/draws/box-matrix/{draw}/{box}', [DrawController::class, 'getBoxMatrix'])->name('draw.box.matrix');
  Route::get('/draws/box-matrix/{draw}', [DrawController::class, 'boxMatrix'])->name('draw.box.matrix.single');
  Route::get('/draw/{draw}/group-standings', [DrawController::class, 'groupStandings']);
  Route::get('/draw/{draw}/preview', [DrawController::class, 'drawPreview']);
  Route::get('/draw/{draw}/json', [DrawController::class, 'json'])->name('json');

  // new draw stuff
  Route::get('/admin/draws/{id}', [DrawController::class, 'showBracket'])->name('draws.show');
  Route::get('/admin/draws/{id}/manage', [DrawController::class, 'manage'])->name('draws.manage');
  Route::post('/admin/draws/{id}/players/update', [DrawController::class, 'updatePlayers'])->name('draws.players.update');
  Route::post('/admin/draws/{id}/update', [DrawController::class, 'update'])->name('draws.update');
  Route::get('/admin/draws/{id}/settings', [DrawController::class, 'settings'])->name('draws.settings');

  Route::post('/admin/draws/{draw}/players', [DrawController::class, 'addPlayers'])->name('draws.addPlayers');

  Route::post('/category-events/{categoryEvent}/generate-draw', [DrawController::class, 'generate'])->name('draws.generate');
  Route::post('/draw/{draw}/add-player', [DrawController::class, 'addPlayer'])->name('draw.addPlayer');

  Route::delete('/draws/{id}', [DrawController::class, 'destroy'])->name('draws.destroy');
  Route::post('/draws/generate-from-modal', [DrawController::class, 'generateFromModal'])->name('draws.generate.from.modal');

  Route::post('/admin/draws/add-category-players', [DrawController::class, 'addCategoryPlayers'])->name('admin.draws.addCategoryPlayers');
  Route::post('/admin/draws/add-player', [DrawController::class, 'addPlayerToDraw'])->name('admin.draws.addPlayerToDraw');

  Route::get('/admin/draws/{draw}/players', [DrawController::class, 'getDrawPlayers'])->name('get.draws.players');
  Route::post('/admin/draws/{id}/update-seeds', [DrawController::class, 'updateSeeds']);
  Route::post('/admin/draws/{id}/assign-boxes', [DrawController::class, 'assignBoxNumbers']);

  Route::get('/draws/{id}/players', [DrawController::class, 'players'])->name('draws.players');
  Route::get('/admin/draws/{id}/split-boxes', [DrawController::class, 'getSplitBoxes'])->name('admin.draws.split-boxes');
  Route::post('/admin/draws/{draw}/generate-roundrobin', [DrawController::class, 'generateRoundRobinFixtures'])->name('admin.draws.generateRoundRobin');

  //managedrawcontroller
  Route::put('/admin/draws/{draw}/update-settings', [DrawController::class, 'updateSettings'])->name('admin.draws.update-settings');
  Route::resource('draw', DrawController::class)->except(['destroy']);

  //category Events
  Route::get('/admin/category/{category_event_id}/manage', [CategoryEventController::class, 'manage'])->name('category.manage');
  Route::get('/admin/category/{categoryEvent}/entries', [EventAdminController::class, 'showEntries'])->name('category.entries');

  // Admin manual withdrawal for individual registration
  Route::post(
    '/admin/category-registration/{registration}/withdraw',
    [CategoryEventController::class, 'withdraw']
  )->name('admin.category.registration.withdraw');

  // Admin refund chooser (after admin withdrawal)
  Route::get(
    '/admin/event/{event}/registration/{registration}/refund',
    [\App\Http\Controllers\Backend\AdminRegistrationRefundController::class, 'chooseRefund']
  )->name('admin.registration.refund.choose');

  Route::post(
    '/admin/event/{event}/registration/{registration}/refund',
    [\App\Http\Controllers\Backend\AdminRegistrationRefundController::class, 'storeRefund']
  )->name('admin.registration.refund.store');

  Route::delete(
    '/admin/event/{event}/registration/{registration}/refund/cancel',
    [\App\Http\Controllers\Backend\AdminRegistrationRefundController::class, 'cancelWithdraw']
  )->name('admin.registration.refund.cancel');

  Route::get('selection/index/{id}', [BackendTeamSelectionController::class, 'selection_index'])->name('selection.index');

  //scoreboard

  Route::get('/scoreboard/showScoreboard/{event}', [ScoreboardController::class, 'showScoreboard'])->name('scoreboard.teams.show');

  Route::resource('scoreboard', ScoreboardController::class);

  Route::get('league/frontindex', [LeagueController::class, 'frontIndex'])->name('league.front.index');
  Route::resource('league', LeagueController::class);

  //Player
  Route::get('player/search', [PlayerController::class, 'search'])->name('player.search');
  Route::post('player/attachNoProfile', [PlayerController::class, 'attachNoProfile'])->name('player.attach');
  Route::post('player/attach', [PlayerController::class, 'attach'])->name('backend.user.player.attach');

  Route::post(
    'user/{user}/players',
    [UserPlayerController::class, 'store']
  )->name('backend.user.players.store');
  Route::delete(
    'user/{user}/players/{player}',
    [UserPlayerController::class, 'destroy']
  )->name('backend.user.players.destroy');

  Route::get('player/details/{id}', [PlayerController::class, 'details'])->name('player.details');
  Route::get('player/removeProfileFromUser/{id}', [PlayerController::class, 'removeProfileFromUser'])->name('player.remove.profile');
  Route::get('player/addToProfile', [PlayerController::class, 'addToProfile'])->name('player.add.profile');
  Route::get('player/profile/{id}', [PlayerController::class, 'profile'])->name('backend.player.profile');
  Route::get('player/results/{id}', [PlayerController::class, 'results'])->name('player.results');
  Route::get('player/events/results/{id}', [PlayerController::class, 'playerEventResults'])->name('player.events.results');
  Route::resource('player', PlayerController::class);

  //Player
  Route::resource('regionTeam', RegionTeamController::class);

  //settings
  Route::post('settings/single', [SettingsController::class, 'storeSingle'])->name('settings.store.single');
  Route::resource('settings', SettingsController::class);

  //email
  Route::post('email/send', [EmailController::class, 'sendEmail'])->name('email.send');





    

    // Series → Events
    Route::get(
      'series/{series}/events',
      [SeriesController::class, 'events']
    )->name('series.events');

    Route::post(
      'series/{series}/events/add',
      [SeriesController::class, 'addEvent']
    )->name('series.events.add');

    Route::delete(
      'series/{series}/events/{event}',
      [SeriesController::class, 'removeEvent']
    )->name('series.events.remove');
  Route::post('/series/{series}/events/{event}/copy', [
    SeriesController::class,
    'copyEvent'
  ])->name('series.events.copy');





  Route::post(
      'series/{series}/events/create',
      [SeriesController::class, 'createEvent']
    )->name('series.events.create');


  Route::resource('series', SeriesController::class);
 

  
 Route::get(
  'backend/events/{event}/edit',
  [\App\Http\Controllers\Backend\EventController::class, 'edit']
)->name('backend.events.edit');


  Route::patch(
    'backend/events/{event}',
    [\App\Http\Controllers\Backend\EventController::class, 'update']
  )->name('backend.events.update');




  Route::get('series/publishLeaderboard/{id}', [SeriesController::class, 'togglePublish'])->name('series.publish.leaderboard');
  
  Route::get('/{series}/rankings', [SeriesController::class, 'rankingsOverberg'])->name('series.rankings'); // Rankings view
  Route::get('/{series}/settings', [SeriesController::class, 'settings'])->name('series.settings'); // Settings form
  Route::patch('/series/{series}/publish', [SeriesController::class, 'publish'])->name('series.publish');
  Route::patch('/series/{series}/unpublish', [SeriesController::class, 'unpublish'])->name('series.unpublish');

  Route::post('/series/{series}/email-players', [EmailController::class, 'sendToSeriesPlayers'])->name('series.email.players');

  //import export
  Route::get('exportRegistrations/{id}', [ImportExportController::class, 'exportRegistrations'])->name('export.registrations');

  // result
  Route::post('result/saveOrder/{id}', [ResultController::class, 'saveOrder'])->name('result.save.order');
  Route::post('result/reset/', [ResultController::class, 'resetPositions'])->name('positions.reset');
  Route::get('result/show/{id}', [ResultController::class, 'show'])->name('result.show');
  Route::get('result/publish/{id}', [ResultController::class, 'publishResults'])->name('result.publish');
  Route::get('result/details/{id}', [RankingController::class, 'details'])->name('result.details'); // legacy link kept

  //point (left as-is)
  Route::resource('point', RankingController::class);

  //goal
  Route::get('goal/create-general-goal/{id}', [GoalController::class, 'create_general_goal'])->name('create.general.goal');
  Route::get('goal/create-career-goal/{id}', [GoalController::class, 'create_career_goal'])->name('create.career.goal');
  Route::resource('goal', GoalController::class);

  //evaluation
  Route::resource('evaluation', EvaluationController::class);

  //practice
  Route::resource('practice', PracticeController::class);

  // charts
  Route::get('chart/test/{id}', [ChartController::class, 'test'])->name('chart.test');
  Route::get('chart/physical/{id}', [ChartController::class, 'physical'])->name('chart.physical');
});

// Frontend (auth)
Route::prefix('frontend')->middleware('auth')->group(function () {
  Route::get('player/profile/{id}', [FrontendPlayerController::class, 'player_profile'])->name('frontend.player.profile');
  //Photo
  Route::get('frontPhoto/showFolder/{id}', [FrontendPhotoController::class, 'show_folder'])->name('frontend.event.show.folder');
  Route::get('frontPhoto/eventPhoto/{id}', [FrontendPhotoController::class, 'folders'])->name('frontend.event.photos');
  Route::resource('frontPhoto', FrontendPhotoController::class);
});

//Frotend fixtures (public)
Route::get('frontend/fixtures/draw/index/{id}', [FrontFixtureController::class, 'drawFixtures'])->name('frontend.fixtures.index');
Route::get('frontend/fixtures/draw/show/{id}', [FrontFixtureController::class, 'show'])->name('frontend.fixtures.show');
Route::get('frontend/fixtures/draw/indexRound/{event}/{round}/{type}', [FrontFixtureController::class, 'drawFixturesRound'])->name('frontend.fixtures.indexRound');
Route::get('frontend/fixtures/draw/bracket/{id}', [FrontFixtureController::class, 'bracketFixtures'])->name('frontend.bracket.fixtures');
Route::resource('file', FileController::class);

//draw (frontend)
Route::get('frontend/draw/show/{id}', [EventController::class, 'showDraw'])->name('frontend.showDraw');

Route::prefix('eventAdmin/tabs')->group(function () {
  Route::get('entries/{id}', [EventAdminController::class, 'entries'])->name('event.tab.entries');
  Route::get('draws/{id}', [EventAdminController::class, 'draws'])->name('event.tab.draws');
  Route::get('results/{id}', [EventAdminController::class, 'results'])->name('event.tab.results');
  Route::get('settings/{id}', [EventAdminController::class, 'settings'])->name('event.tab.settings');
});

Route::get('/hoodie-order', [OrderController::class, 'showHoodieForm'])->name('hoodie.form');
Route::post('/hoodie-order/submit', [OrderController::class, 'submitHoodieForm'])->name('hoodie.submit');
// web.php
Route::get('/hoodie/sizes/{item_id}', [OrderController::class, 'getSizesForItem'])->name('hoodie.sizes');
Route::post('notify_hoodie', [OrderController::class, 'notifyHoodie'])->name('notify.hoodie');
Route::get('/hoodie/orders/paid', [OrderController::class, 'paidOrders'])->name('hoodie.orders.paid');

Route::get('/admin/draws/format-options/{id}', function ($id) {
  $option = \App\Models\DrawFormatOption::where('draw_format_id', $id)->first();
  return response()->json($option);
});
Route::get('backend/ranking/{series}/results', [RankingController::class, 'results'])
  ->name('backend.ranking.results');
Route::get('ranking/{series}/results', [RankingController::class, 'results'])
  ->name('rankings.results');
Route::get('backend/ranking/{series}/getSeriesData', [SeriesController::class, 'seriesRankings'])
  ->name('backend.ranking.series.data');



Route::prefix('region/{region}')->name('frontend.clothing.')->group(function () {
  Route::get('clothing/sheet', [ClothingOrderController::class, 'sheet'])->name('sheet');
  Route::post('clothing/place', [ClothingOrderController::class, 'place'])->name('place');
});
Route::post(
  'backend/ranking-scores/{id}/school',
  [App\Http\Controllers\Backend\RankingController::class, 'setSchool']
)->name('ranking-scores.school');

Route::get('/backend/team-fixtures/{fixture}/json', [TeamFixtureController::class, 'showJson'])
  ->name('backend.team-fixtures.json');


// routes/web.php
Route::post('/fixtures/{fixture}/save-score', [FrontFixtureController::class, 'saveScore'])
  ->name('frontend.fixtures.saveScore');

// routes/web.php
Route::get('/event/{event_id}/venue/{venue_id}', [TeamFixtureController::class, 'byVenue'])
  ->name('fixtures.venue');
Route::get(
  '/event/{eventId}/venue/{venueId}/order/{date}',
  [TeamFixtureController::class, 'orderOfPlay']
)->name('fixtures.order');

Route::get('/backend/event/{event}/players/export-pdf', [EventAdminController::class, 'exportAllPlayersPdf'])->name('event.players.exportPdf');
Route::get('/backend/event/{event}/players/exportExcel',[EventAdminController::class, 'exportPlayersExcel'])->name('event.players.exportExcel');

Route::post('/backend/user/update', [UserController::class, 'update'])->name('backend.user.update');

Route::match(['patch', 'post'], '/backend/player/{id}', [PlayerController::class, 'update'])
  ->name('backend.player.update');
Route::get('/backend/team-schedule/all/{event}', [TeamScheduleController::class, 'indexAll'])->name('backend.team-schedule.all');
Route::get('/backend/team-schedule/all-data/{event}', [TeamScheduleController::class, 'dataAll'])->name('backend.team-schedule.all.data');
Route::post('/backend/team-schedule/all-auto/{event}', [TeamScheduleController::class, 'autoAll'])->name('backend.team-schedule.all.auto');

// backend/team-fixtures/admin/{event}
Route::get('backend/team-fixtures/admin/{event}', [\App\Http\Controllers\Backend\TeamFixtureController::class, 'admin'])
  ->name('backend.team-fixtures.admin');

Route::prefix('backend')->middleware('auth')->group(function () {

  /*
  |--------------------------------------------------------------------------
  | DASHBOARD
  |--------------------------------------------------------------------------
  */
  Route::get('dashboard', [DashboardController::class, 'dashboard'])
    ->name('backend.dashboard');

  /*
  |--------------------------------------------------------------------------
  | USERS & PLAYERS DATA ENDPOINTS (for DataTables)
  |--------------------------------------------------------------------------
  */
  Route::get('user/data', [UserController::class, 'data'])->name('user.data');
  Route::get('player/data', [PlayerController::class, 'data'])->name('player.data');

  /*
  |--------------------------------------------------------------------------
  | EVENT ADMIN
  |--------------------------------------------------------------------------
  */
  Route::prefix('event')->group(function () {

    Route::get('{event}/teams', [EventAdminController::class, 'show'])
      ->name('admin.events.teams');
    Route::get('{event}/overview', [EventAdminController::class, 'overview'])
      ->name('admin.events.overview');

    Route::get('{event}/fixtures', [EventAdminController::class, 'fixtures'])
      ->name('admin.events.individual.hq');

    Route::post('{event}/copy', [BackendEventController::class, 'copyEvent'])
      ->name('admin.events.copy');
  });

  /*
  |--------------------------------------------------------------------------
  | DRAWS & FIXTURES
  |--------------------------------------------------------------------------
  */
  Route::post('draw/{draw}/lock', [DrawController::class, 'lock_draw'])
    ->name('draw.lock');

  Route::post('draw/{draw}/unlock', [DrawController::class, 'unlock_draw'])
    ->name('draw.unlock');

  // Team player withdraw / refund (frontend)
  Route::post(
    '/team/{team}/player/{player}/withdraw/{event}',
    [\App\Http\Controllers\Frontend\TeamPlayerWithdrawController::class, 'withdraw']
  )->middleware('auth')->name('team.player.withdraw');

  Route::post('/team/{team}/player/{player}/refund/{event}', [\App\Http\Controllers\Frontend\TeamPlayerWithdrawController::class, 'storeRefund'])
    ->middleware('auth')->name('team.player.refund.store');

});


Route::get('/fixtures/{draw}', [TeamFixtureFrontendController::class, 'index'])
  ->name('frontend.fixtures.draw');

// Public rankings index (series selection)
Route::get('rankings', [\App\Http\Controllers\Backend\RankingController::class, 'rankingsIndex'])
  ->name('rankings.index');

// Public frontend ranking view
Route::get('ranking/{series}', [\App\Http\Controllers\Backend\RankingController::class, 'ranking_frontend_show'])
  ->name('frontend.ranking.show');

// Public player ranking detail view
Route::get('ranking/{series}/player/{player}', [\App\Http\Controllers\Backend\RankingController::class, 'playerDetail'])
  ->name('frontend.ranking.player-detail');

// Public round-robin draw view
Route::get('roundrobin/{draw}', [PublicRoundRobinController::class, 'show'])
  ->name('public.roundrobin.show');

// Public bracket view (AJAX loaded, no auth required)
Route::get('roundrobin/{draw}/main-bracket', [PublicRoundRobinController::class, 'mainBracket'])
  ->name('public.roundrobin.main-bracket');

// Allow convenor, superadmin, and superuser roles to access score entry and management
Route::middleware(['auth', 'role:convenor|admin|super-user'])->group(function () {
  // Enter scores for a specific draw
  Route::get('/convenor/fixtures/enter-scores/{draw}', [\App\Http\Controllers\Frontend\TeamFixtureFrontendController::class, 'enterScores'])
    ->name('frontend.fixtures.enter-scores');
  // Store a score for a fixture
  Route::post('/frontend/fixtures/score/store/{fixture}', [TeamFixtureFrontendController::class, 'storeScore'])
    ->name('frontend.fixtures.score.store');
  // Delete a score for a fixture
  Route::delete('/convenor/fixtures/{fixture}/score', [\App\Http\Controllers\Frontend\TeamFixtureFrontendController::class, 'deleteScore'])
    ->name('frontend.fixtures.score.delete');
  // View all fixtures for a venue
  Route::get('/convenor/fixtures/venue/{venue}', [\App\Http\Controllers\Frontend\TeamFixtureFrontendController::class, 'venueFixtures'])
    ->name('frontend.fixtures.venue');

  // Convenor: Enter scores for all fixtures at a given event and venue
  Route::get('/convenor/fixtures/venue/{event}/{venue}/enter', [\App\Http\Controllers\Frontend\TeamFixtureFrontendController::class, 'enterScoresByEventVenue'])
    ->name('frontend.fixtures.enter-scores.venue');

});

