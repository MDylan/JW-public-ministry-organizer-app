<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * The two-factor recovery codes as a plain-text file, so the user can save
 * them in one step instead of copying them off the settings page.
 *
 * Gated like the settings page that already shows the same codes (verified,
 * profileFull, password.confirm). A user without a confirmed second factor
 * gets a 404: an unconfirmed secret's codes are not yet in force.
 */
class DownloadRecoveryCodes extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        abort_unless($user->hasConfirmedTwoFactorAuth(), 404);

        $lines = [
            __('user.two_factor.codes_file_title', ['app' => config('app.name')]),
            __('user.two_factor.codes_file_account', ['email' => $user->email]),
            __('user.two_factor.codes_file_created', ['date' => now()->format('Y-m-d H:i')]),
            '',
            // One sentence a line, so the note reads without scrolling.
            ...preg_split('/(?<=[.!?])\s+/u', __('user.two_factor.codes_file_usage')),
            '',
            ...$user->recoveryCodes(),
        ];

        $filename = (Str::slug((string) config('app.name')) ?: 'account').'-recovery-codes.txt';

        return response(implode("\r\n", $lines)."\r\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename),
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
