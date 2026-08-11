<?php

namespace Tests\Feature\Mail;

use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Component\Mailer\Transport\NullTransport;
use Tests\TestCase;

/**
 * TODO 36: what config/mail.php's smtp mailer actually reaches the transport
 * with, which nothing measured before - and that is precisely how TODO 34
 * managed to drop a working setting without anyone noticing.
 *
 * Laravel 8 read two keys that Laravel 9 does not:
 *
 *     MailManager:222-223   if (isset($config['stream']))    $transport->setStreamOptions(...)
 *     MailManager:238-239   if (isset($config['auth_mode'])) $transport->setAuthMode(...)
 *
 * Laravel 9 hands the WHOLE mailer config array to Symfony as the Dsn's option
 * list and lets EsmtpTransportFactory take what it recognises;
 * configureSmtpTransport() then reads only source_ip and timeout. So 'stream'
 * is inert - and so is 'auth_mode', although that one was already inert on
 * Laravel 8, because its value is null and isset(null) is false. Two keys, two
 * different stories, which is why they are measured separately below.
 *
 * The transport can be built without connecting: SocketStream::initialize() runs
 * on send, not on construction. That is the only reason any of this is testable,
 * and no test in this file may ever send.
 */
class MailTransportConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        // MailManager caches resolved mailers (:45). A test that sets config
        // and does not purge would hand the next one a transport built from its
        // own configuration, and the failure would surface somewhere else.
        Mail::forgetMailers();

        parent::tearDown();
    }

    /**
     * The stream options of the smtp mailer's transport, built from the current
     * configuration plus the given overrides. Nothing here opens a socket.
     */
    private function smtpStreamOptions(array $overrides = []): array
    {
        foreach ($overrides as $key => $value) {
            config()->set('mail.mailers.smtp.'.$key, $value);
        }

        Mail::purge('smtp');

        $transport = Mail::mailer('smtp')->getSymfonyTransport();

        $this->assertInstanceOf(
            EsmtpTransport::class,
            $transport,
            'Az smtp mailer nem EsmtpTransportot épített, így a stream-opciók mérése értelmetlen.'
        );

        $stream = $transport->getStream();

        $this->assertInstanceOf(SocketStream::class, $stream);

        return $stream->getStreamOptions();
    }

    // =========================================================================
    // 1. What Laravel 9 stopped reading
    // =========================================================================

    public function test_the_stream_key_no_longer_reaches_the_transport(): void
    {
        // Deliberately a NON-empty value, so this measures the key being
        // ignored rather than the key happening to be empty under APP_ENV
        // =testing. On Laravel 8 this exact array became the stream options.
        $options = $this->smtpStreamOptions([
            'stream' => [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ],
        ]);

        $this->assertSame(
            [],
            $options,
            'A stream kulcs eljutott a transportig - ha ez elbukik, a Laravel újra olvassa, és a config/mail.php-t felül kell vizsgálni.'
        );
    }

    public function test_auth_mode_has_nothing_left_to_reach(): void
    {
        // setAuthMode() was a SwiftMailer API. Symfony negotiates the
        // authenticators itself and exposes no equivalent, so the key cannot be
        // ported - there is nothing to port it to. Laravel 9 does not read it
        // either, and Laravel 8 never actually called the setter here, because
        // the configured value is null.
        $this->assertFalse(
            method_exists(EsmtpTransport::class, 'setAuthMode'),
            'A Symfony EsmtpTransport kapott setAuthMode()-ot - az auth_mode kulcs sorsát újra kell gondolni.'
        );
    }

    // =========================================================================
    // 2. The Symfony equivalent, and what it does
    // =========================================================================

    public function test_a_mailer_level_verify_peer_reaches_the_stream_options(): void
    {
        // EsmtpTransportFactory:36. This is the whole basis of the TODO 36
        // decision: it is asserted BEFORE config/mail.php is changed, so the
        // port is a measured move rather than a hopeful one.
        $options = $this->smtpStreamOptions(['verify_peer' => false]);

        $this->assertFalse($options['ssl']['verify_peer']);
        $this->assertFalse($options['ssl']['verify_peer_name']);

        // The two the old stream block set are exactly the two Symfony sets.
        // allow_self_signed has no equivalent and needs none: it only matters
        // while verify_peer is true.
        $this->assertArrayNotHasKey('allow_self_signed', $options['ssl']);
    }

    public function test_a_null_verify_peer_is_the_same_as_no_key_at_all(): void
    {
        // Dsn::getOption() uses ??, so a literal null falls through to the
        // default - which is true. This is what makes
        // "APP_ENV === 'local' ? false : null" exactly as narrow as the block
        // it replaces: outside local, verification stays on.
        $this->assertSame([], $this->smtpStreamOptions(['verify_peer' => null]));
    }

    public function test_a_true_verify_peer_leaves_verification_on(): void
    {
        $this->assertSame([], $this->smtpStreamOptions(['verify_peer' => true]));
    }

    // =========================================================================
    // 3. Why nothing else in the suite measures any of this
    // =========================================================================

    public function test_the_suite_runs_on_the_array_transport(): void
    {
        // phpunit.xml sets MAIL_MAILER=array. Every other mail test therefore
        // reads messages out of memory and never builds an SMTP transport,
        // which is why the file you are reading has to exist.
        $this->assertSame('array', config('mail.default'));

        $transport = Mail::mailer()->getSymfonyTransport();

        $this->assertNotInstanceOf(EsmtpTransport::class, $transport);
        $this->assertNotInstanceOf(NullTransport::class, $transport);
    }
}
