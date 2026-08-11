<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * Null, and deliberately so: no proxy is trusted, so the X-Forwarded-*
     * headers are treated as the attacker-controlled input they are on a host
     * that is reached directly. The application ships no config/trustedproxy.php
     * either; the parent still reads that key, so an operator who genuinely
     * runs behind a reverse proxy can publish one without touching this class.
     *
     * @var array|string|null
     */
    protected $proxies;

    /*
     * The $headers override that used to sit here is GONE, and it is worth
     * recording why, because the value looked meaningful:
     *
     *     HEADER_X_FORWARDED_FOR | HOST | PORT | PROTO | AWS_ELB
     *
     * Both the old parent (Fideloper\Proxy\TrustProxies, a switch) and the new
     * one (a match) resolve $headers against SINGLE constants and fall through
     * to their default arm for anything else. A combined bitmask equals no
     * single constant, so the override never selected anything under either
     * parent - it only ever reached the same default it was trying to restate.
     *
     * The two defaults differ by one flag: the framework's also trusts
     * X-Forwarded-Prefix. That cannot surface here either, because $proxies is
     * null - isFromTrustedProxy() is always false, so the trusted-header set is
     * never consulted at all.
     *
     * Measured in TODO 35 and pinned by TrustProxiesTest.
     */
}
