@extends('layouts.public')
@php
    $c = config('company'); $sw = $lang === 'sw';
    $t = fn ($en, $swh) => $sw ? $swh : $en;
    $types = ['wedding' => $t('Wedding', 'Harusi'), 'sendoff' => $t('Send-off / kitchen party', 'Send-off / kitchen party'), 'funeral' => $t('Funeral / memorial', 'Msiba / kumbukumbu'), 'graduation' => $t('Graduation', 'Mahafali'), 'fundraiser' => $t('Fundraiser', 'Harambee'), 'birthday' => $t('Birthday', 'Sherehe ya kuzaliwa'), 'corporate' => $t('Corporate event', 'Tukio la kampuni'), 'other' => $t('Something else', 'Jambo lingine')];
@endphp
@section('title', $t('Request an account', 'Omba akaunti').' — '.$c['brand'])
@section('description', 'Tell us about your event and we will set up your Fanikisha organiser account.')

@section('content')
<div class="max-w-xl mx-auto px-5 pt-10">
    <h1 class="text-3xl font-bold mb-2">{{ $t('Request an account', 'Omba akaunti') }}</h1>
    <p class="text-gray-600 text-sm mb-6">{{ $t('Tell us a little about your event. We will contact you within 2 working days and set up your organiser account. It is free to ask — nothing is charged by sending this form.', 'Tuambie kidogo kuhusu tukio lako. Tutawasiliana nawe ndani ya siku 2 za kazi na kukufungulia akaunti ya mwandaaji. Kuomba ni bure — hakuna malipo kwa kutuma fomu hii.') }}</p>

    <form method="POST" action="{{ route('account-request.store', ['lang' => $sw ? 'sw' : null]) }}" class="space-y-4">
        @csrf
        <div style="position:absolute;left:-9999px;" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
        <div>
            <label class="text-xs font-semibold" for="name">{{ $t('Your full name', 'Jina lako kamili') }} *</label>
            <input id="name" name="name" value="{{ old('name') }}" maxlength="120" required autocomplete="name" class="w-full border rounded-lg px-3 py-2.5 text-base mt-1">
            @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="text-xs font-semibold" for="phone">{{ $t('Phone number (we will call or WhatsApp you)', 'Namba ya simu (tutakupigia au WhatsApp)') }} *</label>
            <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" value="{{ old('phone') }}" maxlength="30" required placeholder="07XX XXX XXX" class="w-full border rounded-lg px-3 py-2.5 text-base mt-1">
            @error('phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="text-xs font-semibold" for="email">{{ $t('Email (we send your login details here)', 'Barua pepe (tutatuma taarifa za kuingia hapa)') }}</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" maxlength="150" autocomplete="email" class="w-full border rounded-lg px-3 py-2.5 text-base mt-1">
            @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="text-xs font-semibold" for="event_type">{{ $t('What kind of event?', 'Tukio ni la aina gani?') }} *</label>
            <select id="event_type" name="event_type" class="w-full border rounded-lg px-3 py-2.5 text-base mt-1" required>
                @foreach ($types as $v => $label)
                    <option value="{{ $v }}" @selected(old('event_type', 'wedding') === $v)>{{ $label }}</option>
                @endforeach
            </select>
            @error('event_type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="text-xs font-semibold" for="event_date">{{ $t('Event date', 'Tarehe ya tukio') }}</label>
                <input id="event_date" type="date" name="event_date" value="{{ old('event_date') }}" min="{{ now()->toDateString() }}" class="w-full border rounded-lg px-3 py-2.5 text-base mt-1">
                @error('event_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="text-xs font-semibold" for="guests">{{ $t('Guests (about)', 'Wageni (takriban)') }}</label>
                <input id="guests" type="number" inputmode="numeric" min="1" max="100000" name="guests" value="{{ old('guests') }}" class="w-full border rounded-lg px-3 py-2.5 text-base mt-1">
                @error('guests')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>
        <div>
            <label class="text-xs font-semibold" for="location">{{ $t('Town / venue', 'Mji / ukumbi') }}</label>
            <input id="location" name="location" value="{{ old('location') }}" maxlength="150" class="w-full border rounded-lg px-3 py-2.5 text-base mt-1">
        </div>
        <fieldset>
            <legend class="text-xs font-semibold">{{ $t('What do you need?', 'Unahitaji nini?') }} *</legend>
            @foreach (['ecards' => $t('Invitation cards, RSVP and door check-in', 'Kadi za mwaliko, RSVP na kuingia mlangoni'), 'both' => $t('All of that, plus pledges and contributions', 'Yote hayo, pamoja na ahadi na michango')] as $v => $label)
                <label class="flex items-start gap-2 mt-2 text-sm"><input type="radio" name="needs" value="{{ $v }}" class="mt-1" @checked(old('needs', 'ecards') === $v)> <span>{{ $label }}</span></label>
            @endforeach
        </fieldset>
        <div>
            <label class="text-xs font-semibold" for="message">{{ $t('Anything else we should know? (optional)', 'Kuna jingine tunalopaswa kujua? (si lazima)') }}</label>
            <textarea id="message" name="message" rows="3" maxlength="2000" class="w-full border rounded-lg px-3 py-2.5 text-base mt-1">{{ old('message') }}</textarea>
        </div>
        <div>
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="consent" value="1" class="mt-1" required @checked(old('consent'))> <span>{{ $t('I agree that Fanikisha may contact me about this request.', 'Nakubali Fanikisha iwasiliane nami kuhusu ombi hili.') }} *</span></label>
            @error('consent')<p class="text-xs text-red-600 mt-1">{{ $t('Please tick the box so we may contact you.', 'Tafadhali weka tiki ili tuweze kuwasiliana nawe.') }}</p>@enderror
        </div>
        <button class="pbtn pbtn-primary w-full">{{ $t('Send request', 'Tuma ombi') }}</button>
        <p class="text-xs text-gray-500">{{ $t('We use what you give here only to answer your request. See our', 'Tunatumia ulichotoa hapa kujibu ombi lako tu. Tazama') }} <a href="{{ route('privacy', ['lang' => $sw ? 'sw' : null]) }}" class="underline">{{ $t('Privacy Policy', 'Sera ya Faragha') }}</a>.</p>
    </form>
</div>
@endsection
