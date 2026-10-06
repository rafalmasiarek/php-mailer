<?php

declare(strict_types=1);

namespace rafalmasiarek\Mailer;

/**
 * Canonical SMTP error codes and classifier.
 *
 * All needle-matching logic lives here so every caller (logging, API error
 * responses, …) derives the same structured reason code from a raw SMTP
 * error string.
 *
 * @package rafalmasiarek\Mailer
 */
final class SmtpErrorCode
{
    /** @var string SMTP server refused the connection. */
    public const CONNECT = 'ERR_SMTP_CONNECT';

    /** @var string SMTP authentication failed. */
    public const AUTH = 'ERR_SMTP_AUTH';

    /** @var string Sender address was rejected. */
    public const FROM = 'ERR_SMTP_FROM';

    /** @var string Recipient address was rejected. */
    public const RCPT = 'ERR_SMTP_RCPT';

    /** @var string SMTP server rejected the message data. */
    public const DATA = 'ERR_SMTP_DATA';

    /** @var string TLS negotiation failed. */
    public const TLS = 'ERR_SMTP_TLS';

    /** @var string Recipient mailbox is full or disabled. */
    public const QUOTA = 'ERR_SMTP_QUOTA';

    /** @var string Message exceeds a size limit imposed by the server. */
    public const SIZE = 'ERR_SMTP_SIZE';

    /** @var string Server refused to relay the message for this sender/recipient pair. */
    public const RELAY = 'ERR_SMTP_RELAY';

    /** @var string Server rejected the message on content/policy grounds (spam, blocklist, etc.). */
    public const POLICY = 'ERR_SMTP_POLICY';

    /** @var string Server is rate-limiting or temporarily refusing mail (greylisting, throttling). */
    public const RATE_LIMIT = 'ERR_SMTP_RATE_LIMIT';

    /** @var string Unrecognised SMTP failure. */
    public const UNKNOWN = 'ERR_SMTP_UNKNOWN';

    /**
     * Human-readable description for each error code.
     *
     * @var array<string, string>
     */
    private const MESSAGES = [
        self::CONNECT    => 'Could not connect to the SMTP server.',
        self::AUTH       => 'SMTP authentication failed.',
        self::FROM       => 'Invalid sender address.',
        self::RCPT       => 'Recipient address was rejected.',
        self::DATA       => 'SMTP server rejected the message data.',
        self::TLS        => 'Could not start TLS connection.',
        self::QUOTA      => 'Recipient mailbox is full or disabled.',
        self::SIZE       => 'Message exceeds the server size limit.',
        self::RELAY      => 'Relay access denied by the SMTP server.',
        self::POLICY     => 'Message rejected by server policy.',
        self::RATE_LIMIT => 'SMTP server is rate-limiting or temporarily unavailable.',
        self::UNKNOWN    => 'Unknown SMTP error.',
    ];

    /**
     * Returns the human-readable message for an ERR_SMTP_* code.
     *
     * @param string $code One of the ERR_SMTP_* constant values.
     *
     * @return string Human-readable error description.
     */
    public static function message(string $code): string
    {
        return self::MESSAGES[$code] ?? self::MESSAGES[self::UNKNOWN];
    }

    /**
     * Classifies an SMTP error string into one of the ERR_SMTP_* constants.
     *
     * Checks for an RFC 3463 enhanced status code
     * first — Postfix and most modern MTAs include one on nearly every rejection,
     * and it is far more reliable than matching human-readable text that varies
     * between server software. Falls back to free-text needles for
     * connection-level failures that never reach the SMTP protocol layer, where
     * no such code exists.
     *
     * @param string $errorInfo Raw SMTP server response / error text.
     * @param string $exceptionMessage Exception::getMessage() for the same failure.
     *
     * @return string One of the ERR_SMTP_* constant values.
     */
    public static function classify(string $errorInfo, string $exceptionMessage = ''): string
    {
        $haystack = strtolower($errorInfo . ' ' . $exceptionMessage);

        if (preg_match('/\b[245]\.(\d{1,3})\.(\d{1,3})\b/', $haystack, $m)) {
            $subject = (int) $m[1];
            $detail  = (int) $m[2];

            // Greylisting/throttling is inconsistently coded across MTAs (often
            // 4.7.1, sometimes 4.3.x) — text signals win over the generic
            // per-subject mapping below when present.
            $isRateLimited = str_contains($haystack, 'greylist')
                || str_contains($haystack, 'try again later')
                || str_contains($haystack, 'too many');

            $code = match (true) {
                $isRateLimited                                      => self::RATE_LIMIT,
                $subject === 1 && ($detail === 7 || $detail === 8) => self::FROM,
                $subject === 1                                     => self::RCPT,
                $subject === 2                                     => self::QUOTA,
                $subject === 3 && $detail === 4                    => self::SIZE,
                $subject === 4                                     => self::CONNECT,
                $subject === 7 && str_contains($haystack, 'sender') => self::FROM,
                $subject === 7 && str_contains($haystack, 'relay')  => self::RELAY,
                $subject === 7                                      => self::POLICY,
                default                                              => null,
            };

            if ($code !== null) {
                return $code;
            }
        }

        $needles = [
            'smtp connect() failed'      => self::CONNECT,
            'could not connect'          => self::CONNECT,
            'failed to connect'          => self::CONNECT,
            'connection refused'         => self::CONNECT,
            'connection timed out'       => self::CONNECT,
            'could not authenticate'     => self::AUTH,
            'authentication failed'      => self::AUTH,
            'auth '                      => self::AUTH,
            'sender address rejected'    => self::FROM,
            'invalid address'            => self::FROM,
            'mail from'                  => self::FROM,
            'recipient address rejected' => self::RCPT,
            'mailbox unavailable'        => self::RCPT,
            'user unknown'               => self::RCPT,
            'no such user'               => self::RCPT,
            'rcpt to'                    => self::RCPT,
            'mailbox full'               => self::QUOTA,
            'over quota'                 => self::QUOTA,
            'quota exceeded'             => self::QUOTA,
            'message size exceeds'       => self::SIZE,
            'size limit exceeded'        => self::SIZE,
            'relay access denied'        => self::RELAY,
            'unable to relay'            => self::RELAY,
            'message rejected'           => self::POLICY,
            'blocked'                    => self::POLICY,
            'spam'                       => self::POLICY,
            'too many'                   => self::RATE_LIMIT,
            'try again later'            => self::RATE_LIMIT,
            'data not accepted'          => self::DATA,
            'could not start tls'        => self::TLS,
            'starttls'                   => self::TLS,
        ];

        foreach ($needles as $needle => $code) {
            if (str_contains($haystack, $needle)) {
                return $code;
            }
        }

        return self::UNKNOWN;
    }
}
