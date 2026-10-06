<?php

declare(strict_types=1);

namespace rafalmasiarek\Mailer;

/**
 * Builds a complete RFC 5322 message (headers + body) ready to send as SMTP
 * DATA content.
 *
 * Supports three body shapes:
 *   - text only
 *   - multipart/alternative (html + text), optionally wrapped in
 *     multipart/mixed when attachments are present
 *   - a verbatim RawMimeBody — attachments still wrap it in
 *     multipart/mixed if present, otherwise it is used as the top-level body
 *
 * Header values are passed through PHP's own mb_encode_mimeheader() (RFC 2047)
 * so non-ASCII subjects/display names degrade safely rather than corrupting
 * the message.
 *
 * @package rafalmasiarek\Mailer
 */
final class MimeBuilder
{
    /**
     * @param string                                          $fromEmail   Sender address.
     * @param string                                          $fromName    Sender display name (may be empty).
     * @param string                                          $toEmail     Recipient address.
     * @param string                                          $toName      Recipient display name (may be empty).
     * @param string|null                                     $replyTo     Reply-To address, or null.
     * @param string                                          $subject     Subject line.
     * @param string|null                                     $htmlBody    HTML body, or null when not used.
     * @param string|null                                     $textBody    Plain-text body, or null when not used.
     * @param list<array{path: string, name?: string}>        $attachments Files to attach.
     * @param RawMimeBody|null                                $rawBody     Verbatim body, takes
     *                                                                     precedence over html/text when set.
     * @param string                                          $messageId   Value for the Message-ID header (without angle brackets).
     *
     * @return string Complete message: headers, blank line, body.
     */
    public static function build(
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $toName,
        ?string $replyTo,
        string $subject,
        ?string $htmlBody,
        ?string $textBody,
        array $attachments,
        ?RawMimeBody $rawBody,
        string $messageId,
    ): string {
        [$bodyContentType, $bodyEncoding, $bodyContent] = self::resolveBody($htmlBody, $textBody, $rawBody);

        if ($attachments !== []) {
            $mixedBoundary = self::boundary('mixed');
            $bodyContent   = self::wrapInMixed($mixedBoundary, $bodyContentType, $bodyEncoding, $bodyContent, $attachments);
            $bodyContentType = 'multipart/mixed; boundary="' . $mixedBoundary . '"';
            $bodyEncoding     = '8bit';
        }

        $headers = [
            'Date'                     => \gmdate('D, d M Y H:i:s') . ' +0000',
            'From'                     => self::formatAddress($fromEmail, $fromName),
            'To'                       => self::formatAddress($toEmail, $toName),
            'Subject'                  => self::encodeHeader($subject),
            'Message-ID'               => '<' . $messageId . '>',
            'MIME-Version'             => '1.0',
            'Content-Type'             => $bodyContentType,
            'Content-Transfer-Encoding' => $bodyEncoding,
        ];

        if ($replyTo !== null && $replyTo !== '') {
            $headers['Reply-To'] = self::formatAddress($replyTo, '');
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = "{$name}: {$value}";
        }

        return \implode("\r\n", $headerLines) . "\r\n\r\n" . $bodyContent;
    }

    /**
     * Resolves the top-level content-type/encoding/body before any attachment wrapping.
     *
     * @param string|null $htmlBody
     * @param string|null $textBody
     * @param RawMimeBody|null $rawBody
     *
     * @return array{0: string, 1: string, 2: string} [contentType, encoding, body]
     */
    private static function resolveBody(?string $htmlBody, ?string $textBody, ?RawMimeBody $rawBody): array
    {
        if ($rawBody !== null) {
            return [$rawBody->contentType, $rawBody->encoding, $rawBody->body];
        }

        if ($htmlBody !== null && $textBody !== null) {
            $boundary = self::boundary('alt');
            $body =
                "--{$boundary}\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: base64\r\n\r\n"
                . self::chunkBase64($textBody)
                . "--{$boundary}\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: base64\r\n\r\n"
                . self::chunkBase64($htmlBody)
                . "--{$boundary}--\r\n";

            return ['multipart/alternative; boundary="' . $boundary . '"', '8bit', $body];
        }

        if ($htmlBody !== null) {
            return ['text/html; charset=UTF-8', 'base64', self::chunkBase64($htmlBody)];
        }

        return ['text/plain; charset=UTF-8', 'base64', self::chunkBase64($textBody ?? '')];
    }

    /**
     * Wraps the already-resolved body plus attachments in multipart/mixed.
     *
     * @param string                                   $boundary
     * @param string                                   $innerContentType
     * @param string                                   $innerEncoding
     * @param string                                   $innerBody
     * @param list<array{path: string, name?: string}> $attachments
     *
     * @return string
     */
    private static function wrapInMixed(
        string $boundary,
        string $innerContentType,
        string $innerEncoding,
        string $innerBody,
        array $attachments
    ): string {
        $parts = [];
        $parts[] =
            "--{$boundary}\r\n"
            . "Content-Type: {$innerContentType}\r\n"
            . "Content-Transfer-Encoding: {$innerEncoding}\r\n\r\n"
            . $innerBody;

        foreach ($attachments as $attachment) {
            $path = $attachment['path'];
            $name = $attachment['name'] ?? \basename($path);
            $data = @\file_get_contents($path);
            if ($data === false) {
                throw new SmtpException("Attachment not readable: \"{$path}\".");
            }

            $mimeType = self::guessMimeType($path);
            $encodedName = self::encodeHeader($name);

            $parts[] =
                "--{$boundary}\r\n"
                . "Content-Type: {$mimeType}; name=\"{$encodedName}\"\r\n"
                . "Content-Transfer-Encoding: base64\r\n"
                . "Content-Disposition: attachment; filename=\"{$encodedName}\"\r\n\r\n"
                . self::chunkBase64($data);
        }

        return \implode('', $parts) . "--{$boundary}--\r\n";
    }

    /**
     * Base64-encodes content and splits it into RFC-compliant 76-char lines.
     *
     * @param string $data
     *
     * @return string Base64 content, chunked, CRLF-terminated.
     */
    private static function chunkBase64(string $data): string
    {
        return \chunk_split(\base64_encode($data), 76, "\r\n");
    }

    /**
     * Formats a "Name <email>" address header value, encoding the name if needed.
     *
     * @param string $email
     * @param string $name
     *
     * @return string
     */
    private static function formatAddress(string $email, string $name): string
    {
        if ($name === '') {
            return "<{$email}>";
        }

        return self::encodeHeader($name) . " <{$email}>";
    }

    /**
     * Encodes a header value per RFC 2047 when it contains non-ASCII bytes.
     *
     * @param string $value
     *
     * @return string
     */
    private static function encodeHeader(string $value): string
    {
        if ($value === '' || \preg_match('/[^\x20-\x7E]/', $value) !== 1) {
            return $value;
        }

        return \mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
    }

    /**
     * Generates a unique MIME boundary string.
     *
     * @param string $label
     *
     * @return string
     */
    private static function boundary(string $label): string
    {
        return '=_' . $label . '_' . \bin2hex(\random_bytes(12));
    }

    /**
     * Best-effort MIME type guess from a file extension; falls back to a
     * generic binary type when unknown.
     *
     * @param string $path
     *
     * @return string
     */
    private static function guessMimeType(string $path): string
    {
        $finfo = \finfo_open(\FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return 'application/octet-stream';
        }

        $type = \finfo_file($finfo, $path);
        \finfo_close($finfo);

        return $type !== false ? $type : 'application/octet-stream';
    }
}
