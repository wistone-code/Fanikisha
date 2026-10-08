@extends('layouts.app')
@section('title', 'User Manual — '.config('app.name'))

@section('content')
@php
    $money = $event->hasFeature('money');
    $cards = $event->hasFeature('cards');
    $funeral = $event->event_type === 'Funeral';
    $s = [];
    $s['start'] = ['Getting started', '
        <p>Sign in with the username and password from your System Administrator. If you forget your password, tap <b>Forgot password</b>, enter your username and email, choose a code by SMS or email, type the 6-digit code and set a new one. You can ask for 5 codes an hour and each expires after 15 minutes.</p>
        <p>To install the app on a phone: in Chrome (Android) choose <b>Add to Home screen</b>; on iPhone use Safari, <b>Share</b>, then <b>Add to Home Screen</b>.</p>'];
    $s['menu'] = ['Menu and your account', '
        <p>The menu only shows what your package and role allow. Home shows your event banner and shortcuts, including this manual.'.($cards && $isAdmin ? ' You also have <b>Event Photos</b>, just before Setting.' : '').' Admins can change their username, email, phone and password from <b>Account settings</b> (top right); each change asks for your current password, and changing your password signs you out of other devices.</p>
        <p class="tip">Tip: save your phone number so you can recover your password by SMS. On an Android phone with Chrome, a small address-book button next to each phone field lets you pick a number from your contacts.</p>'];
    if ($isAdmin) $s['setup'] = ['Setting up your event', '
        <p>Open <b>Setting</b>. You can change the event name, type, place and date'.($cards && !$money ? '' : ', the pledge deadline').', the theme color, and the SMS language (English or Swahili; a message you wrote yourself is always sent as written).</p>
        <ul>'.($money ? '<li><b>Mobile money number and network</b>: where pledgers send payments.</li>' : '').($cards && !$funeral ? '<li><b>Invitation photo</b>: JPG, PNG or WebP up to 5 MB, shown on the guest card.</li>' : '').($cards && $money && !$funeral ? '<li><b>Card setting</b>: the amount from which a pledger gets a double card.</li>' : '').($money && !$funeral ? '<li><b>Automatic reminders</b>: every N days at a time you choose.</li>' : '').'</ul>
        <p>Changing the event date makes the day-of and thank-you messages ready to send again.</p>'];
    if ($money) $s['pledges'] = [$funeral ? 'Condolences' : 'Pledges', '
        <ul><li><b>Add</b> a person, or <b>Import</b> a CSV, text or Word file, or pasted list (<code>Name, Phone, Amount</code>). <b>Import from photo</b> reads up to 4 photos of a list; check the result before saving.</li>
        <li>Open a person with <b>Edit</b> to <b>Add payment</b>, or <b>Correct total paid</b> to set the exact total.</li>
        <li><b>Export</b> downloads an Excel file.</li></ul>
        <p>People who asked not to be messaged are marked <b>Will not be messaged</b>.</p>'];
    if ($money && !$funeral) $s['reminders'] = ['Reminders and Pay now', '
        <p>Use the <b>Reminder</b> tab to message one person, or <b>SMS all</b> for everyone. Fields you can use: <code>{name}</code> <code>{event}</code> <code>{pledged}</code> <code>{paid}</code> <code>{remain}</code> <code>{pay_link}</code>. Each SMS counts against your quota on a Contributions account.</p>
        <p>The <code>{pay_link}</code> opens a personal <b>Pay now</b> page showing what the person pledged, paid and owes, with your mobile money number. After they pay, record it with <b>Add payment</b>.</p>'];
    if ($money) $s['finance'] = ['Financial status and service providers', '
        <p><b>Financial Status</b> shows total pledged, collected and balance, and who has paid fully, partly or not yet'.($funeral ? '; for a Funeral it shows budget, expenditure, condolences collected and variance' : '').'. <b>Service Provider</b> lists the people you pay for the event, with amounts agreed and paid.</p>'];
    if ($money) $s['people'] = ['Event Management and Schedule', '
        <p><b>Event Management</b> keeps your committees and their members. <b>Schedule</b> is the event timeline with dates and times.</p>'];
    if (!$cards && $money) $s['sms-guests'] = [$funeral ? 'Announcement' : 'Guest Management', '
        <p><b>Event invitation</b> sends the invitation to every pledger by SMS. <b>Meeting invitation</b> sends one SMS to all pledgers; you can edit it and use <code>{event}</code> <code>{place}</code> <code>{date}</code>. Each SMS uses your quota (shown in Setting).</p>'];
    if ($cards) $s['ecards'] = ['Guest e-cards', '
        <p>'.($money ? 'Every pledger is on the guest list; press <b>Activate</b> or <b>Send invite</b> to give them a live card, or invite someone new.' : 'Add guests one by one or import <code>Name, Phone, Card</code> (single or double). Each card link is live immediately.').' Send by SMS, WhatsApp or copy the link. If a link was shared by mistake use <b>Reset link</b>. Card message fields: <code>{name}</code> <code>{event_name}</code> <code>{event_type}</code> <code>{date}</code> <code>{place}</code> <code>{link}</code> <code>{code}</code> <code>{wall_link}</code>.</p>
        <p>The <b>SMS</b> invitation carries the guest\'s entry code and no link, so it works on any phone. WhatsApp and Copy link carry the card link. If your saved message contains <code>{link}</code>, SMS sends the standard text with the code instead.</p>'];
    if ($cards && $isAdmin) $s['design'] = ['Card design and venue', '
        <p>In <b>Card design</b> pick a template and card language, write the messages in English and Swahili, add a YouTube video and music (mp3 link or a file up to 10 MB), or upload your own design and set where the name and QR go. Set the venue: start time, name, address, landmark and map pin. The event-day reminder goes out at a time you choose, or press <b>Send now</b> (<code>{name}</code> <code>{event}</code> <code>{date}</code> <code>{time}</code> <code>{place}</code> <code>{link}</code>).</p>'];
    if ($cards) $s['delivery'] = ['Delivery and RSVP', '
        <p><b>Delivery</b> tracks each guest: not sent, sent, opened, responded, arrived. Use <b>Send to all not yet sent</b> and <b>Remind unopened</b> (an automatic reminder also goes out N days before the event). <b>Possibly forwarded</b> means a card was opened on several phones. <b>Export CSV</b> and <b>Door list</b> give you lists.</p>
        <p><b>RSVP</b> shows attending, not attending and awaiting, people expected and meals. You can allow plus-ones, meal choice, dietary needs, a message to the hosts and a cut-off date. <b>Reset</b> lets a guest answer again. If a guest cannot open the link, admins can press <b>Mark yes</b> or <b>Mark no</b> next to their name when the guest tells you by phone, SMS or in person.</p>'];
    if ($cards && $isAdmin) $s['seating'] = ['Seating plan', '
        <p>Choose a style (none, zones, tables or rows), add areas with a capacity, then assign guests or press <b>Seat everyone without a place</b>. Switch on <b>Publish</b> to show seats on cards and at the door.</p>'];
    if ($cards) $s['checkin'] = ['Check-in at the entrance', '
        <p>Scan the guest QR code or search by name, card code or phone. A guest on a basic phone only needs to read out the entry code from the SMS. Turn on <b>Confirm name before checking in</b> if staff should confirm each guest. The arrival log refreshes every 20 seconds and lets you undo a mistake.</p>
        <p>No network? Press <b>Prepare for offline</b> before the event, then <b>Sync now</b> when back online. Guests already checked in on another phone show as <b>Already checked in elsewhere</b>. On iPhone, install the app first.</p>'];
    if ($cards && $isAdmin) $s['photos'] = ['Event Photos', '
        <p>Open <b>Event Photos</b> from the menu or the Home screen (just before Setting). On the guest card the button reads <b>Upload photo</b>. Turn the wall on, choose link or guests-only access, an optional PIN, when uploads open, a per-guest and total limit, and when it closes. <b>Pause uploads</b> stops new photos; <b>Make a new link</b> replaces a shared link; hide photos or review reports; <b>Download all</b> saves a ZIP.</p>'];
    if ($cards && $isAdmin) $s['after'] = ['After the event', '
        <p>Write a thank-you for guests who came and another for those who could not (sign with <code>{hosts}</code>). It goes out automatically the next morning, or press <b>Send thank-yous now</b>. The printable <b>Recap</b> shows cards sent and opened, replies, arrivals, no-shows, walk-ins and meals'.($money ? ', and contributions' : '').'.</p>'];
    if ($isAdmin && !($funeral && !$cards)) $s['team'] = ['Team management', '
        <p>Roles: <b>Admin</b> (everything), <b>Viewer</b> (look only) and <b>Door staff</b> (check-in only). <b>Add member</b> emails them a temporary password. You can change a role, reset a password, disable, enable or remove a member. Everyone on the event shares its package; the System Administrator changes it.</p>'];
    $s['help'] = ['Need help?', '
        <ul><li>A menu item is missing: it is not part of your package or role.</li>
        <li>SMS not sending: check your SMS quota in Setting and whether the number is marked Will not be messaged.</li>
        <li>Still stuck: email info@fanikisha.app.</li></ul>'];
@endphp

<div class="max-w-3xl mx-auto manual">
    <a href="{{ route('dashboard') }}" class="text-sm text-gray-500"><i class="fa-solid fa-arrow-left"></i> Home</a>
    <h1 class="text-2xl font-semibold mt-2 mb-1">User Manual</h1>
    <p class="text-sm text-gray-500 mb-5">This guide shows only what your package and role can use.</p>

    <div class="card p-4 mb-5 text-sm">
        <div class="font-semibold mb-2">Contents</div>
        <ol class="list-decimal pl-5 space-y-1">
            @foreach ($s as $id => [$title])
            <li><a href="#m-{{ $id }}" style="color:var(--primary);">{{ $title }}</a></li>
            @endforeach
        </ol>
    </div>

    @foreach ($s as $id => [$title, $body])
    <section id="m-{{ $id }}" class="card p-5 mb-4 text-sm leading-relaxed space-y-2">
        <h2 class="text-lg font-semibold">{{ $title }}</h2>
        {!! $body !!}
    </section>
    @endforeach
</div>
<style>.manual ul{list-style:disc;padding-left:1.25rem}.manual code{background:#eef2f5;padding:0 .25rem;border-radius:3px}.manual .tip{color:#555;font-style:italic}</style>
@endsection
