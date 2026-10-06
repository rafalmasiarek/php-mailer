<?php

declare(strict_types=1);

namespace rafalmasiarek\Mailer;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use rafalmasiarek\DnsResolver\DnsResolverInterface;

/**
 * From-scratch SMTP client (RFC 5321) — no PHPMailer dependency.
 *
 * Resolves the target host through an injected DnsResolverInterface (so DNS
 * follows the same resolver chain as the rest of the app) and connects
 * directly to the resolved IP, passing the original hostname via the stream
 * context's ssl.peer_name option for correct TLS certificate/SNI verification.
 *
 * Supports STARTTLS and implicit TLS, AUTH PLAIN/LOGIN, and an optional debug
 * callback receiving "C: ..."/"S: ..." transcript lines (pair with
 * SmtpDebugCapture for AUTH redaction + structured logging).
 *
 * @package rafalmasiarek\Mailer
 */
final class SmtpClient
{
    /** Maximum bytes read per response line — guards against a broken/malicious server. */
    private const MAX_LINE_LENGTH = 32768;

    /** @var resource|null */
    private $connection = null;

    /** @var callable(string): void|null */
    private $debugCallback = null;

    /**
     * @param DnsResolverInterface $dns     Resolver used to pin the connection's target IP.
     * @param LoggerInterface|null $logger  PSR-3 logger; NullLogger when omitted.
     * @param float                $timeout Socket read/connect timeout in seconds.
     */
    public function __construct(
        private readonly DnsResolverInterface $dns,
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly float $timeout = 30.0,
    ) {
    }

    /**
     * Sets the debug callback invoked with each raw transcript line
     * ("C: ..." for client-sent, "S: ..." for server lines).
     *
     * @param callable(string): void $callback
     *
     * @return void
     */
    public function setDebugCallback(callable $callback): void
    {
        $this->debugCallback = $callback;
    }

    /**
     * Sends one message over a fresh connection, then disconnects.
     *
     * @param string $host         SMTP server hostname.
     * @param int    $port         SMTP server port.
     * @param string $encryption   'tls' (STARTTLS), 'ssl' (implicit TLS), or '' (none).
     * @param string $username     SMTP username, or '' to skip authentication.
     * @param string $password     SMTP password.
     * @param string $envelopeFrom Envelope sender address (MAIL FROM).
     * @param string $envelopeTo   Envelope recipient address (RCPT TO).
     * @param string $rawMessage   Complete RFC 5322 message (headers + body) from MimeBuilder.
     *
     * @throws SmtpException On any protocol or transport failure.
     *
     * @return void
     */
    public function send(
        string $host,
        int $port,
        string $encryption,
        string $username,
        string $password,
        string $envelopeFrom,
        string $envelopeTo,
        string $rawMessage,
    ): void {
        try {
            $this->connect($host, $port, $encryption === 'ssl');
            $this->readGreeting();
            $capabilities = $this->ehlo($host);

            if ($encryption === 'tls') {
                $this->command('STARTTLS', [220]);
                $this->enableCrypto($host);
                $capabilities = $this->ehlo($host);
            }

            if ($username !== '') {
                $this->authenticate($username, $password, $capabilities);
            }

            $this->command('MAIL FROM:<' . $envelopeFrom . '>', [250]);
            $this->command('RCPT TO:<' . $envelopeTo . '>', [250, 251]);
            $this->sendData($rawMessage);

            $this->logger->info('mail.sent', ['to' => $envelopeTo]);
        } catch (\Throwable $e) {
            $this->logger->error('mail.failed', ['to' => $envelopeTo, 'error' => $e->getMessage()]);
            throw $e instanceof SmtpException ? $e : new SmtpException($e->getMessage(), 0, $e);
        } finally {
            $this->quit();
        }
    }

