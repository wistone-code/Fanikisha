@extends('layouts.public')
@php
    $c = config('company'); $sw = $lang === 'sw';
    $t = fn ($en, $swh) => $sw ? $swh : $en;
@endphp
@section('title', $t('Send us feedback', 'Tupe maoni').' — '.$c['brand'])
@section('description', 'Tell us what you think about Fanikisha.')

@section('content')
<div class="max-w-xl mx-auto px-5 pt-10">
    <h1 class="text-3xl font-bold mb-2">{{ $t('Send us feedback', 'Tupe maoni yako') }}</h1>
    <p class="text-gray-600 text-sm mb-6">{{ $t('Found a problem, have an idea or a question? Tell us. Leave a phone or email only if you want an answer.', 'Umeona tatizo, una wazo au swali? Tuambie. Acha simu au barua pepe ikiwa unataka jibu.') }}</p>
    @if (session('sent'))<div class="rounded-xl bg-green-50 text-green-800 p-3 text-sm mb-4"><strong>{{ $t('Thank you.', 'Asante.') }}</strong> {{ $t('Your feedback reached us.', 'Maoni yako yamefika.') }}</div>@endif
    <form method="POST" action="{{ route('site-feedback.store', ['lang' => $sw ? 'sw' : null]) }}" class="space-y-4">
        @csrf
        <div style="position:absolute;left:-9999px;" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
        @include('feedback._fields', ['sw' => $sw])
        <div>
            <label class="text-xs font-semibold" for="name">{{ $t('Your name (optional)', 'Jina lako (si lazima)') }}</label>
            <input id="name" name="name" value="{{ old('name') }}" maxlength="120" class="w-full border rounded-lg px-3 py-2.5 text-base mt-1">
        </div>
        <div>
            <label class="text-xs font-semibold" for="contact">{{ $t('Phone or email (optional, if you want an answer)', 'Simu au barua pepe (si lazima, ukitaka jibu)') }}</label>
            <input id="contact" name="contact" value="{{ old('contact') }}" maxlength="150" class="w-full border rounded-lg px-3 py-2.5 text-base mt-1">
        </div>
        <button class="pbtn pbtn-primary w-full">{{ $t('Send feedback', 'Tuma maoni') }}</button>
    </form>
</div>
@endsection
