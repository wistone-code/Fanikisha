@php($wait = (int) ($exception->getHeaders()['Retry-After'] ?? 60))
@include('errors._page', ['code' => 429, 'icon' => '&#9203;', 'title' => 'Please slow down', 'message' => "That was tried too many times in a short time. Please wait about {$wait} seconds, then try again."])
