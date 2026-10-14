<?php

namespace App\Http\Controllers;

use App\Services\EventBanner;
use Illuminate\Http\RedirectResponse;

class BannerController extends Controller
{
    /** "Got it" on the top banner: hides the admin message, or today's best wishes. */
    public function dismiss(EventBanner $banner): RedirectResponse
    {
        if ($event = app('currentEvent')) {
            $banner->dismiss($event);
        }

        return back();
    }
}
