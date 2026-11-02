<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api;
use App\Http\Controllers\ResUserController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\RevenueCatController;
use App\Http\Controllers\EngagementController;
use App\Http\Controllers\TrustCenterController;
use App\Http\Controllers\LearnEarnController;
use App\Http\Controllers\SquadController;
use App\Http\Controllers\SquadPublicController;
use App\Http\Controllers\AppIssueLogController;
use App\Http\Controllers\AppEngagementEventController;
use App\Http\Controllers\PartnerCampaignController;
use App\Http\Controllers\PartnerPromoCodeController;
use App\Http\Controllers\VerifiedMemberController;
use App\Http\Controllers\DiditWebhookController;
use App\Http\Controllers\DodoBoostWebhookController;
use App\Http\Controllers\GooglePlayPurchaseController;
use App\Http\Controllers\GooglePlayRtdnController;
use App\Notifications\CoinsSentNotification;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/revenuecat/webhook', [RevenueCatController::class, 'handle'])
    ->middleware('throttle:120,1');
Route::post('/app-issue-log', [AppIssueLogController::class, 'store'])
    ->middleware('throttle:120,1');
Route::post('/app-engagement-event', [AppEngagementEventController::class, 'store'])
    ->middleware('throttle:240,1');
Route::post('/webhooks/didit', [DiditWebhookController::class, 'handle'])
    ->middleware('throttle:120,1');
Route::post('/webhooks/dodo/boost', [DodoBoostWebhookController::class, 'handle'])
    ->middleware('throttle:120,1');
Route::post('/webhooks/google-play', [GooglePlayRtdnController::class, 'handle'])
    ->middleware('throttle:120,1');

Route::get('/trust-center', [TrustCenterController::class, 'show'])
    ->middleware('throttle:60,1');
Route::get('/squads/invite/{code}', [SquadPublicController::class, 'resolve'])
    ->where('code', '[A-Za-z0-9_-]+')
    ->middleware('throttle:60,1');

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/mining', [Api::class, 'UserMining'])->name('mining');
    Route::get('banners', [Api::class, 'banners']);
    Route::post('/referral', [ReferralController::class, 'create']);
    Route::get('app-version', [Api::class, 'app_version']);
    Route::get('app-stage', [Api::class, 'appStage']);
    Route::get('top-miners',[Api::class,'topMiners']);
    Route::get('top-refferals',[Api::class,'topRefferals']);
    Route::post('update-password',[Api::class,'updatePassword']);
    Route::post('send-otp-update-password',[Api::class,'SendOtpupdatePassword']);
    Route::get('get-countries',[Api::class,'getCountries']);
    Route::get('get-news',[Api::class,'getNews']);
    Route::get('/apk/download', [Api::class, 'APkdownloadFile']);
    Route::get('/app-maintenance', [Api::class, 'APpMaintenance']);
    Route::get('/games', [Api::class, 'AllGames']);
    Route::get('/top-games', [Api::class, 'TopGames']);
    Route::get('/recommended-games', [Api::class, 'RecommendedGames']);
    Route::get('/global-rank', [Api::class, 'globalrank']);
    Route::get('/white-paper', [Api::class, 'whitepaper']);
    Route::get('/total-reward', [Api::class, 'totalReward']);

});
// Route::group(['middleware' => 'api'], function($router) {
//     Route::post('/register', [Api::class, 'register']);
//     Route::post('/login', [Api::class, 'login']);
//     Route::post('/logout', [Api::class, 'logout']);
//     Route::post('/refresh', [Api::class, 'refresh']);
//     Route::post('/profile', [Api::class, 'profile']);
// });

