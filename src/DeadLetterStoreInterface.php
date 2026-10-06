<?php

declare(strict_types=1);

namespace rafalmasiarek\Mailer;

/**
 * Receives a FailedDelivery after SmtpClient::dispatch() exhausts its own
 * error handling. Implement to persist failed sends for inspection or
 * retry — SmtpClient has no built-in retry of its own, and still throws
 * SmtpException after calling push().
 *
 * @package rafalmasiarek\Mailer
 */
interface DeadLetterStoreInterface
{
    /**
     * @param FailedDelivery $delivery
     *
     * @return void
     */
    public function push(FailedDelivery $delivery): void;
}
