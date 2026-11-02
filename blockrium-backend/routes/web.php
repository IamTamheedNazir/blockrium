<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RevenueCatController;
use App\Http\Controllers\EngagementAdminController;
use App\Http\Controllers\TrustCenterAdminController;
use App\Http\Controllers\LearnEarnAdminController;
use App\Http\Controllers\SquadAdminController;
use App\Http\Controllers\SquadPublicController;
use App\Http\Controllers\AppHealthController;
use App\Http\Controllers\PartnerCampaignAdminController;
use App\Http\Controllers\PartnerPromoCodeAdminController;
use App\Http\Controllers\PartnerCampaignPublicController;
use App\Http\Controllers\VerifiedMemberAdminController;
use App\Http\Controllers\MiningAuditAdminController;
use App\Http\Controllers\WalletMonitorAdminController;
use App\Http\Controllers\AdminEmailCampaignController;
use App\Http\Controllers\EmailCampaignTrackingController;
use App\Http\Controllers\GooglePlayPurchaseAdminController;
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


Route::get('/clear-cache', function() {
    abort_unless(app()->environment('local'), 404);
	//$exitCode = Artisan::call('migrate');
	$exitCode = Artisan::call('config:clear');
    $exitCode = Artisan::call('cache:clear');
	$exitCode = Artisan::call('view:clear');
	$exitCode = Artisan::call('route:clear');
	//$exitCode = Artisan::call('view:clear'); 
    return '<h1>Cache facade value cleared</h1>';
});

Route::get('/squad/{code}/poster.svg', [SquadPublicController::class, 'poster'])
    ->where('code', '[A-Za-z0-9_-]+')
    ->name('squad.poster');
Route::get('/squad/{code}', [SquadPublicController::class, 'landing'])
    ->where('code', '[A-Za-z0-9_-]+')
    ->name('squad.invite');

Route::get('/email/bulk-opt-out/{userId}', [AdminEmailCampaignController::class, 'optOutPage'])
    ->where('userId', '[0-9]+')->middleware(['signed', 'throttle:60,1'])->name('email.bulk-opt-out');
Route::post('/email/bulk-opt-out/{userId}', [AdminEmailCampaignController::class, 'optOut'])
    ->where('userId', '[0-9]+')->middleware(['signed', 'throttle:60,1'])->name('email.bulk-opt-out.confirm');
Route::get('/email/campaign/click/{token}', [EmailCampaignTrackingController::class, 'click'])
    ->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:120,1')->name('email.campaign.click');
Route::get('/email/campaign/open/{token}.gif', [EmailCampaignTrackingController::class, 'open'])
    ->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:240,1')->name('email.campaign.open');

Route::get('/partner/campaign/{slug}', [PartnerCampaignPublicController::class, 'redirect'])
    ->where('slug', '[A-Za-z0-9_-]+')
    ->middleware('throttle:120,1')
    ->name('partner.campaign.redirect');
Route::get('/partner/dashboard/{token}/overview', [PartnerCampaignPublicController::class, 'overview'])
    ->where('token', '[A-Za-z0-9]+')
    ->middleware('throttle:120,1');
Route::get('/partner/dashboard/{token}/campaigns', [PartnerCampaignPublicController::class, 'campaigns'])
    ->where('token', '[A-Za-z0-9]+')
    ->middleware('throttle:120,1');
Route::get('/partner/dashboard/{token}/campaigns/{campaignId}/metrics', [PartnerCampaignPublicController::class, 'metrics'])
    ->where(['token' => '[A-Za-z0-9]+', 'campaignId' => '[0-9]+'])
    ->middleware('throttle:120,1');
Route::get('/partner/dashboard/{token}', [PartnerCampaignPublicController::class, 'dashboard'])
    ->where('token', '[A-Za-z0-9]+')
    ->middleware('throttle:60,1')
    ->name('partner.dashboard');

