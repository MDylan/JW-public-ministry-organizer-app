<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TwoFactorSettings extends Controller
{
    public function __invoke()
    {
        return view('user.twofactorsettings');
    }

    public function confirm(Request $request)
    {
        $confirmed = $request->user()->confirmTwoFactorAuth($request->code);

        if (!$confirmed) {
            return back()->withErrors(__('user.two_factor.error'));
        }

        return back();
    }
}
