<?php

declare(strict_types=1);

namespace rafalmasiarek\Mailer;

/**
 * Signs a complete RFC 5322 message with DKIM (RFC 6376), relaxed/relaxed
 * canonicalization, rsa-sha256. Operates on the raw message string produced
 * by MimeBuilder::build() — no knowledge of SMTP or transport; the returned
 * string is prepended with a DKIM-Signature header and otherwise identical,
 * ready to pass to SmtpClient as-is.
 *
 * @package rafalmasiarek\Mailer
 */
final class DkimSigner
{
    /**
     * @param string       $rawMessage    Complete message from MimeBuilder::build().
     * @param string       $privateKeyPem PEM-encoded RSA private key.
     * @param string       $domain        Signing domain (d= tag).
     * @param string       $selector      DKIM selector (s= tag).
     * @param list<string> $headersToSign Header field names to sign, in signing order.
     *
     * @throws SmtpException When the private key is invalid or signing fails.
     *
     * @return string The message with a DKIM-Signature header prepended.
     */
    public static function sign(
        string $rawMessage,
        string $privateKeyPem,
        string $domain,
        string $selector,
        array $headersToSign = ['From', 'To', 'Subject', 'Date', 'Message-ID'],
    ): string {
        [$headerBlock, $body] = self::splitMessage($rawMessage);
        $headers = self::parseHeaders($headerBlock);

        $bodyHash = \base64_encode(\hash('sha256', self::canonicalizeBodyRelaxed($body), true));

        $signedNames      = [];
        $canonHeaderLines = [];
        foreach ($headersToSign as $name) {
            $found = self::findHeader($headers, $name);
            if ($found === null) {
                continue;
            }
            $canonHeaderLines[] = self::canonicalizeHeaderRelaxed($found[0], $found[1]);
            $signedNames[]      = \strtolower($name);
        }

        $dkimHeaderNoSignature = \sprintf(
            'v=1; a=rsa-sha256; c=relaxed/relaxed; d=%s; s=%s; h=%s; bh=%s; b=',
            $domain,
            $selector,
            \implode(':', $signedNames),
            $bodyHash
        );

        $canonHeaderLines[] = self::canonicalizeHeaderRelaxed('DKIM-Signature', $dkimHeaderNoSignature);
        $signingInput       = \implode("\r\n", $canonHeaderLines);

        $privateKey = \openssl_pkey_get_private($privateKeyPem);
        if ($privateKey === false) {
            throw new SmtpException('Invalid DKIM private key.');
        }

        $signature = '';
        $ok = \openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if (!$ok) {
            throw new SmtpException('DKIM signing failed.');
        }

        $dkimHeaderValue = $dkimHeaderNoSignature . \base64_encode($signature);

        return "DKIM-Signature: {$dkimHeaderValue}\r\n" . $rawMessage;
    }

    /**
     * @param string $rawMessage
     *
     * @return array{0: string, 1: string} [headerBlock, body]
     */
    private static function splitMessage(string $rawMessage): array
    {
        $pos = \strpos($rawMessage, "\r\n\r\n");
        if ($pos === false) {
            return [$rawMessage, ''];
        }

        return [\substr($rawMessage, 0, $pos), \substr($rawMessage, $pos + 4)];
    }

    /**
     * Parses an unfolded header block into an ordered list of [name, value] pairs,
     * reassembling folded continuation lines into the owning header's value.
     *
     * @param string $headerBlock
     *
     * @return list<array{0: string, 1: string}>
     */
    private static function parseHeaders(string $headerBlock): array
    {
        $lines   = \explode("\r\n", $headerBlock);
        $headers = [];

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            if (($line[0] === ' ' || $line[0] === "\t") && $headers !== []) {
                $lastIndex = \array_key_last($headers);
                $headers[$lastIndex][1] .= "\r\n" . $line;
                continue;
            }

            $pos = \strpos($line, ':');
            if ($pos === false) {
                continue;
            }

            $headers[] = [\substr($line, 0, $pos), \substr($line, $pos + 1)];
        }

        return $headers;
    }

    /**
     * @param list<array{0: string, 1: string}> $headers
     * @param string                             $name
     *
     * @return array{0: string, 1: string}|null
     */
    private static function findHeader(array $headers, string $name): ?array
    {
        foreach ($headers as $header) {
            if (\strcasecmp($header[0], $name) === 0) {
                return $header;
            }
        }

        return null;
    }

    /**
     * RFC 6376 §3.4.2 relaxed header canonicalization: lowercase the name,
     * unfold continuation lines, collapse whitespace runs to a single space,
     * trim the value.
     *
     * @param string $name
     * @param string $value
     *
     * @return string
     */
    private static function canonicalizeHeaderRelaxed(string $name, string $value): string
    {
        $name  = \strtolower(\trim($name));
        $value = \preg_replace('/\r\n[ \t]+/', ' ', $value) ?? $value;
        $value = \preg_replace('/[ \t]+/', ' ', $value) ?? $value;
        $value = \trim($value);

        return $name . ':' . $value;
    }

    /**
     * RFC 6376 §3.4.4 relaxed body canonicalization: collapse whitespace runs
     * within each line, strip trailing whitespace per line, remove trailing
     * blank lines, end with exactly one CRLF unless the body is empty.
     *
     * @param string $body
     *
     * @return string
     */
    private static function canonicalizeBodyRelaxed(string $body): string
    {
        $lines = \explode("\r\n", $body);
        foreach ($lines as &$line) {
            $line = \rtrim($line, " \t");
            $line = \preg_replace('/[ \t]+/', ' ', $line) ?? $line;
        }
        unset($line);

        $canon = \rtrim(\implode("\r\n", $lines), "\r\n");

        return $canon === '' ? '' : $canon . "\r\n";
    }
}
