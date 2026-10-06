<?php

declare(strict_types=1);

namespace rafalmasiarek\Mailer;

/**
 * TLS behavior for SmtpClient's connection (implicit TLS and STARTTLS alike).
 *
 * @package rafalmasiarek\Mailer
 */
final class TlsOptions
{
    /**
     * @param bool        $verifyPeer          Verify the server's certificate chain and hostname against
     *                                         the system CA store. Set false only to trust any certificate
     *                                         unconditionally — prefer $caFile to pin a specific self-signed
     *                                         certificate or private CA instead.
     * @param string|null $caFile              PEM file containing the specific certificate (or CA) to trust,
     *                                         used instead of the system CA store. Verification still runs
     *                                         when $verifyPeer is true — this only changes what counts as trusted.
     * @param string|null $clientCertFile      PEM file with the client certificate, for servers requiring
     *                                         mutual TLS.
     * @param string|null $clientKeyFile       PEM file with the client private key; same file as
     *                                         $clientCertFile when both are stored together.
     * @param string|null $clientKeyPassphrase Passphrase for $clientKeyFile, when encrypted.
     */
    public function __construct(
        public readonly bool $verifyPeer = true,
        public readonly ?string $caFile = null,
        public readonly ?string $clientCertFile = null,
        public readonly ?string $clientKeyFile = null,
        public readonly ?string $clientKeyPassphrase = null,
    ) {
    }
}