    /**
     * Resolves the host via DnsResolverInterface and opens the socket.
     *
     * @param string $host
     * @param int    $port
     * @param bool   $implicitTls
     *
     * @throws SmtpException
     *
     * @return void
     */
    private function connect(string $host, int $port, bool $implicitTls): void
    {
        $answer = $this->dns->resolve($host);
        if ($answer->records === []) {
            throw new SmtpException("Could not resolve SMTP host \"{$host}\".");
        }
        $ip = $answer->records[0];

        $scheme = $implicitTls ? 'ssl' : 'tcp';
        $target = \str_contains($ip, ':') ? "{$scheme}://[{$ip}]:{$port}" : "{$scheme}://{$ip}:{$port}";

        $context = \stream_context_create([
            'ssl' => [
                'peer_name'        => $host,
                'verify_peer'      => true,
                'verify_peer_name' => true,
                'SNI_enabled'      => true,
            ],
        ]);

        $errno  = 0;
        $errstr = '';
        $connection = @\stream_socket_client(
            $target,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($connection === false) {
            throw new SmtpException("Could not connect to SMTP server \"{$host}:{$port}\": {$errstr}");
        }

        \stream_set_timeout($connection, (int) $this->timeout);
        $this->connection = $connection;
    }

    /**
     * Upgrades the current connection to TLS after STARTTLS.
     *
     * @param string $host Original hostname, for SNI (already set on the stream context at connect time).
     *
     * @throws SmtpException
     *
     * @return void
     */
    private function enableCrypto(string $host): void
    {
        $ok = @\stream_socket_enable_crypto($this->mustConnection(), true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if ($ok !== true) {
            throw new SmtpException("Could not start TLS connection to \"{$host}\".");
        }
    }

    /**
     * Reads and validates the server's initial 220 greeting.
     *
     * @throws SmtpException
     *
     * @return void
     */
    private function readGreeting(): void
    {
        $response = $this->readResponse();
        if ($response['code'] !== 220) {
            throw new SmtpException("Unexpected SMTP greeting: {$response['text']}");
        }
    }

    /**
     * Sends EHLO and returns the server's advertised capability lines.
     *
     * @param string $host
     *
     * @throws SmtpException
     *
     * @return list<string> Capability lines, uppercase.
     */
    private function ehlo(string $host): array
    {
        $local = \gethostname();
        $response = $this->command('EHLO ' . ($local !== false ? $local : $host), [250]);

        return \array_map('strtoupper', \array_slice(\explode("\n", $response), 1));
    }

    /**
     * Authenticates using AUTH PLAIN when the server advertises it, else AUTH LOGIN.
     *
     * @param string        $username
     * @param string        $password
     * @param list<string>  $capabilities EHLO capability lines.
     *
     * @throws SmtpException
     *
     * @return void
     */
    private function authenticate(string $username, string $password, array $capabilities): void
    {
        $authLine = '';
        foreach ($capabilities as $line) {
            if (\str_starts_with($line, 'AUTH ')) {
                $authLine = $line;
                break;
            }
        }

        if (\str_contains($authLine, 'PLAIN')) {
            $credential = \base64_encode("\0{$username}\0{$password}");
            $this->command('AUTH PLAIN ' . $credential, [235]);
            return;
        }

        if (\str_contains($authLine, 'LOGIN')) {
            $this->command('AUTH LOGIN', [334]);
            $this->command(\base64_encode($username), [334]);
            $this->command(\base64_encode($password), [235]);
            return;
        }

        throw new SmtpException('SMTP server does not advertise a supported AUTH mechanism (PLAIN/LOGIN).');
    }

    /**
     * Sends the DATA command and the message body with dot-stuffing.
     *
     * @param string $rawMessage
     *
     * @throws SmtpException
     *
     * @return void
     */
    private function sendData(string $rawMessage): void
    {
        $this->command('DATA', [354]);

        $stuffed = \preg_replace('/\r\n\./', "\r\n..", $rawMessage) ?? $rawMessage;
        if (\str_starts_with($stuffed, '.')) {
            $stuffed = '.' . $stuffed;
        }

        $this->write($stuffed);
        $this->write("\r\n.\r\n");

        $response = $this->readResponse();
        if ($response['code'] !== 250) {
            throw new SmtpException("SMTP server rejected the message data: {$response['text']}");
        }
    }

    /**
     * Sends QUIT and closes the connection. Best-effort — never throws.
     *
     * @return void
     */
    private function quit(): void
    {
        if ($this->connection === null) {
            return;
        }

        try {
            $this->write('QUIT' . "\r\n");
            $this->debug('C: QUIT');
            $this->readResponse();
        } catch (\Throwable) {
            // best effort
        } finally {
            \fclose($this->connection);
            $this->connection = null;
        }
    }

    /**
     * Sends one command and validates the response code.
     *
     * Rejects embedded CR/LF in the command — prevents SMTP command/header
     * injection if a caller-supplied value (address, subject) ever contained
     * a newline.
     *
     * @param string     $command
     * @param list<int>  $expect Acceptable response codes.
     *
     * @throws SmtpException
     *
     * @return string The full (possibly multiline) response text.
     */
    private function command(string $command, array $expect): string
    {
        if (\str_contains($command, "\r") || \str_contains($command, "\n")) {
            throw new SmtpException('SMTP command contained embedded line breaks.');
        }

        $this->write($command . "\r\n");
        $this->debug('C: ' . $command);

        $response = $this->readResponse();
        if (!\in_array($response['code'], $expect, true)) {
            throw new SmtpException("Unexpected response to \"{$command}\": {$response['text']}");
        }

        return $response['text'];
    }

    /**
     * Writes raw bytes to the connection.
     *
     * @param string $data
     *
     * @throws SmtpException
     *
     * @return void
     */
    private function write(string $data): void
    {
        $result = @\fwrite($this->mustConnection(), $data);
        if ($result === false) {
            throw new SmtpException('Failed to write to SMTP connection.');
        }
    }

    /**
     * Reads one (possibly multiline) SMTP response.
     *
     * Continuation lines are "CODE-text\r\n"; the final line is
     * "CODE text\r\n" (4th byte is a space, or end of line). Accumulates
     * until the terminator — never assumes one line is the whole response.
     *
     * Retries stream_select() on EINTR (a signal interrupting the call)
     * instead of treating it as a read failure.
     *
     * @throws SmtpException
     *
     * @return array{code: int, text: string}
     */
    private function readResponse(): array
    {
        $connection = $this->mustConnection();
        $lines = [];
        $code  = 0;

        while (true) {
            $line = $this->readLine($connection);
            $this->debug('S: ' . \rtrim($line));

            if (!\preg_match('/^(\d{3})([ \-])(.*)$/s', $line, $m)) {
                throw new SmtpException("Malformed SMTP response: \"{$line}\"");
            }

            $code = (int) $m[1];
            $lines[] = \rtrim($m[3], "\r\n");

            if ($m[2] === ' ') {
                break;
            }
        }

        return ['code' => $code, 'text' => \implode("\n", $lines)];
    }

    /**
     * Reads a single line (up to MAX_LINE_LENGTH bytes), retrying on EINTR.
     *
     * @param resource $connection
     *
     * @throws SmtpException
     *
     * @return string
     */
    private function readLine($connection): string
    {
        while (true) {
            $read   = [$connection];
            $write  = null;
            $except = null;

            \set_error_handler(static fn (): bool => true);
            $n = \stream_select($read, $write, $except, (int) $this->timeout);
            \restore_error_handler();

            if ($n === false) {
                $lastError = \error_get_last();
                $message = $lastError['message'] ?? '';
                if (\stripos($message, 'interrupted system call') !== false) {
                    continue;
                }
                throw new SmtpException('SMTP connection select() failed.');
            }

            if ($n === 0) {
                throw new SmtpException('SMTP connection timed out while waiting for a response.');
            }

            $line = @\fgets($connection, self::MAX_LINE_LENGTH);
            if ($line === false) {
                throw new SmtpException('SMTP connection closed unexpectedly.');
            }

            $meta = \stream_get_meta_data($connection);
            if ($meta['timed_out'] ?? false) {
                throw new SmtpException('SMTP connection timed out while reading a response.');
            }

            return $line;
        }
    }

    /**
     * @return resource
     *
     * @throws SmtpException
     */
    private function mustConnection()
    {
        if ($this->connection === null) {
            throw new SmtpException('SMTP connection is not open.');
        }

        return $this->connection;
    }

    /**
     * @param string $line
     *
     * @return void
     */
    private function debug(string $line): void
    {
        if ($this->debugCallback !== null) {
            ($this->debugCallback)($line);
        }
    }
}
