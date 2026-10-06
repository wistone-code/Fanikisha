@extends('layouts.public')
@php $c = config('company'); $sw = $lang === 'sw'; @endphp
@section('title', $title.' — '.$c['brand'])
@section('description', $description)

@section('content')
<div class="max-w-3xl mx-auto px-5 pt-10 prose-doc">
    <h1 class="text-3xl font-bold mb-1">{{ $title }}</h1>
    <p class="text-sm text-gray-500 mb-6">{{ $sw ? 'Imesasishwa' : 'Last updated' }}: {{ $c['updated'] }}</p>
    {!! $html !!}
</div>
@endsection
