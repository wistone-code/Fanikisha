@extends('layouts.public')
@php
    $c = config('company');
    $sw = $lang === 'sw';
    $t = fn ($en, $swh) => $sw ? $swh : $en;
    $features = [
        ['fa-envelope-open-text', $t('Digital invitation cards', 'Kadi za mwaliko za kidijitali'), $t('Beautiful e-cards for weddings, funerals, graduations and more. Each guest gets a personal link and a QR code.', 'Kadi nzuri za harusi, misiba, mahafali na zaidi. Kila mgeni anapata kiungo chake na msimbo wa QR.')],
        ['fa-circle-check', $t('RSVP in one tap', 'RSVP kwa mguso mmoja'), $t('Guests confirm attendance, party size and meal choice from their phone. You see answers update live.', 'Wageni wanathibitisha kuhudhuria, idadi na chakula kupitia simu. Unaona majibu moja kwa moja.')],
        ['fa-qrcode', $t('Door check-in', 'Kuingia mlangoni'), $t('Scan QR codes or search by name, card code or phone. Works offline when the signal is weak.', 'Changanua QR au tafuta kwa jina, msimbo wa kadi au simu. Inafanya kazi bila mtandao.')],
        ['fa-comment-sms', $t('SMS delivery & reminders', 'Kutuma SMS na vikumbusho'), $t('Send cards by SMS and remind guests who have not opened them yet. Each guest is messaged only when you choose.', 'Tuma kadi kwa SMS na ukumbushe wasiofungua. Kila mgeni anapokea ujumbe pale tu unapochagua.')],
        ['fa-hand-holding-dollar', $t('Contributions & pledges', 'Michango na ahadi'), $t('Track pledges and payments, send payment reminders and see exactly who has paid and who still owes.', 'Fuatilia ahadi na malipo, tuma vikumbusho na ona nani amelipa na nani bado.')],
        ['fa-images', $t('Photo wall & thank-yous', 'Ukuta wa picha na shukrani'), $t('Guests share photos from the event, and you thank everyone who came with one message.', 'Wageni wanashiriki picha za tukio, nawe unawashukuru wote waliohudhuria kwa ujumbe mmoja.')],
    ];
    $uses = [$t('Weddings', 'Harusi'), $t('Send-offs & kitchen parties', 'Send-off na kitchen party'), $t('Funerals & memorials', 'Misiba na kumbukumbu'), $t('Graduations', 'Mahafali'), $t('Fundraisers', 'Harambee'), $t('Birthdays & corporate events', 'Sherehe na matukio ya kampuni')];
@endphp
@section('title', 'Fanikisha — '.$t('Event invitations, RSVP & contributions', 'Mialiko, RSVP na michango ya matukio'))

@section('content')
@if (session('account_requested'))
    <div class="bg-green-50 text-green-800 border-b border-green-200">
        <div class="max-w-5xl mx-auto px-5 py-3 text-sm text-center">
            <strong>{{ $t('Thank you.', 'Asante.') }}</strong> {{ $t('We received your request and will contact you within 2 working days.', 'Tumepokea ombi lako na tutawasiliana nawe ndani ya siku 2 za kazi.') }}
        </div>
    </div>
@endif
<section class="bg-[#1F3A52] text-white">
    <div class="max-w-5xl mx-auto px-5 py-14 sm:py-20 text-center">
        <p class="text-xs tracking-widest uppercase text-sky-200 mb-3">{{ $t('Your Event Partner', 'Mshirika wako wa matukio') }}</p>
        <h1 class="text-3xl sm:text-5xl leading-tight font-bold mb-4">{{ $t('Invite, confirm and welcome your guests — from one phone.', 'Alika, thibitisha na wapokee wageni wako — kwa simu moja.') }}</h1>
        <p class="text-sky-100 max-w-2xl mx-auto sm:text-lg leading-relaxed mb-8">{{ $t('Fanikisha helps event organisers in Tanzania send digital invitation cards, collect RSVPs, check guests in at the door and manage contributions — built to work on any phone, even on slow networks.', 'Fanikisha inawasaidia waandaaji wa matukio Tanzania kutuma kadi za mwaliko za kidijitali, kukusanya RSVP, kuwapokea wageni mlangoni na kusimamia michango — inafanya kazi kwenye simu yoyote, hata mtandao ukiwa polepole.') }}</p>
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="{{ route('account-request', ['lang' => $sw ? 'sw' : null]) }}" class="pbtn pbtn-light"><i class="fa-solid fa-user-plus"></i> {{ $t('Request an account', 'Omba akaunti') }}</a>
        </div>
    </div>
</section>