Route::get('/ad_maticlegend', [AdminController::class,'index']); 
Route::post('/dologin', [AdminController::class,'dologin']); 
Route::get('admin/forgotpassword', [AdminController::class,'forgotpassword']);
Route::post('admin/forgot_password', [AdminController::class,'forgot_password']);
Route::get('admin/reset_password', [AdminController::class,'reset_password']);
Route::post('admin/reset_password', [AdminController::class,'updatePassword']);




Route::group(['middleware' => ['checklogin']], function () {
Route::get('/dashboard', [AdminController::class,'home']);
Route::get('admin/change_password', [AdminController::class,'changepassword']);
Route::post('admin/change_password', [AdminController::class,'change_password']);
Route::get('admin/logout', [AdminController::class,'logout']);

// Route::get('razorpay-payment', [AdminController::class, 'razorpayindex']);
// Route::get('paysuccess', [AdminController::class, 'razorPaySuccess']);
// Route::get('razor-thank-you', [AdminController::class, 'RazorThankYou']);
// Route::get('all-payment', [AdminController::class, 'allPayment']);


Route::get('admin/add-task', [AdminController::class,'addTask']);
Route::post('admin/add-task', [AdminController::class,'taskSubmit']);
Route::get('admin/manage-task', [AdminController::class,'manageTask']);
Route::get('admin/edit-task/{id}', [AdminController::class,'addTask']);  
Route::get('admin/delete-task', [AdminController::class,'deleteTask']);
Route::get('admin/status', [AdminController::class,'Taskstatus']);
Route::get('admin/task-view/{id}', [AdminController::class,'Taskview']);


Route::get('admin/users',[AdminController::class,'userview']);
Route::post('users/status', [AdminController::class,'userstatus']);
Route::post('admin/users/is-active', [AdminController::class,'isActive']);
Route::post('admin/delete_users/{id}', [AdminController::class,'deleteUsers'])->where('id', '[0-9]+');
Route::get('admin/users/transactions',[AdminController::class,'transactionslist']);
Route::get('admin/email-campaigns', [AdminEmailCampaignController::class, 'index'])->name('admin.email-campaigns.index');
Route::get('admin/email-campaigns/create', [AdminEmailCampaignController::class, 'index'])->name('admin.email-campaigns.create');
Route::get('admin/email-campaigns/{campaignId}', [AdminEmailCampaignController::class, 'show'])->where('campaignId', '[0-9]+')->name('admin.email-campaigns.show');
Route::post('admin/email-campaigns', [AdminEmailCampaignController::class, 'store'])->name('admin.email-campaigns.store');
Route::post('admin/email-campaigns/brand-settings', [AdminEmailCampaignController::class, 'saveBrandSettings'])->name('admin.email-campaigns.brand-settings');
Route::post('admin/email-campaigns/{campaignId}/test', [AdminEmailCampaignController::class, 'testSend'])->where('campaignId', '[0-9]+')->name('admin.email-campaigns.test');
Route::post('admin/email-campaigns/{campaignId}/queue', [AdminEmailCampaignController::class, 'queue'])->where('campaignId', '[0-9]+')->name('admin.email-campaigns.queue');
Route::post('admin/email-campaigns/{campaignId}/retry-failed', [AdminEmailCampaignController::class, 'retryFailed'])->where('campaignId', '[0-9]+')->name('admin.email-campaigns.retry-failed');
Route::get('admin/manage/banner',[AdminController::class,'managebanner']);
Route::get('admin/add/banner',[AdminController::class,'addbanner']);
Route::post('admin/add/banner',[AdminController::class,'submitbanner']);
Route::get('admin/delete_banner/{id}', [AdminController::class,'deleteBanner']);

Route::get('admin/add/addcoin',[AdminController::class,'add_coin_to_multi_users']);
Route::post('admin/add/addcoin',[NotificationController::class,'sendCoin']);

Route::get('admin/add/single-user-addcoin',[AdminController::class,'add_coin_to_single_users']);
Route::post('admin/add/single-user-addcoin',[NotificationController::class,'sendCoinSingleUser']);

Route::get('admin/add/app-stage',[AdminController::class,'AppStage']);
Route::post('admin/add/app-stage',[AdminController::class,'submitStage']);
Route::get('admin/manage/manage-stage',[AdminController::class,'ManageAppStage']);
Route::get('admin/edit-app-stage/{id}', [AdminController::class,'AppStage']);  
Route::get('admin/delete_app-stage/{id}', [AdminController::class,'deleteAppStage']);

Route::get('admin/add/app-version',[AdminController::class,'Appversion']);
Route::post('admin/add/app-version',[AdminController::class,'submitversion']);
Route::get('admin/edit-app-version/{id}', [AdminController::class,'Appversion']); 
Route::get('admin/manage/app-version',[AdminController::class,'ManageAppVersion']);


Route::get('admin/manage/news',[AdminController::class,'managenews']);
Route::get('admin/add/news',[AdminController::class,'addnews']);
Route::post('admin/add/news',[AdminController::class,'submitnews']);
Route::get('admin/delete_news/{id}', [AdminController::class,'deletenews']);


Route::get('admin/manage/apk',[AdminController::class,'manageapk']);
Route::get('admin/add/apk',[AdminController::class,'addapk']);
Route::post('admin/add/apk',[AdminController::class,'submitapk']);
Route::get('admin/delete_apk/{id}', [AdminController::class,'deleteapk']);


Route::get('admin/add/app-maintenance',[AdminController::class,'Appmaintenance']);
Route::post('admin/add/app-maintenance',[AdminController::class,'submitmaintenance']);
Route::get('admin/edit-app-maintenance/{id}', [AdminController::class,'Appmaintenance']); 
Route::get('admin/manage/app-maintenance',[AdminController::class,'ManageAppmaintenance']);
Route::get('admin/app-health', [AppHealthController::class, 'index'])->name('admin.app-health');


Route::get('admin/manage/game-category',[AdminController::class,'ManageGameCategory']);
Route::get('admin/add/game-category',[AdminController::class,'addGameCategory']);
Route::post('admin/add/game-category',[AdminController::class,'submitGameCategory']);
Route::get('admin/delete-game-category/{id}', [AdminController::class,'deleteGameCategory']);

Route::get('admin/manage/game',[AdminController::class,'ManageGame']);
Route::get('admin/add/game',[AdminController::class,'addGame']);
Route::post('admin/add/game',[AdminController::class,'submitGame']);
Route::get('admin/delete-game/{id}', [AdminController::class,'deleteGame']);
Route::get('admin/edit-game/{id}', [AdminController::class,'addGame']);

Route::get('admin/manage/tasks',[AdminController::class,'managetasks']);
Route::get('admin/add/tasks',[AdminController::class,'addtasks']);
Route::post('admin/add/tasks',[AdminController::class,'submittasks']);
Route::get('admin/delete_tasks/{id}', [AdminController::class,'deletetasks']);
Route::get('admin/manage/boost-plans',[AdminController::class,'manageBoostPlans']);
Route::get('admin/add/boost-plan',[AdminController::class,'addBoostPlan']);
Route::get('admin/edit-boost-plan/{id}',[AdminController::class,'addBoostPlan']);
Route::post('admin/add/boost-plan',[AdminController::class,'submitBoostPlan']);
Route::get('admin/delete_boost-plan/{id}',[AdminController::class,'deleteBoostPlan']);
Route::get('admin/manage/boost-purchases',[AdminController::class,'manageBoostPurchases']);
Route::get('boost-payment-return', function () {
    return response('<!doctype html><html><head><meta charset="utf-8"><title>Blockrium payment</title></head><body style="font-family: sans-serif; padding: 40px;"><h1>Payment submitted</h1><p>Return to the Blockrium app. Your mining boost will activate after Dodo confirms the payment.</p></body></html>');
});
Route::get('admin/subscriptions',[AdminController::class,'subscriptions']);
Route::get('admin/subscriptions/stats',[RevenueCatController::class,'stats']);
Route::get('admin/subscriptions/users',[RevenueCatController::class,'users']);
Route::get('admin/subscriptions/revenue',[RevenueCatController::class,'revenue']);

Route::prefix('admin/partner-campaigns')->name('admin.partner-campaigns.')->group(function () {
    Route::get('/', [PartnerCampaignAdminController::class, 'index'])->name('index');
    Route::get('/create', [PartnerCampaignAdminController::class, 'create'])->name('create');
    Route::post('/', [PartnerCampaignAdminController::class, 'store'])->name('store');
    Route::get('/{id}/edit', [PartnerCampaignAdminController::class, 'edit'])->where('id', '[0-9]+')->name('edit');
    Route::match(['put', 'patch'], '/{id}', [PartnerCampaignAdminController::class, 'update'])->where('id', '[0-9]+')->name('update');
    Route::post('/{id}/toggle', [PartnerCampaignAdminController::class, 'toggle'])->where('id', '[0-9]+')->name('toggle');
    Route::get('/completions', [PartnerCampaignAdminController::class, 'completions'])->name('completions');
    Route::post('/completions/{id}/approve', [PartnerCampaignAdminController::class, 'approve'])->where('id', '[0-9]+')->name('completions.approve');
    Route::post('/completions/{id}/reject', [PartnerCampaignAdminController::class, 'reject'])->where('id', '[0-9]+')->name('completions.reject');
    Route::get('/task-completions', [PartnerCampaignAdminController::class, 'taskCompletions'])->name('task-completions');
    Route::post('/task-completions/{id}/approve', [PartnerCampaignAdminController::class, 'approveTask'])->where('id', '[0-9]+')->name('task-completions.approve');
    Route::post('/task-completions/{id}/reject', [PartnerCampaignAdminController::class, 'rejectTask'])->where('id', '[0-9]+')->name('task-completions.reject');
    Route::post('/partners', [PartnerCampaignAdminController::class, 'storePartner'])->name('partners.store');
    Route::post('/partners/{id}/dashboard-token', [PartnerCampaignAdminController::class, 'issueDashboardToken'])->where('id', '[0-9]+')->name('partners.token');
    Route::post('/partners/{id}/dashboard-token/rotate', [PartnerCampaignAdminController::class, 'rotateDashboardToken'])->where('id', '[0-9]+')->name('partners.token.rotate');
    Route::get('/{campaignId}/promo-codes', [PartnerPromoCodeAdminController::class, 'index'])->where('campaignId', '[0-9]+')->name('promo-codes.index');
    Route::post('/{campaignId}/promo-codes', [PartnerPromoCodeAdminController::class, 'store'])->where('campaignId', '[0-9]+')->name('promo-codes.store');
    Route::get('/promo-batches/{batchId}/export', [PartnerPromoCodeAdminController::class, 'export'])->where('batchId', '[0-9]+')->name('promo-codes.export');
    Route::get('/promo-batches/{batchId}/redemptions', [PartnerPromoCodeAdminController::class, 'redemptions'])->where('batchId', '[0-9]+')->name('promo-codes.redemptions');
    Route::post('/promo-batches/{batchId}/revoke-unused', [PartnerPromoCodeAdminController::class, 'revokeUnused'])->where('batchId', '[0-9]+')->name('promo-codes.revoke-unused');
});

Route::get('/admin/partner-promo-codes', [PartnerPromoCodeAdminController::class, 'campaigns'])
    ->name('admin.partner-promo-codes.index');

Route::prefix('admin/verified-members')->name('admin.verified-members.')->group(function () {
    Route::get('/', [VerifiedMemberAdminController::class, 'index'])->name('index');
    Route::post('/sync-didit', [VerifiedMemberAdminController::class, 'syncDidit'])->name('sync-didit');
    Route::get('/settings', [VerifiedMemberAdminController::class, 'settings'])->name('settings');
    Route::post('/settings', [VerifiedMemberAdminController::class, 'updateSettings'])->name('settings.update');
    Route::get('/waitlist', [VerifiedMemberAdminController::class, 'waitlist'])->name('waitlist');
    Route::get('/payment-support', [VerifiedMemberAdminController::class, 'paymentSupport'])->name('payment-support');
    Route::post('/payment-support/{id}', [VerifiedMemberAdminController::class, 'updatePaymentSupport'])->where('id', '[0-9]+')->name('payment-support.update');
    Route::post('/waitlist/invite', [VerifiedMemberAdminController::class, 'inviteWaitlist'])->name('waitlist.invite');
    Route::post('/waitlist/invite-selected', [VerifiedMemberAdminController::class, 'inviteSelectedWaitlist'])->name('waitlist.invite-selected');
    Route::post('/{userId}/kyc/resend-invite', [VerifiedMemberAdminController::class, 'resendWaitlistInvite'])->where('userId', '[0-9]+')->name('kyc.resend-invite');
    Route::post('/waitlist/country-limit', [VerifiedMemberAdminController::class, 'saveCountryLimit'])->name('waitlist.country-limit');
    Route::post('/{userId}/kyc/approve', [VerifiedMemberAdminController::class, 'approveKyc'])->where('userId', '[0-9]+')->name('kyc.approve');
    Route::post('/{userId}/kyc/reject', [VerifiedMemberAdminController::class, 'rejectKyc'])->where('userId', '[0-9]+')->name('kyc.reject');
    Route::post('/{userId}/access/revoke', [VerifiedMemberAdminController::class, 'revokeAccess'])->where('userId', '[0-9]+')->name('access.revoke');
    Route::post('/{userId}/reminder', [VerifiedMemberAdminController::class, 'sendReminder'])->where('userId', '[0-9]+')->name('reminder');
    Route::post('/reminders', [VerifiedMemberAdminController::class, 'sendReminders'])->name('reminders.send');
    Route::post('/{userId}/payment/confirm', [VerifiedMemberAdminController::class, 'confirmPayment'])->where('userId', '[0-9]+')->name('payment.confirm');
});

Route::get('admin/google-play/purchases', [GooglePlayPurchaseAdminController::class, 'index'])
    ->name('admin.google-play.purchases');

Route::prefix('admin/audit/mining')->name('admin.audit.mining.')->group(function () {
    Route::get('/', [MiningAuditAdminController::class, 'index'])->name('index');
    Route::post('/scan', [MiningAuditAdminController::class, 'scan'])->name('scan');
    Route::post('/cases/{id}', [MiningAuditAdminController::class, 'update'])->where('id', '[0-9]+')->name('cases.update');
    Route::post('/users/{userId}/wallet-adjustment', [MiningAuditAdminController::class, 'adjustWallet'])->where('userId', '[0-9]+')->name('wallet-adjustment');
});

Route::get('/admin/wallet-monitor', [WalletMonitorAdminController::class, 'index'])
    ->name('admin.wallet-monitor.index');

Route::prefix('admin/trust-center')->name('admin.trust-center.')->group(function () {
    Route::get('/', [TrustCenterAdminController::class, 'index'])->name('index');
    Route::get('/sections/create', [TrustCenterAdminController::class, 'create'])->name('create');
    Route::post('/sections', [TrustCenterAdminController::class, 'store'])->name('store');
    Route::get('/sections/{id}/edit', [TrustCenterAdminController::class, 'edit'])->name('edit')->where('id', '[0-9]+');
    Route::match(['put', 'patch'], '/sections/{id}', [TrustCenterAdminController::class, 'update'])->name('update')->where('id', '[0-9]+');
    Route::post('/sections/{id}/toggle', [TrustCenterAdminController::class, 'toggle'])->name('toggle')->where('id', '[0-9]+');
});

Route::prefix('admin/engagement')->name('admin.engagement.')->group(function () {
    Route::get('/', [EngagementAdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/xp-events', [EngagementAdminController::class, 'xpEvents'])->name('xp-events');

    Route::get('/badges', [EngagementAdminController::class, 'badges'])->name('badges');
    Route::get('/badges/create', [EngagementAdminController::class, 'createBadge'])->name('badges.create');
    Route::post('/badges', [EngagementAdminController::class, 'storeBadge'])->name('badges.store');
    Route::get('/badges/{id}/edit', [EngagementAdminController::class, 'editBadge'])->name('badges.edit')->where('id', '[0-9]+');
    Route::match(['put', 'patch'], '/badges/{id}', [EngagementAdminController::class, 'updateBadge'])->name('badges.update')->where('id', '[0-9]+');
    Route::post('/badges/{id}/toggle', [EngagementAdminController::class, 'toggleBadge'])->name('badges.toggle')->where('id', '[0-9]+');
    Route::get('/user-badges', [EngagementAdminController::class, 'userBadges'])->name('user-badges');

    Route::get('/seasons', [EngagementAdminController::class, 'seasons'])->name('seasons');
    Route::get('/seasons/create', [EngagementAdminController::class, 'createSeason'])->name('seasons.create');
    Route::post('/seasons', [EngagementAdminController::class, 'storeSeason'])->name('seasons.store');
    Route::get('/seasons/{id}/edit', [EngagementAdminController::class, 'editSeason'])->name('seasons.edit')->where('id', '[0-9]+');
    Route::match(['put', 'patch'], '/seasons/{id}', [EngagementAdminController::class, 'updateSeason'])->name('seasons.update')->where('id', '[0-9]+');

    Route::get('/referral-milestones', [EngagementAdminController::class, 'referralMilestones'])->name('referral-milestones');
    Route::get('/referral-milestones/create', [EngagementAdminController::class, 'createReferralMilestone'])->name('referral-milestones.create');
    Route::post('/referral-milestones', [EngagementAdminController::class, 'storeReferralMilestone'])->name('referral-milestones.store');
    Route::get('/referral-milestones/{id}/edit', [EngagementAdminController::class, 'editReferralMilestone'])->name('referral-milestones.edit')->where('id', '[0-9]+');
    Route::match(['put', 'patch'], '/referral-milestones/{id}', [EngagementAdminController::class, 'updateReferralMilestone'])->name('referral-milestones.update')->where('id', '[0-9]+');
    Route::post('/referral-milestones/{id}/toggle', [EngagementAdminController::class, 'toggleReferralMilestone'])->name('referral-milestones.toggle')->where('id', '[0-9]+');

    Route::get('/referral-claims', [EngagementAdminController::class, 'referralClaims'])->name('referral-claims');
    Route::get('/daily-missions', [EngagementAdminController::class, 'dailyMissions'])->name('daily-missions');
    Route::get('/health', [EngagementAdminController::class, 'health'])->name('health');
    Route::get('/squads', [SquadAdminController::class, 'index'])->name('squads');
    Route::get('/squads/{id}', [SquadAdminController::class, 'show'])->name('squads.show')->where('id', '[0-9]+');
    Route::get('/squads/{id}/settings', [SquadAdminController::class, 'settings'])->name('squads.settings')->where('id', '[0-9]+');
    Route::post('/squads/{id}/settings', [SquadAdminController::class, 'updateSettings'])->name('squads.settings.update')->where('id', '[0-9]+');
    Route::post('/squads/{id}/announcements', [SquadAdminController::class, 'storeAnnouncement'])->name('squads.announcements.store')->where('id', '[0-9]+');
    Route::post('/squads/{id}/member-role', [SquadAdminController::class, 'updateMemberRole'])->name('squads.member-role')->where('id', '[0-9]+');
    Route::post('/squads/{id}/remove-member/{userId}', [SquadAdminController::class, 'removeMember'])->name('squads.remove-member')->where(['id' => '[0-9]+', 'userId' => '[0-9]+']);
    Route::post('/squads/missions/{missionId}/toggle', [SquadAdminController::class, 'toggleMission'])->name('squads.missions.toggle')->where('missionId', '[0-9]+');
    Route::post('/squads/{id}/toggle', [SquadAdminController::class, 'toggle'])->name('squads.toggle')->where('id', '[0-9]+');

    Route::get('/learn', [LearnEarnAdminController::class, 'index'])->name('learn');
    Route::get('/learn/create', [LearnEarnAdminController::class, 'create'])->name('learn.create');
    Route::post('/learn', [LearnEarnAdminController::class, 'store'])->name('learn.store');
    Route::get('/learn/{id}/edit', [LearnEarnAdminController::class, 'edit'])->name('learn.edit')->where('id', '[0-9]+');
    Route::match(['put', 'patch'], '/learn/{id}', [LearnEarnAdminController::class, 'update'])->name('learn.update')->where('id', '[0-9]+');
    Route::post('/learn/{id}/toggle', [LearnEarnAdminController::class, 'toggle'])->name('learn.toggle')->where('id', '[0-9]+');
    Route::get('/learn/{id}/questions', [LearnEarnAdminController::class, 'questions'])->name('learn.questions')->where('id', '[0-9]+');
    Route::post('/learn/{id}/questions', [LearnEarnAdminController::class, 'storeQuestion'])->name('learn.questions.store')->where('id', '[0-9]+');
    Route::get('/learn/{id}/questions/{questionId}/edit', [LearnEarnAdminController::class, 'editQuestion'])->name('learn.questions.edit')->where(['id' => '[0-9]+', 'questionId' => '[0-9]+']);
    Route::match(['put', 'patch'], '/learn/{id}/questions/{questionId}', [LearnEarnAdminController::class, 'updateQuestion'])->name('learn.questions.update')->where(['id' => '[0-9]+', 'questionId' => '[0-9]+']);
    Route::delete('/learn/{id}/questions/{questionId}', [LearnEarnAdminController::class, 'deleteQuestion'])->name('learn.questions.delete')->where(['id' => '[0-9]+', 'questionId' => '[0-9]+']);
    Route::get('/learn-progress', [LearnEarnAdminController::class, 'progress'])->name('learn.progress');
});

Route::get('admin/activate-user-boost/{id}',[AdminController::class,'activateUserBoost']);
Route::get('admin/cancel-user-boost/{id}',[AdminController::class,'cancelUserBoost']);
Route::get('admin/referral/settings',[AdminController::class,'referralSettings']);
Route::post('admin/referral/settings',[AdminController::class,'submitReferralSettings']);
Route::get('admin/referral/missions',[AdminController::class,'manageReferralMissions']);
Route::get('admin/referral/mission/add',[AdminController::class,'addReferralMission']);
Route::get('admin/referral/mission/edit/{id}',[AdminController::class,'addReferralMission']);
Route::post('admin/referral/mission/save',[AdminController::class,'submitReferralMission']);
Route::get('admin/referral/mission/delete/{id}',[AdminController::class,'deleteReferralMission']);

Route::get('admin/add/whitepaper',[AdminController::class,'addwhitepaper']);
Route::post('admin/add/whitepaper',[AdminController::class,'submitwhitepaper']);

Route::get('admin/add/notification',[AdminController::class,'addnotification']);

Route::get('admin/add/push-notification',[AdminController::class,'addPushnotification']);
Route::post('admin/add/push-notification',[AdminController::class,'submitPushnotification']);

Route::get('admin/logs', [AdminController::class, 'Logshow'])->name('logs');
Route::get('admin/logs/{lines}', [AdminController::class, 'Logshow'])->name('logs.view');

/* Manage site settings*/

// Route::get('admin/managesite', [AdminController::class,'ManageSite']);
// Route::get('admin/managesite/edit/{id}',[AdminController::class,'EditSitesetting']);
// Route::get('admin/managesite/add', [AdminController::class,'AddManageSite']);
// Route::post('admin/managesite/editsite', [AdminController::class,'SubmitEditSetting']);

});


/*
=========================
=====Frontend============
=========================
*/

Route::get('/', [HomeController::class,'home']);
Route::get('/about', [HomeController::class,'about']);
Route::get('/privacy-policy', [HomeController::class,'PrivacyPolicy'])->name('privacy-policy');
Route::get('/privacy-policy.html', [HomeController::class,'PrivacyPolicy']);
Route::get('/delete.html', [HomeController::class,'RequestDelete']);
Route::get('/delete-account-request', [HomeController::class,'RequestDelete'])->name('delete-account-request');
Route::post('delete-account-request',[HomeController::class,'AcountRequestDelete']);
