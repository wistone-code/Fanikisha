<?php

use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\CheckinController;
use App\Http\Controllers\CommitteeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FinancialController;
use App\Http\Controllers\GuestController;
use App\Http\Controllers\PledgeController;
use App\Http\Controllers\ProviderController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

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

// Public RSVP landing page opened from an activated invitation link — no auth required.
Route::get('/rsvp/{token}', function (string $token) {
    $pledge = \App\Models\Pledge::where('invite_token', $token)->firstOrFail();
    $event = $pledge->event;
    $theme = app(\App\Services\EventThemeService::class)->for($event->event_type);

    return view('guest.rsvp', ['pledge' => $pledge, 'event' => $event, 'theme' => $theme]);
})->name('guest.rsvp');

// Publicly servable event card photo (no auth — guests view this on the RSVP page above).
Route::get('/rsvp/{token}/photo', function (string $token) {
    $pledge = \App\Models\Pledge::where('invite_token', $token)->firstOrFail();
    $event = $pledge->event;

    abort_unless($event->hasCardPhoto(), 404);

    return response($event->card_photo)->header('Content-Type', $event->card_photo_mime);
})->name('guest.rsvp.photo');

// Public "Pay now" page — shows the admin's own mobile money number so the pledger
// can send payment directly, peer-to-peer. Fanikisha never touches the money.
// Uses its own always-present pay_token (unlike invite_token, which only exists
// after a pledge is already paid in full).
Route::get('/pay/{token}', function (string $token) {
    $pledge = \App\Models\Pledge::where('pay_token', $token)->firstOrFail();
    $event = $pledge->event;
    $theme = app(\App\Services\EventThemeService::class)->for($event->event_type);

    return view('guest.pay', ['pledge' => $pledge, 'event' => $event, 'theme' => $theme]);
})->name('guest.pay');

// ---- Authenticated, but not yet past the forced password change ----------------------

Route::middleware('auth')->group(function () {
    Route::get('/password/change', [PasswordChangeController::class, 'show'])->name('password.change.show');
    Route::post('/password/change', [PasswordChangeController::class, 'update'])->name('password.change.update');
});

// ---- Fully authenticated (password already changed) ----------------------------------

Route::middleware(['auth', 'password_changed'])->group(function () {

    Route::post('/account/password', [PasswordChangeController::class, 'updateOwn'])->name('password.own.update');

    // Hit by the session-timeout warning's "Stay signed in" button (see layouts/app.blade.php).
    // A real request here is what resets Laravel's session idle clock — this only fires when
    // a person actually clicks, never automatically, so it can't silently defeat the timeout.
    Route::get('/keep-alive', fn () => response()->noContent())->name('keep-alive');

    // System Admin only — zero visibility into any event's data.
    Route::middleware('super_user')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}/email', [UserManagementController::class, 'updateEmail'])->name('users.email');
        Route::post('/users/{user}/reset-password', [UserManagementController::class, 'resetPassword'])->name('users.reset-password');
        Route::patch('/users/{user}/sms-quota', [UserManagementController::class, 'updateSmsQuota'])->name('users.sms-quota');
        Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');

        Route::get('/account', [UserManagementController::class, 'accountSettings'])->name('account');
        Route::patch('/account/email', [UserManagementController::class, 'updateOwnEmail'])->name('account.email');
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

        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

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

        // Team Management: admin-only, and hidden entirely for Funeral events.
        Route::middleware(['event_admin', 'no_funeral_team'])->group(function () {
            Route::get('/team', [TeamController::class, 'index'])->name('team.index');
            Route::post('/team', [TeamController::class, 'store'])->name('team.store');
            Route::delete('/team/{member}', [TeamController::class, 'destroy'])->name('team.destroy');
            Route::post('/team/{member}/reset-password', [TeamController::class, 'resetPassword'])->name('team.reset-password');
        });

        Route::middleware('event_admin')->group(function () {
            Route::get('/settings', [EventController::class, 'editSettings'])->name('event.settings');
            Route::patch('/settings', [EventController::class, 'updateSettings'])->name('event.settings.update');
            Route::patch('/settings/auto-reminder', [EventController::class, 'updateAutoReminder'])->name('event.settings.auto-reminder');
            Route::post('/settings/card-photo', [EventController::class, 'uploadCardPhoto'])->name('event.settings.card-photo.upload');
            Route::delete('/settings/card-photo', [EventController::class, 'removeCardPhoto'])->name('event.settings.card-photo.remove');
            Route::get('/settings/card-photo', [EventController::class, 'viewCardPhoto'])->name('event.settings.card-photo.view');

            Route::get('/checkin', [CheckinController::class, 'index'])->name('checkin.index');
            Route::post('/checkin/verify', [CheckinController::class, 'verify'])->name('checkin.verify');
            Route::get('/checkin/search', [CheckinController::class, 'search'])->name('checkin.search');
            Route::patch('/settings/payout', [EventController::class, 'updatePayout'])->name('event.settings.payout');

            Route::post('/pledges', [PledgeController::class, 'store'])->name('pledges.store');
            Route::post('/pledges/import', [PledgeController::class, 'import'])->name('pledges.import');
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
            Route::patch('/schedule/message', [ScheduleController::class, 'updateMessage'])->name('schedule.message');
            Route::post('/schedule/broadcast', [ScheduleController::class, 'broadcast'])->name('schedule.broadcast');
            Route::patch('/schedule/{item}', [ScheduleController::class, 'update'])->name('schedule.update');
            Route::delete('/schedule/{item}', [ScheduleController::class, 'destroy'])->name('schedule.destroy');

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