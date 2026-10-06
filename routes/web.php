<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\ComplianceController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\AfterEventController;
use App\Http\Controllers\CardDesignController;
use App\Http\Controllers\CheckinController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\PhotoWallController;
use App\Http\Controllers\SeatingController;
use App\Http\Controllers\CommitteeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FinancialController;
use App\Http\Controllers\GuestCardController;
use App\Http\Controllers\GuestController;
use App\Http\Controllers\PublicPhotoWallController;
use App\Http\Controllers\PledgeController;
use App\Http\Controllers\ProviderController;
use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

// ---- Public pages (no login) ----------------------------------------------------------
// '/' is the public landing page for visitors; signed-in users are sent on to the dashboard.
Route::get('/', [PublicPageController::class, 'home'])->name('home');
Route::get('/privacy', [PublicPageController::class, 'privacy'])->name('privacy');
Route::get('/terms', [PublicPageController::class, 'terms'])->name('terms');
Route::get('/acceptable-use', [PublicPageController::class, 'acceptableUse'])->name('acceptable-use');
Route::get('/data-request', [PublicPageController::class, 'dataRequestForm'])->name('data-request');
Route::post('/data-request', [PublicPageController::class, 'dataRequestStore'])->middleware('throttle:5,10')->name('data-request.store');

// ---- Guest ---------------------------------------------------------------------------

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'authenticate'])->middleware('throttle:login')->name('login.attempt');

    Route::prefix('forgot-password')->name('password.forgot.')->group(function () {
        Route::get('/', [ForgotPasswordController::class, 'showIdentify'])->name('identify');
        Route::post('/', [ForgotPasswordController::class, 'identify'])->middleware('throttle:password-reset')->name('identify.submit');
        Route::get('/verify', [ForgotPasswordController::class, 'showVerify'])->name('verify');
        Route::post('/verify', [ForgotPasswordController::class, 'verify'])->middleware('throttle:password-reset')->name('verify.submit');
        Route::get('/reset', [ForgotPasswordController::class, 'showReset'])->name('reset');
        Route::post('/reset', [ForgotPasswordController::class, 'reset'])->name('reset.submit');
    });
});

Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

// ---- Public guest card (no login — the secret link is the key) -------------------------
Route::get('/rsvp/{token}', [GuestCardController::class, 'show'])->name('guest.rsvp');
Route::post('/rsvp/{token}/stop', [GuestCardController::class, 'stopMessages'])->middleware('throttle:10,1')->name('guest.rsvp.stop');
Route::post('/rsvp/{token}/respond', [GuestCardController::class, 'respond'])->middleware('throttle:30,1')->name('guest.rsvp.respond');
Route::get('/rsvp/{token}/photo', [GuestCardController::class, 'photo'])->name('guest.rsvp.photo');
Route::get('/rsvp/{token}/design', [GuestCardController::class, 'design'])->name('guest.rsvp.design');
Route::get('/rsvp/{token}/music', [GuestCardController::class, 'music'])->name('guest.rsvp.music');
Route::get('/rsvp/{token}/calendar.ics', [GuestCardController::class, 'calendar'])->name('guest.rsvp.calendar');

// Shared photo wall: a public page behind its own secret link (and optional PIN).
Route::get('/wall/{wallToken}', [PublicPhotoWallController::class, 'show'])->name('wall.show');
Route::post('/wall/{wallToken}/pin', [PublicPhotoWallController::class, 'pin'])->middleware('throttle:10,1')->name('wall.pin');
Route::post('/wall/{wallToken}/upload', [PublicPhotoWallController::class, 'upload'])->middleware('throttle:30,1')->name('wall.upload');
Route::post('/wall/{wallToken}/photos/{photo}/report', [PublicPhotoWallController::class, 'report'])->middleware('throttle:20,1')->name('wall.report');
Route::get('/wall/{wallToken}/photos/{photo}/thumb', [PublicPhotoWallController::class, 'thumb'])->name('wall.thumb');
Route::get('/wall/{wallToken}/photos/{photo}', [PublicPhotoWallController::class, 'full'])->name('wall.full');

// Public "Pay now" page — shows the admin's own mobile money number so the pledger
// can send payment directly, peer-to-peer. Fanikisha never touches the money.
// Uses its own always-present pay_token (unlike invite_token, which only exists
// after a pledge is already paid in full).
Route::get('/pay/{token}', function (string $token) {
    $pledge = \App\Models\Pledge::where('pay_token', $token)->firstOrFail();
    $event = $pledge->event;
    $theme = app(\App\Services\EventThemeService::class)->forEvent($event);

    return view('guest.pay', ['pledge' => $pledge, 'event' => $event, 'theme' => $theme]);
})->name('guest.pay');

