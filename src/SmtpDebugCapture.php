<?php

declare(strict_types=1);

namespace rafalmasiarek\Mailer;

use Psr\Clock\ClockInterface;

/**
 * Captures the raw SMTP transcript and redacts AUTH credential exchange.
 *
 * Pass the callable from getCallback() to SmtpClient's debug hook. Expects
 * SmtpClient's own transcript line format: "C: <command>" for client-sent
 * lines, "S: <response>" for server lines.
 *
 * AUTH filtering strategy:
 *   - the "C: AUTH ..." line is replaced with a redacted marker.
 *   - all subsequent lines (challenge/response base64 exchange) are dropped.
 *   - capture resumes after the server's 235 (success) or 535 (failure) response.
 *   - the 235/535 line itself is kept so the auth outcome is visible.
 *
 * @package rafalmasiarek\Mailer
 */
final class SmtpDebugCapture
{
    /** @var list<string> */
    private array $lines = [];

    /** Whether we are currently inside an AUTH credential exchange. */
    private bool $inAuthExchange = false;

    /**
     * @param ClockInterface $clock Clock used for log line timestamps.
     */
    public function __construct(private readonly ClockInterface $clock)
    {
    }

    /**
     * Returns the callable to pass to SmtpClient's debug hook.
     *
     * @return callable(string): void
     */
    public function getCallback(): callable
    {
        return function (string $line): void {
            $line = \rtrim($line);
            if ($line === '') {
                return;
            }

            if (\str_starts_with($line, 'C: AUTH')) {
                $this->inAuthExchange = true;
                $this->append('C: AUTH [CREDENTIALS REDACTED]');
                return;
            }

            if ($this->inAuthExchange) {
                if (\preg_match('/\b(235|535)\b/', $line)) {
                    $this->inAuthExchange = false;
                    $this->append($line);
                }
                return;
            }

            $this->append($line);
        };
    }

    /**
     * Returns true when at least one SMTP debug line was captured.
     *
     * @return bool
     */
    public function hasLines(): bool
    {
        return $this->lines !== [];
    }

    /**
     * Returns all captured lines.
     *
     * @return list<string>
     */
    public function getLines(): array
    {
        return $this->lines;
    }

    /**
     * Writes the captured transcript to a file, creating parent directories as needed.
     *
     * @param  string $path Absolute path to the target log file.
     * @return void
     */
    public function writeToFile(string $path): void
    {
        $dir = \dirname($path);
        if (!\is_dir($dir)) {
            \mkdir($dir, 0755, true);
        }
        \file_put_contents($path, \implode("\n", $this->lines) . "\n", LOCK_EX);
    }

    /**
     * Prepends a timestamp and stores a line.
     *
     * @param  string $line
     * @return void
     */
    private function append(string $line): void
    {
        $this->lines[] = '[' . $this->clock->now()->format('Y-m-d H:i:s') . '] ' . $line;
    }
}
