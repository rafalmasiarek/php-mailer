<?php

declare(strict_types=1);

namespace rafalmasiarek\Mailer;

/**
 * Snapshot of one failed SmtpClient::dispatch() call, handed to a
 * DeadLetterStoreInterface so the message is not lost.
 *
 * @package rafalmasiarek\Mailer
 */
final class FailedDelivery
{
    /**
     * @param string            $envelopeFrom       MAIL FROM address.
     * @param list<string>      $envelopeRecipients RCPT TO addresses.
     * @param string            $rawMessage         Complete message from MimeBuilder::build().
     * @param string            $error              The exception message that caused the failure.
     * @param \DateTimeImmutable $failedAt           When the failure occurred.
     */
    public function __construct(
        public readonly string $envelopeFrom,
        public readonly array $envelopeRecipients,
        public readonly string $rawMessage,
        public readonly string $error,
        public readonly \DateTimeImmutable $failedAt,
    ) {
    }
}
