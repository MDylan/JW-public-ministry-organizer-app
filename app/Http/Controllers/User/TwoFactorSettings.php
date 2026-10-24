<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;

/**
 * The two-factor settings page. Enabling, confirming, disabling and the
 * recovery codes are Fortify's own routes (routes/fortify.php); the
 * application confirmed the second factor itself until roadmap TODO 69.
 */
class TwoFactorSettings extends Controller
{
    public function __invoke()
    {
        return view('user.twofactorsettings');
    }
}