Route::group(['middleware' => ['api', 'throttle:60,1'], 'prefix' => 'auth'], function ($router) {

    Route::post('google-play/purchase-intents', [GooglePlayPurchaseController::class, 'createIntent']);
    Route::post('google-play/verify', [GooglePlayPurchaseController::class, 'verify']);

    Route::get('daily-missions', [EngagementController::class, 'dailyMissions']);
    Route::get('gamification/summary', [EngagementController::class, 'gamificationSummary']);
    Route::get('gamification/badges', [EngagementController::class, 'gamificationBadges']);
    Route::get('referrals/journey', [EngagementController::class, 'referralJourney']);
    Route::post('referrals/milestone/{id}/claim', [EngagementController::class, 'claimReferralMilestone'])
        ->where('id', '[0-9]+');
    Route::get('learn/modules', [LearnEarnController::class, 'modules']);
    Route::get('learn/modules/{id}', [LearnEarnController::class, 'detail'])->where('id', '[0-9]+');
    Route::post('learn/modules/{id}/complete', [LearnEarnController::class, 'complete'])->where('id', '[0-9]+');
    Route::get('squads/me', [SquadController::class, 'me']);
    Route::get('squads/app-home', [SquadController::class, 'appHome']);
    Route::get('squads/home', [SquadController::class, 'home']);
    Route::get('squads/members', [SquadController::class, 'members']);
    Route::get('squads/discover', [SquadController::class, 'discover']);
    Route::get('squads/leaderboard', [SquadController::class, 'leaderboard']);
    Route::post('squads/create', [SquadController::class, 'create']);
    Route::post('squads/join', [SquadController::class, 'join']);
    Route::post('squads/leave', [SquadController::class, 'leave']);
    Route::post('squads/chest/claim', [SquadController::class, 'claimChest']);
    Route::get('squads/missions', [SquadController::class, 'missions']);
    Route::post('squads/missions/{id}/claim', [SquadController::class, 'claimMission'])->where('id', '[0-9]+');
    Route::post('squads/invite-streak/claim', [SquadController::class, 'claimInviteStreak']);
    Route::get('squads/share-card', [SquadController::class, 'shareCard']);
    Route::post('squads/share', [SquadController::class, 'trackShare']);
    Route::post('squads/follow', [SquadController::class, 'follow']);
    Route::post('squads/invite/regenerate', [SquadController::class, 'regenerateInvite']);
    Route::post('squads/member/role', [SquadController::class, 'updateMemberRole']);
    Route::post('squads/member/remove', [SquadController::class, 'removeMember']);
    Route::post('squads/members/role', [SquadController::class, 'updateMemberRole']);
    Route::post('squads/members/remove', [SquadController::class, 'removeMember']);
    Route::get('squads/announcements', [SquadController::class, 'announcements']);

    Route::post('register', [ResUserController::class, 'register']);
    Route::post('login', [ResUserController::class, 'login']);
    Route::post('logout', [ResUserController::class, 'logout']);
    Route::post('refresh', [ResUserController::class, 'refresh']);
    //Route::post('addcredit', [ResUserController::class, 'addCredit']);
    Route::get('profile', [ResUserController::class, 'userProfile']);
    Route::post('profile_update', [ResUserController::class, 'updateProfile']);
    Route::post('userattendance', [ResUserController::class, 'UserAttendance']);
    Route::post('/auth_mining', [ResUserController::class, 'usermining']);

    Route::post('/dailyreward', [ResUserController::class, 'dailyreward']);
    Route::post('random-dailyreward', [ResUserController::class, 'random_dailyreward']);
    Route::get('reward-status', [ResUserController::class, 'reward_status']);
    Route::get('reward-calendar', [ResUserController::class, 'rewardCalendar']);
    Route::post('reward-chest/claim', [ResUserController::class, 'claimRewardChest']);
    Route::get('onboarding-quest', [ResUserController::class, 'onboardingQuest']);
    Route::post('onboarding-quest/claim', [ResUserController::class, 'claimOnboardingQuest']);
    Route::get('wallet', [ResUserController::class, 'wallet']);
    Route::get('get_mining', [ResUserController::class, 'get_mining_details']);
    Route::post('/mining-button',[ResUserController::class, 'click']);
    Route::post('/click-button', [ResUserController::class, 'clickButton']);
    Route::post('update-referred-by',[ResUserController::class, 'update_referral']);
    
    Route::get('getnotification',[ResUserController::class, 'get_notification']);
    Route::post('/notifications/mark-as-read',[ResUserController::class, 'markAsRead']);
    Route::get('count-notification',[ResUserController::class, 'count_notification']);

    Route::get('referred-by-team',[ResUserController::class, 'team_referral']);
    Route::get('my-network',[ResUserController::class, 'myNetwork']);
    Route::post('referral-ping',[ResUserController::class, 'pingReferral']);
    Route::post('validate-referral-code',[ResUserController::class, 'validateReferralCode']);
    Route::get('referral-missions',[ResUserController::class, 'referralMissions']);
    Route::post('claim-referral-mission',[ResUserController::class, 'claimReferralMission']);
    
    
    Route::get('is-active', [ResUserController::class, 'isActive']);
    Route::get('refferal-notification', [ResUserController::class, 'refferalNotification']);
    Route::get('refferal-notification-read/{id}', [ResUserController::class, 'refferalNotificationRead']);
    Route::get('refferal-notification-count', [ResUserController::class, 'refferalNotificationCount']);
    
    Route::get('all-notification', [ResUserController::class, 'allNotification']);
    
    Route::post('email-verify', [ResUserController::class, 'EmailVerify']);
    Route::post('/verify-otp', [ResUserController::class, 'verifyOtp']);
    
    Route::get('verification/status', [VerifiedMemberController::class, 'status']);
    Route::get('verification/waitlist', [VerifiedMemberController::class, 'waitlist']);
    Route::post('verification/waitlist', [VerifiedMemberController::class, 'joinWaitlist']);
    Route::delete('verification/waitlist', [VerifiedMemberController::class, 'leaveWaitlist']);
    Route::post('verification/start', [VerifiedMemberController::class, 'startKyc']);
    Route::post('verification/submit', [VerifiedMemberController::class, 'submitKyc']);
    Route::post('verification/payment/start', [VerifiedMemberController::class, 'startPayment']);
    Route::post('verification/payment/support', [VerifiedMemberController::class, 'paymentSupport']);
    Route::get('verification/payment/support', [VerifiedMemberController::class, 'paymentSupportCases']);
    Route::post('verification/username/availability', [VerifiedMemberController::class, 'usernameAvailability']);
    Route::post('verification/username', [VerifiedMemberController::class, 'createUsername']);
    Route::post('brc/transfers', [VerifiedMemberController::class, 'transfer']);
    Route::get('brc/transfers', [VerifiedMemberController::class, 'transfers']);
    Route::post('transfer-brc-to-another-user', [VerifiedMemberController::class, 'transfer']);
    
    Route::get('get-fingerprint-status', [ResUserController::class, 'GetFingerPrintStatus']);
    Route::post('updated-fingerprint-status', [ResUserController::class, 'UpdateFingerPrintStatus']);
    
    Route::get('get-booster-mining', [ResUserController::class, 'GetBoosterMining']);
    Route::post('get-booster-mining', [ResUserController::class, 'PostBoosterMining']);
    Route::get('boost-plans', [ResUserController::class, 'boostPlans']);
    Route::get('my-boost', [ResUserController::class, 'myBoost']);
    Route::post('boost-purchase', [ResUserController::class, 'createBoostPurchase']);
    Route::get('boost-purchase/{id}', [ResUserController::class, 'boostPurchaseStatus'])
        ->where('id', '[0-9]+');
    
    Route::post('ads-videos-add-coin',[ResUserController::class,'AdsvideosaddCoin']);
    
    
    Route::get('get-task', [ResUserController::class, 'GetTaskList']);
    Route::post('claimed-task-reward', [ResUserController::class, 'ClaimedTaskReward']);
    
    Route::post('update-device-id', [ResUserController::class, 'UpdateDeviceId']);

    Route::get('partner-campaigns', [PartnerCampaignController::class, 'index']);
    Route::post('partner-campaigns/{id}/click', [PartnerCampaignController::class, 'click'])
        ->where('id', '[0-9]+');
    Route::post('partner-campaigns/{id}/claim', [PartnerCampaignController::class, 'claim'])
        ->where('id', '[0-9]+');
    Route::post('partner-campaigns/{id}/tasks/{taskId}/click', [PartnerCampaignController::class, 'taskClick'])
        ->where(['id' => '[0-9]+', 'taskId' => '[0-9]+']);
    Route::post('partner-campaigns/{id}/tasks/{taskId}/submit', [PartnerCampaignController::class, 'submitTask'])
        ->where(['id' => '[0-9]+', 'taskId' => '[0-9]+']);
    Route::post('partner-campaigns/{id}/tasks/{taskId}/claim', [PartnerCampaignController::class, 'taskClaim'])
        ->where(['id' => '[0-9]+', 'taskId' => '[0-9]+']);

    Route::post('partner-promo-codes/redeem', [PartnerPromoCodeController::class, 'redeem'])
        ->middleware('throttle:10,1');
    Route::get('partner-promo-codes/redemptions', [PartnerPromoCodeController::class, 'redemptions']);
    
});
