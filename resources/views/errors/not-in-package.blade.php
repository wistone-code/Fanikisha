@extends('layouts.app')
@section('title', 'Not in your package — '.config('app.name'))

@section('content')
<div class="card max-w-md mx-auto mt-10 text-center p-10">
    <i class="fa-solid fa-lock text-3xl mb-4" style="color:var(--primary);"></i>
    <h3 class="text-xl font-semibold mb-2">Not included in your package</h3>
    <p class="text-sm text-gray-500 mb-6">This feature is not part of your current package. To add it, contact {{ config('company.name', config('app.name')) }}@if (config('company.email')) at {{ config('company.email') }}@endif.</p>
    <a href="{{ route('dashboard') }}" class="btn btn-primary justify-center"><i class="fa-solid fa-house"></i> Back to home</a>
</div>
@endsection
