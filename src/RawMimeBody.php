<?php

declare(strict_types=1);

namespace rafalmasiarek\Mailer;

/**
 * A fully-formed MIME body to send verbatim, bypassing normal
 * alternative/mixed construction.
 *
 * @package rafalmasiarek\Mailer
 */
final readonly class RawMimeBody
{
    /**
     * @param string $body        Raw MIME body content.
     * @param string $contentType Content-Type header value for this body (may include boundary=…).
     * @param string $encoding    Content-Transfer-Encoding value.
     */
    public function __construct(
        public string $body,
        public string $contentType,
        public string $encoding,
    ) {
    }
}
