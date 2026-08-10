<?php

namespace App\Http\Controllers;

use App\Http\Requests\GdprDownload;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * GDPR article 20 (data portability). One action, one route:
 * POST gdpr/download, registered in routes/web.php from config/gdpr.php.
 *
 * TODO 33.2 removed the consent half this controller used to carry
 * (showTerms / termsAccepted / termsDenied). It was never finished: the page
 * returned a 500 because the published view extended a layout this project
 * does not have, its body was still the package's Lorem ipsum, nothing linked
 * to it, and the middleware that would have driven users to it was never
 * registered. users.accepted_gdpr stays as a column - see TODO 16 for the
 * decision. An anonymize($id) action was removed with it: no route ever
 * pointed at it, and it checked no authorization at all.
 */
class GdprController extends Controller
{
    /**
     * Download the GDPR compliant data portability JSON file.
     *
     * @param  \App\Http\Requests\GdprDownload  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function download(GdprDownload $request)
    {
        $credentials = [
            $request->user()->getAuthIdentifierName() => $request->user()->getAuthIdentifier(),
            'password'                                => $request->input('password'),
        ];

        abort_unless(Auth::attempt($credentials), 403);

        return response()->json(
            $request->user()->portable(),
            200,
            [
                'Content-Disposition' => 'attachment; filename="user.json"',
            ]
        );
    }
}
