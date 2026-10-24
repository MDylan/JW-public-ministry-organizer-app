<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Roadmap TODO 67: what the CSRF middleware accepts on Laravel 13.
 *
 * The framework skips the check entirely while unit tests run, so no other
 * test in the suite sees it work. This one uses a subclass that does not
 * skip, and measures the change Laravel 13 made when it renamed the
 * middleware to PreventRequestForgery: a request the browser marks
 * `Sec-Fetch-Site: same-origin` passes without a token. A cross-site or
 * same-site request, or one without the header, still needs the token. The
 * header is set by the browser and cannot be forged by a third-party page,
 * and a client that can set it freely does not carry the victim's cookies.
 */
class RequestForgeryTest extends TestCase
{
    public static function tokenlessPosts(): array
    {
        return [
            'no header' => [null, false],
            'same-origin' => ['same-origin', true],
            'same-site' => ['same-site', false],
            'cross-site' => ['cross-site', false],
            'none (typed URL)' => ['none', false],
        ];
    }

    #[DataProvider('tokenlessPosts')]
    public function test_a_post_without_a_token(?string $secFetchSite, bool $passes): void
    {
        $request = $this->postRequest($secFetchSite);

        try {
            $this->middleware()->handle($request, fn () => response('passed'));
            $this->assertTrue($passes, 'The request passed without a token.');
        } catch (TokenMismatchException) {
            $this->assertFalse($passes, 'The request was rejected.');
        }
    }

    public function test_a_post_with_the_session_token_passes_from_anywhere(): void
    {
        $request = $this->postRequest('cross-site');
        $request->merge(['_token' => $request->session()->token()]);

        $response = $this->middleware()->handle($request, fn () => response('passed'));

        $this->assertSame('passed', $response->getContent());
    }

    private function postRequest(?string $secFetchSite): Request
    {
        $request = Request::create('/logout', 'POST');
        if ($secFetchSite !== null) {
            $request->headers->set('Sec-Fetch-Site', $secFetchSite);
        }

        $session = $this->app['session']->driver();
        $session->start();
        $request->setLaravelSession($session);

        return $request;
    }

    private function middleware(): VerifyCsrfToken
    {
        return new class($this->app, $this->app['encrypter']) extends VerifyCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        };
    }
}
