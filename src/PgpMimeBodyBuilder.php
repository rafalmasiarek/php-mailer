<?php

declare(strict_types=1);

namespace rafalmasiarek\Mailer;

/**
 * Builds a `multipart/encrypted` (RFC 3156, PGP/MIME) body from an already
 * PGP-armored ciphertext block.
 *
 * Standalone — has no dependency on MailMessage/Mailer/anything else in this
 * library. Feed the returned RawMimeBody into whatever "send this exact body
 * verbatim" mechanism the caller's higher-level mail API provides.
 *
 * @package rafalmasiarek\Mailer
 */
final class PgpMimeBodyBuilder
{
    /**
     * Builds the raw multipart/encrypted body for an armored PGP MESSAGE block.
     *
     * @param string $armoredCiphertext Armored "-----BEGIN PGP MESSAGE-----" block.
     *
     * @return RawMimeBody Body/content-type/encoding to send verbatim.
     */
    public static function build(string $armoredCiphertext): RawMimeBody
    {
        $boundary = '=_pgp_boundary_' . \bin2hex(\random_bytes(12));
        $cipher   = self::normalizeCrlf(\trim($armoredCiphertext)) . "\r\n";

        $body =
            "--{$boundary}\r\n"
            . "Content-Type: application/pgp-encrypted\r\n"
            . "Content-Transfer-Encoding: 7bit\r\n"
            . "\r\n"
            . "Version: 1\r\n"
            . "\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: application/octet-stream; name=\"encrypted.asc\"\r\n"
            . "Content-Disposition: inline; filename=\"encrypted.asc\"\r\n"
            . "Content-Transfer-Encoding: 7bit\r\n"
            . "\r\n"
            . $cipher
            . "--{$boundary}--\r\n";

        return new RawMimeBody(
            $body,
            'multipart/encrypted; protocol="application/pgp-encrypted"; boundary="' . $boundary . '"',
            '7bit',
        );
    }

    /**
     * Normalises line endings to CRLF, as required by MIME specs.
     *
     * @param string $s Input string with any line endings.
     *
     * @return string String with all line endings replaced by CRLF.
     */
    private static function normalizeCrlf(string $s): string
    {
        $s = \str_replace("\r\n", "\n", $s);
        return \str_replace("\n", "\r\n", $s);
    }
}
