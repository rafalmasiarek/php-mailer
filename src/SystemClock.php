<?php

declare(strict_types=1);

namespace rafalmasiarek\Mailer;

use Psr\Clock\ClockInterface;

/**
 * Default PSR-20 clock backed by the system time.
 *
 * @package rafalmasiarek\Mailer
 */
final class SystemClock implements ClockInterface
{
    /**
     * {@inheritDoc}
     */
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