// ---- Authenticated, but not yet past the forced password change ----------------------

Route::middleware(['auth', 'not_suspended'])->group(function () {
    Route::get('/password/change', [PasswordChangeController::class, 'show'])->name('password.change.show');
    Route::post('/password/change', [PasswordChangeController::class, 'update'])->name('password.change.update');
});

// ---- Fully authenticated (password already changed) ----------------------------------

Route::middleware(['auth', 'not_suspended', 'password_changed'])->group(function () {

    Route::post('/account/password', [PasswordChangeController::class, 'updateOwn'])->name('password.own.update');

    // Hit by the session-timeout warning's "Stay signed in" button (see layouts/app.blade.php).
    // A real request here is what resets Laravel's session idle clock — this only fires when
    // a person actually clicks, never automatically, so it can't silently defeat the timeout.
    Route::get('/keep-alive', fn () => response()->noContent())->name('keep-alive');

    // System Admin only — zero visibility into any event's data.
    Route::middleware('super_user')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/reset-password', [UserManagementController::class, 'resetPassword'])->name('users.reset-password');
        Route::patch('/users/{user}/sms-quota', [UserManagementController::class, 'updateSmsQuota'])->name('users.sms-quota');
        Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');
        Route::post('/users/{user}/toggle-suspend', [UserManagementController::class, 'toggleSuspend'])->name('users.toggle-suspend');
        Route::post('/users/{user}/reassign-event', [UserManagementController::class, 'reassignEvent'])->name('users.reassign-event');

        Route::get('/account', [UserManagementController::class, 'accountSettings'])->name('account');
        Route::patch('/account/email', [UserManagementController::class, 'updateOwnEmail'])->name('account.email');

        Route::get('/logs', [ActivityLogController::class, 'index'])->name('logs.index');

        Route::get('/compliance', [ComplianceController::class, 'index'])->name('compliance');
        Route::post('/compliance/blocked', [ComplianceController::class, 'addBlocked'])->name('compliance.blocked.add');
        Route::delete('/compliance/blocked/{optout}', [ComplianceController::class, 'removeBlocked'])->name('compliance.blocked.remove');
        Route::patch('/compliance/requests/{dataRequest}', [ComplianceController::class, 'updateRequest'])->name('compliance.request');
    });

    // Event self-service creation (accounts are limited to a single event).
    Route::get('/event/create', [EventController::class, 'create'])->name('event.create');
    Route::post('/event', [EventController::class, 'store'])->name('event.store');

    // Everything below requires the account to already have an event (resolve_event
    // middleware redirects to event.create otherwise) — applied via the 'resolve_event'
    // alias here rather than globally, since it needs auth to have already resolved
    // the user. See ResolveCurrentEvent for why.
    //
    // '/' (the dashboard) is deliberately INSIDE this group too, even though it also
    // has to handle super users: DashboardController calls app('currentEvent') directly,
    // and ResolveCurrentEvent is the only thing that ever binds it (as null, safely, for
    // super users — see that middleware). Registering '/' outside this group was a real
    // bug: any regular account visiting the homepage would hit a fatal error instead of
    // a graceful redirect, since app('currentEvent') would never have been bound at all.
    Route::middleware('resolve_event')->group(function () {

        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/financial', [FinancialController::class, 'index'])->name('financial.index');

        Route::prefix('pledges')->name('pledges.')->group(function () {
            Route::get('/', [PledgeController::class, 'index'])->name('index');
            Route::get('/export/excel', [PledgeController::class, 'exportExcel'])->name('export.excel');
            Route::get('/export/pdf', [PledgeController::class, 'exportPdf'])->name('export.pdf');
        });

        Route::prefix('providers')->name('providers.')->group(function () {
            Route::get('/', [ProviderController::class, 'index'])->name('index');
            Route::get('/export/excel', [ProviderController::class, 'exportExcel'])->name('export.excel');
            Route::get('/export/pdf', [ProviderController::class, 'exportPdf'])->name('export.pdf');
        });

        Route::get('/committees', [CommitteeController::class, 'index'])->name('committees.index');

        Route::prefix('schedule')->name('schedule.')->group(function () {
            Route::get('/', [ScheduleController::class, 'index'])->name('index');
            Route::get('/export/excel', [ScheduleController::class, 'exportExcel'])->name('export.excel');
            Route::get('/export/pdf', [ScheduleController::class, 'exportPdf'])->name('export.pdf');
        });

        Route::get('/guests', [GuestController::class, 'index'])->name('guests.index');
        Route::get('/delivery', [DeliveryController::class, 'index'])->name('delivery.index');

        // Check-in: event admins and door staff ("scanner" role).
        Route::middleware('can_checkin')->group(function () {
            Route::get('/checkin', [CheckinController::class, 'index'])->name('checkin.index');
            Route::post('/checkin/verify', [CheckinController::class, 'verify'])->middleware('throttle:120,1')->name('checkin.verify');
            // Offline check-in: download the guest list, then upload queued scans in one batch.
            Route::get('/checkin/guest-list', [CheckinController::class, 'guestList'])->name('checkin.guest-list');
            Route::get('/checkin/token', [CheckinController::class, 'freshToken'])->name('checkin.token');
            Route::post('/checkin/sync', [CheckinController::class, 'sync'])->middleware('throttle:60,1')->name('checkin.sync');
            Route::get('/checkin/search', [CheckinController::class, 'search'])->middleware('throttle:120,1')->name('checkin.search');
            Route::get('/checkin/stats', [CheckinController::class, 'stats'])->name('checkin.stats');
        });

        // Team Management: admin-only, and hidden entirely for Funeral events.
        Route::middleware(['event_admin', 'no_funeral_team'])->group(function () {
            Route::get('/team', [TeamController::class, 'index'])->name('team.index');
            Route::post('/team', [TeamController::class, 'store'])->name('team.store');
            Route::delete('/team/{member}', [TeamController::class, 'destroy'])->name('team.destroy');
            Route::post('/team/{member}/toggle-disabled', [TeamController::class, 'toggleDisabled'])->name('team.toggle-disabled');
            Route::post('/team/{member}/reset-password', [TeamController::class, 'resetPassword'])->name('team.reset-password');
        });

        Route::middleware('event_admin')->group(function () {
            Route::patch('/account/username', [EventController::class, 'updateOwnUsername'])->name('account.username.update');
            Route::patch('/account/email', [EventController::class, 'updateOwnEmail'])->name('account.email.update');
            Route::patch('/account/phone', [EventController::class, 'updateOwnPhone'])->name('account.phone.update');

            Route::get('/settings', [EventController::class, 'editSettings'])->name('event.settings');
            Route::patch('/settings', [EventController::class, 'updateSettings'])->name('event.settings.update');
            Route::patch('/settings/auto-reminder', [EventController::class, 'updateAutoReminder'])->name('event.settings.auto-reminder');
            Route::post('/settings/card-photo', [EventController::class, 'uploadCardPhoto'])->name('event.settings.card-photo.upload');
            Route::delete('/settings/card-photo', [EventController::class, 'removeCardPhoto'])->name('event.settings.card-photo.remove');
            Route::get('/settings/card-photo', [EventController::class, 'viewCardPhoto'])->name('event.settings.card-photo.view');

            Route::get('/checkin/door-list', [CheckinController::class, 'doorList'])->name('checkin.door-list');
            Route::delete('/checkin/{pledge}', [CheckinController::class, 'undoCheckin'])->name('checkin.undo');
            Route::patch('/settings/checkin-confirm', [EventController::class, 'updateCheckinConfirm'])->name('event.settings.checkin-confirm');
            Route::patch('/settings/payout', [EventController::class, 'updatePayout'])->name('event.settings.payout');
            Route::patch('/settings/couple-threshold', [EventController::class, 'updateCoupleThreshold'])->name('event.settings.couple-threshold');
            Route::patch('/settings/theme-color', [EventController::class, 'updateThemeColor'])->name('event.settings.theme-color');
            Route::patch('/settings/sms-language', [EventController::class, 'updateSmsLanguage'])->name('event.settings.sms-language');

            Route::post('/pledges', [PledgeController::class, 'store'])->name('pledges.store');
            Route::post('/pledges/import', [PledgeController::class, 'import'])->name('pledges.import');
            Route::post('/pledges/import-photo', [PledgeController::class, 'importPhoto'])->name('pledges.import-photo');
            Route::patch('/pledges/{pledge}', [PledgeController::class, 'update'])->name('pledges.update');
            Route::delete('/pledges/{pledge}', [PledgeController::class, 'destroy'])->name('pledges.destroy');
            Route::patch('/pledges/message/reminder', [PledgeController::class, 'updateReminderMessage'])->name('pledges.message.reminder');
            Route::patch('/pledges/message/broadcast', [PledgeController::class, 'updateBroadcastMessage'])->name('pledges.message.broadcast');
            Route::post('/pledges/{pledge}/remind/sms', [PledgeController::class, 'remindSms'])->name('pledges.remind.sms');
            Route::get('/pledges/{pledge}/remind/whatsapp', [PledgeController::class, 'remindWhatsApp'])->name('pledges.remind.whatsapp');
            Route::post('/pledges/remind-all/sms', [PledgeController::class, 'remindAllSms'])->name('pledges.remind-all.sms');

            Route::post('/providers', [ProviderController::class, 'store'])->name('providers.store');
            Route::patch('/providers/message', [ProviderController::class, 'updateMessage'])->name('providers.message');
            Route::patch('/providers/{provider}', [ProviderController::class, 'update'])->name('providers.update');
            Route::delete('/providers/{provider}', [ProviderController::class, 'destroy'])->name('providers.destroy');
            Route::post('/providers/{provider}/sms', [ProviderController::class, 'sendSms'])->name('providers.sms');
            Route::post('/providers/{provider}/confirm-payment/sms', [ProviderController::class, 'confirmPaymentSms'])->name('providers.confirm-payment.sms');
            Route::get('/providers/{provider}/whatsapp', [ProviderController::class, 'sendWhatsApp'])->name('providers.whatsapp');

            Route::post('/committees', [CommitteeController::class, 'store'])->name('committees.store');
            Route::patch('/committees/message', [CommitteeController::class, 'updateMessage'])->name('committees.message');
            Route::patch('/committees/{committee}', [CommitteeController::class, 'update'])->name('committees.update');
            Route::delete('/committees/{committee}', [CommitteeController::class, 'destroy'])->name('committees.destroy');
            Route::delete('/committees/members/{member}', [CommitteeController::class, 'destroyMember'])->name('committees.members.destroy');
            Route::patch('/committees/members/{member}', [CommitteeController::class, 'updateMember'])->name('committees.members.update');
            Route::post('/committees/members/{member}/sms', [CommitteeController::class, 'notifySms'])->name('committees.members.sms');
            Route::get('/committees/members/{member}/whatsapp', [CommitteeController::class, 'notifyWhatsApp'])->name('committees.members.whatsapp');

            Route::post('/schedule', [ScheduleController::class, 'store'])->name('schedule.store');
            Route::post('/schedule/import-photo', [ScheduleController::class, 'importPhoto'])->name('schedule.import-photo');
            Route::post('/schedule/import-text', [ScheduleController::class, 'importText'])->name('schedule.import-text');
            Route::post('/schedule/broadcast', [ScheduleController::class, 'broadcast'])->name('schedule.broadcast');
            Route::patch('/schedule/{item}', [ScheduleController::class, 'update'])->name('schedule.update');
            Route::delete('/schedule/{item}', [ScheduleController::class, 'destroy'])->name('schedule.destroy');

            // E-card-only accounts: add / import / edit / remove guests (each gets a live card link).
            Route::post('/guests', [GuestController::class, 'storeGuest'])->name('guests.store');
            Route::post('/guests/import', [GuestController::class, 'importGuests'])->name('guests.import');
            Route::patch('/guests/{pledge}', [GuestController::class, 'updateGuest'])->name('guests.update');
            Route::delete('/guests/{pledge}', [GuestController::class, 'destroyGuest'])->name('guests.destroy');

            Route::get('/guests-export', [DeliveryController::class, 'export'])->name('guests.export');
            Route::post('/delivery/send-all', [DeliveryController::class, 'sendAll'])->name('delivery.send-all');
            Route::post('/delivery/remind-unopened', [DeliveryController::class, 'remindUnopened'])->name('delivery.remind-unopened');
            Route::patch('/delivery/auto', [DeliveryController::class, 'updateAuto'])->name('delivery.auto');
            Route::post('/delivery/{pledge}/mark-sent', [DeliveryController::class, 'markSent'])->name('delivery.mark-sent');
            Route::post('/delivery/{pledge}/revoke', [DeliveryController::class, 'revoke'])->name('delivery.revoke');
            Route::post('/delivery/{pledge}/reissue', [DeliveryController::class, 'reissue'])->name('delivery.reissue');
            Route::get('/delivery/{pledge}/remind-wa', [DeliveryController::class, 'remindWhatsApp'])->name('delivery.remind-wa');

            // RSVP extras (plus-ones, meals…) and the seating plan.
            Route::patch('/rsvp/settings', [GuestController::class, 'updateRsvpSettings'])->name('rsvp.settings');
            Route::get('/seating', [SeatingController::class, 'index'])->name('seating.index');
            Route::patch('/seating/mode', [SeatingController::class, 'updateMode'])->name('seating.mode');
            Route::patch('/seating/publish', [SeatingController::class, 'publish'])->name('seating.publish');
            Route::post('/seating/areas', [SeatingController::class, 'storeArea'])->name('seating.areas.store');
            Route::delete('/seating/areas/{area}', [SeatingController::class, 'destroyArea'])->name('seating.areas.destroy');
            Route::post('/seating/tables', [SeatingController::class, 'storeTable'])->name('seating.tables.store');
            Route::patch('/seating/tables/{table}', [SeatingController::class, 'updateTable'])->name('seating.tables.update');
            Route::delete('/seating/tables/{table}', [SeatingController::class, 'destroyTable'])->name('seating.tables.destroy');
            Route::patch('/seating/assign/{pledge}', [SeatingController::class, 'assign'])->name('seating.assign');
            Route::post('/seating/auto-fill', [SeatingController::class, 'autoFill'])->name('seating.auto-fill');

            // Shared photo wall (admin side).
            Route::get('/photos', [PhotoWallController::class, 'index'])->name('photos.index');
            Route::patch('/photos/settings', [PhotoWallController::class, 'update'])->name('photos.update');
            Route::post('/photos/new-link', [PhotoWallController::class, 'newLink'])->name('photos.new-link');
            Route::post('/photos/{photo}/toggle-hidden', [PhotoWallController::class, 'toggleHidden'])->name('photos.toggle-hidden');
            Route::delete('/photos/{photo}', [PhotoWallController::class, 'destroy'])->name('photos.destroy');
            Route::get('/photos/{photo}/thumb', [PhotoWallController::class, 'thumb'])->name('photos.thumb');
            Route::get('/photos/download', [PhotoWallController::class, 'download'])->name('photos.download');

            // Card design, venue and event-day reminder.
            Route::get('/design', [CardDesignController::class, 'index'])->name('design.index');
            Route::patch('/design/card', [CardDesignController::class, 'updateCard'])->name('design.card');
            Route::post('/design/music', [CardDesignController::class, 'uploadMusic'])->name('design.music.upload');
            Route::delete('/design/music', [CardDesignController::class, 'removeMusic'])->name('design.music.remove');
            Route::post('/design/custom', [CardDesignController::class, 'uploadDesign'])->name('design.custom.upload');
            Route::patch('/design/custom', [CardDesignController::class, 'updateLayout'])->name('design.custom.layout');
            Route::delete('/design/custom', [CardDesignController::class, 'removeDesign'])->name('design.custom.remove');
            Route::get('/design/custom-image', [CardDesignController::class, 'designImage'])->name('design.custom.image');
            Route::patch('/design/venue', [CardDesignController::class, 'updateVenue'])->name('design.venue');
            Route::patch('/design/day-reminder', [CardDesignController::class, 'updateDayReminder'])->name('design.day-reminder');
            Route::post('/design/day-reminder/send-now', [CardDesignController::class, 'sendDayReminderNow'])->name('design.day-reminder.send');

            // After the event: thank-you messages and the recap.
            Route::get('/after', [AfterEventController::class, 'index'])->name('after.index');
            Route::patch('/after/settings', [AfterEventController::class, 'update'])->name('after.update');
            Route::post('/after/send-now', [AfterEventController::class, 'sendNow'])->name('after.send');
            Route::get('/after/recap', [AfterEventController::class, 'recap'])->name('after.recap');

            Route::post('/guests/{pledge}/send-invite', [GuestController::class, 'sendInvite'])->name('guests.send-invite');
            Route::post('/guests/{pledge}/sms', [GuestController::class, 'inviteSms'])->name('guests.sms');
            Route::get('/guests/{pledge}/whatsapp', [GuestController::class, 'inviteWhatsApp'])->name('guests.whatsapp');
            Route::patch('/guests/message/invitation', [GuestController::class, 'updateInvitationMessage'])->name('guests.message.invitation');
            Route::post('/guests/meeting/broadcast-sms', [GuestController::class, 'meetingBroadcastSms'])->name('guests.meeting.broadcast-sms');
            Route::patch('/guests/message/meeting', [GuestController::class, 'updateMeetingMessage'])->name('guests.message.meeting');
            Route::patch('/guests/message/announcement', [GuestController::class, 'updateAnnouncementMessage'])->name('guests.message.announcement');
            Route::post('/guests/broadcast-sms', [GuestController::class, 'broadcastSms'])->name('guests.broadcast-sms');
        });
    });
});