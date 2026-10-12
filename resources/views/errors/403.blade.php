@php
    $text = $exception->getMessage();
    // Messages written for people are shown as they are; the generic one gets a clearer explanation.
    if ($text === '' || $text === "You don't have permission to do that." || $text === 'This action is unauthorized.') {
        $text = 'Your account is not allowed to do this on this event. Only the event Admin can send invitations, edit guests or change settings — ask them, or sign in with the Admin account.';
    }
@endphp
@include('errors._page', ['code' => 403, 'icon' => '&#128274;', 'title' => 'Not allowed', 'message' => $text])