<section class="max-w-5xl mx-auto px-5 pt-14">
    <h2 class="text-2xl sm:text-3xl text-center font-bold mb-2">{{ $t('Everything for your event', 'Kila kitu kwa tukio lako') }}</h2>
    <p class="text-center text-gray-500 mb-8">{{ $t('From the first invitation to the thank-you message.', 'Tangu mwaliko wa kwanza hadi ujumbe wa shukrani.') }}</p>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($features as [$icon, $title, $text])
            <div class="border border-gray-100 rounded-2xl p-5 shadow-sm">
                <div class="w-10 h-10 rounded-xl bg-[#1F3A52]/10 text-[#1F3A52] flex items-center justify-center mb-3"><i class="fa-solid {{ $icon }}"></i></div>
                <h3 class="font-semibold mb-1">{{ $title }}</h3>
                <p class="text-sm text-gray-600 leading-relaxed">{{ $text }}</p>
            </div>
        @endforeach
    </div>
</section>

<section class="max-w-5xl mx-auto px-5 pt-14">
    <h2 class="text-2xl sm:text-3xl text-center font-bold mb-8">{{ $t('How it works', 'Inavyofanya kazi') }}</h2>
    <div class="grid gap-4 sm:grid-cols-3 text-center">
        @foreach ([[$t('Add your guests', 'Ongeza wageni'), $t('Paste a list or add names and phone numbers one by one.', 'Bandika orodha au ongeza majina na namba za simu.')], [$t('Send the cards', 'Tuma kadi'), $t('Each guest receives a personal invitation link by SMS or from your own WhatsApp.', 'Kila mgeni anapokea kiungo chake kwa SMS au kupitia WhatsApp yako.')], [$t('Welcome them', 'Wapokee'), $t('Guests reply, you track RSVPs, and scan their QR code at the entrance.', 'Wageni wanajibu, unafuatilia RSVP, na unachanganua QR mlangoni.')]] as $i => [$h, $p])
            <div class="rounded-2xl bg-gray-50 p-6">
                <div class="w-9 h-9 mx-auto rounded-full bg-[#1F3A52] text-white font-bold flex items-center justify-center mb-3">{{ $i + 1 }}</div>
                <h3 class="font-semibold mb-1">{{ $h }}</h3>
                <p class="text-sm text-gray-600">{{ $p }}</p>
            </div>
        @endforeach
    </div>
</section>

<section class="max-w-5xl mx-auto px-5 pt-14 text-center">
    <h2 class="text-2xl font-bold mb-4">{{ $t('Made for every kind of gathering', 'Imeundwa kwa kila aina ya tukio') }}</h2>
    <div class="flex flex-wrap justify-center gap-2">
        @foreach ($uses as $u)<span class="px-4 py-2 rounded-full bg-[#1F3A52]/10 text-[#1F3A52] text-sm font-medium">{{ $u }}</span>@endforeach
    </div>
</section>

<section class="max-w-3xl mx-auto px-5 pt-14">
    <div class="rounded-2xl border border-gray-200 p-6">
        <h2 class="text-lg font-bold mb-2"><i class="fa-brands fa-whatsapp text-green-600"></i> {{ $t('How we use messaging', 'Jinsi tunavyotumia ujumbe') }}</h2>
        <p class="text-sm text-gray-600 leading-relaxed">{{ $t('Fanikisha only sends messages that an event organiser asks us to send, to guests the organiser has added — invitations, reminders about an event or a pledge, and thank-you notes. We do not send marketing messages or sell phone numbers. Anyone who does not want further messages can tell the organiser or write to us, and we will exclude them. See our', 'Fanikisha hutuma tu ujumbe ambao mwandaaji wa tukio ameomba, kwa wageni aliowaongeza — mialiko, vikumbusho vya tukio au ahadi, na ujumbe wa shukrani. Hatutumi matangazo wala kuuza namba za simu. Mtu asiyetaka ujumbe zaidi anaweza kumwambia mwandaaji au kutuandikia, nasi tutamwondoa. Tazama') }}
            <a href="{{ route('privacy', ['lang' => $sw ? 'sw' : null]) }}" class="underline text-[#1F3A52]">{{ $t('Privacy Policy', 'Sera ya Faragha') }}</a>.</p>
    </div>
</section>

<section class="max-w-3xl mx-auto px-5 pt-10">
    <div class="rounded-2xl bg-[#1F3A52] text-white p-8 text-center">
        <h2 class="text-2xl font-bold mb-2">{{ $t('Ready to plan your event?', 'Uko tayari kuandaa tukio lako?') }}</h2>
        <p class="text-sky-100 mb-5 text-sm">{{ $t('Tell us about your event and we will set up your organiser account.', 'Tuambie kuhusu tukio lako tukufungulie akaunti ya mwandaaji.') }}</p>
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="{{ route('account-request', ['lang' => $sw ? 'sw' : null]) }}" class="pbtn pbtn-light"><i class="fa-solid fa-user-plus"></i> {{ $t('Request an account', 'Omba akaunti') }}</a>
            <a href="mailto:{{ $c['email'] }}" class="pbtn border border-white/30 text-white"><i class="fa-solid fa-envelope"></i> {{ $c['email'] }}</a>
            @if ($c['phone'])<a href="tel:{{ preg_replace('/\s+/', '', $c['phone']) }}" class="pbtn border border-white/30 text-white"><i class="fa-solid fa-phone"></i> {{ $c['phone'] }}</a>@endif
        </div>
    </div>
</section>
@endsection
