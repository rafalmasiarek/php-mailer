<?php

declare(strict_types=1);

namespace rafalmasiarek\Mailer;

/**
 * Delivery Status Notification request (RFC 3461). Applied to the MAIL
 * FROM/RCPT TO commands only when the server's EHLO response advertises the
 * DSN extension — silently omitted otherwise.
 *
 * @package rafalmasiarek\Mailer
 */
final class DsnOptions
{
    /**
     * @param 'HDRS'|'FULL'                            $ret    How much of the original message a DSN report
     *                                                         should include.
     * @param list<'SUCCESS'|'FAILURE'|'DELAY'|'NEVER'> $notify Events to request a DSN report for.
     * @param string|null                               $envId  Opaque id echoed back as Original-Envelope-Id in
     *                                                          any DSN report; must be xtext-safe (printable
     *                                                          ASCII, no "+" or "=").
     */
    public function __construct(
        public readonly string $ret = 'HDRS',
        public readonly array $notify = ['FAILURE', 'DELAY'],
        public readonly ?string $envId = null,
    ) {
    }
}
