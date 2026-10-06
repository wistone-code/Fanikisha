@extends('layouts.public')
@php
    $c = config('company'); $sw = $lang === 'sw';
    $t = fn ($en, $swh) => $sw ? $swh : $en;
    $sent = session('sent');
@endphp
@section('title', $t('Data request', 'Maombi ya taarifa').' — '.$c['brand'])
@section('description', 'Ask Fanikisha to show, correct or delete your personal data, or to stop sending you messages.')

@section('content')
<div class="max-w-xl mx-auto px-5 pt-10">
    <h1 class="text-3xl font-bold mb-2">{{ $t('Your data and messages', 'Taarifa zako na ujumbe') }}</h1>
    <p class="text-gray-600 text-sm mb-6">{{ $t('Use this form to see, correct or delete the personal data we hold about you, or to stop receiving messages sent through Fanikisha. We reply within 30 days. A request to stop messages is checked and applied by our team, usually within 2 working days. To stop messages at once, use the Stop messages button on your invitation card.', 'Tumia fomu hii kuona, kusahihisha au kufuta taarifa zako, au kuacha kupokea ujumbe unaotumwa kupitia Fanikisha. Tunajibu ndani ya siku 30. Ombi la kuacha ujumbe huhakikiwa na timu yetu, kwa kawaida ndani ya siku 2 za kazi. Kuacha ujumbe mara moja, tumia kitufe cha Acha ujumbe kwenye kadi yako ya mwaliko.') }}</p>

    @if ($sent)
        <div class="rounded-xl bg-green-50 text-green-800 p-4 text-sm mb-6">
            <strong>{{ $t('Thank you.', 'Asante.') }}</strong> {{ $t('We received your request and will reply within 30 days.', 'Tumepokea ombi lako na tutajibu ndani ya siku 30.') }}
        </div>
    @endif

    <form method="POST" action="{{ route('data-request.store', ['lang' => $sw ? 'sw' : null]) }}" class="space-y-4">
        @csrf
        <div style="position:absolute;left:-9999px;" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
        <div>
            <label class="text-xs font-semibold" for="type">{{ $t('What would you like?', 'Unataka nini?') }}</label>
            <select id="type" name="type" class="w-full border rounded-lg px-3 py-2.5 text-base mt-1" required>
                @foreach (['stop' => $t('Stop messages to my number', 'Acha kutuma ujumbe kwa namba yangu'), 'access' => $t('See the data you hold about me', 'Niona taarifa mlizonazo kunihusu'), 'correct' => $t('Correct my data', 'Sahihisheni taarifa zangu'), 'delete' => $t('Delete my data', 'Futeni taarifa zangu'), 'other' => $t('Something else', 'Jambo lingine')] as $v => $label)
                    <option value="{{ $v }}" @selected(old('type', request('type')) === $v)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-xs font-semibold" for="name">{{ $t('Your name (optional)', 'Jina lako (si lazima)') }}</label>
            <input id="name" name="name" value="{{ old('name') }}" maxlength="120" class="w-full border rounded-lg px-3 py-2.5 text-base mt-1">
        </div>
        <div>
            <label class="text-xs font-semibold" for="phone">{{ $t('Phone number the messages came to', 'Namba ya simu iliyopokea ujumbe') }}</label>
            <input id="phone" name="phone" inputmode="tel" value="{{ old('phone') }}" maxlength="30" placeholder="07XX XXX XXX" class="w-full border rounded-lg px-3 py-2.5 text-base mt-1">
            @error('phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="text-xs font-semibold" for="email">{{ $t('Email (optional)', 'Barua pepe (si lazima)') }}</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" maxlength="150" class="w-full border rounded-lg px-3 py-2.5 text-base mt-1">
            @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="text-xs font-semibold" for="details">{{ $t('Details (event name, what you want)', 'Maelezo (jina la tukio, unachotaka)') }}</label>
            <textarea id="details" name="details" rows="3" maxlength="2000" class="w-full border rounded-lg px-3 py-2.5 text-base mt-1">{{ old('details') }}</textarea>
        </div>
        <button class="pbtn pbtn-primary w-full">{{ $t('Send request', 'Tuma ombi') }}</button>
        <p class="text-xs text-gray-500">{{ $t('We use what you give here only to find and answer your request. See our', 'Tunatumia ulichotoa hapa kutafuta na kujibu ombi lako tu. Tazama') }} <a href="{{ route('privacy', ['lang' => $sw ? 'sw' : null]) }}" class="underline">{{ $t('Privacy Policy', 'Sera ya Faragha') }}</a>.</p>
    </form>
</div>
@endsection
