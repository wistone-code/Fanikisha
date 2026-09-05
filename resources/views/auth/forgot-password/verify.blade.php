@extends('layouts.guest')
@section('title', 'Verify your identity — '.config('app.name'))

@section('content')
@include('auth.forgot-password._steps', ['step' => 2])

<p class="text-sm text-gray-500 mb-3">We've sent a 6-digit verification code by SMS to the phone number on file, ending in <strong>{{ $maskedPhone }}</strong>.</p>

@if ($expiresAt)
<div class="bg-gray-50 rounded-lg p-3 mb-4 text-xs text-gray-500" id="codeExpiry" data-expires="{{ $expiresAt->clone()->timezone('UTC')->toIso8601String() }}">
    This code expires at {{ $expiresAt->timezone('Africa/Dar_es_Salaam')->format('g:i A') }}.
</div>
@endif

@error('code')
<div class="bg-red-50 text-red-700 text-sm rounded-lg px-3 py-2 mb-4">{{ $message }}</div>
@enderror

<form method="POST" action="{{ route('password.forgot.verify.submit') }}" class="space-y-4">
    @csrf
    <div>
        <label class="text-xs font-semibold">Verification code</label>
        <input type="text" name="code" maxlength="6" inputmode="numeric" placeholder="000000" required
               class="w-full border rounded-lg px-3 py-2.5 text-sm mt-1 text-center tracking-widest font-mono">
    </div>
    <div class="flex gap-2">
        <a href="{{ route('password.forgot.identify') }}" class="btn btn-ghost">Back</a>
        <button class="btn btn-primary">Verify code</button>
    </div>
</form>

@if ($expiresAt)
<script>
(function () {
    var el = document.getElementById('codeExpiry');
    if (!el) return;
    var expires = new Date(el.dataset.expires);

    function tick() {
        var secondsLeft = Math.round((expires - new Date()) / 1000);
        if (secondsLeft <= 0) {
            el.textContent = 'This code has expired — go back and request a new one.';
            el.classList.add('text-red-600');
            return;
        }
        var mins = Math.floor(secondsLeft / 60);
        var secs = secondsLeft % 60;
        el.textContent = 'This code expires in ' + mins + ':' + (secs < 10 ? '0' : '') + secs + '.';
        setTimeout(tick, 1000);
    }
    tick();
})();
</script>
@endif
@endsection
