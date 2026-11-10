# mailer
PHP mailer: from-scratch SMTP client + MIME builder (no PHPMailer), native PGP/MIME, structured PSR-3 SMTP diagnostics

<!-- BEGIN_PHP_DOCS -->

## Index

### Classes

- [`DkimSigner`](#dkimsigner) — `rafalmasiarek\Mailer`
- [`DsnOptions`](#dsnoptions) — `rafalmasiarek\Mailer`
- [`FailedDelivery`](#faileddelivery) — `rafalmasiarek\Mailer`
- [`MimeBuilder`](#mimebuilder) — `rafalmasiarek\Mailer`
- [`PgpMimeBodyBuilder`](#pgpmimebodybuilder) — `rafalmasiarek\Mailer`
- [`RawMimeBody`](#rawmimebody) — `rafalmasiarek\Mailer`
- [`SmtpClient`](#smtpclient) — `rafalmasiarek\Mailer`
- [`SmtpDebugCapture`](#smtpdebugcapture) — `rafalmasiarek\Mailer`
- [`SmtpErrorCode`](#smtperrorcode) — `rafalmasiarek\Mailer`
- [`SmtpException`](#smtpexception) — `rafalmasiarek\Mailer`
- [`SystemClock`](#systemclock) — `rafalmasiarek\Mailer`
- [`TlsOptions`](#tlsoptions) — `rafalmasiarek\Mailer`

### Interfaces

- [`DeadLetterStoreInterface`](#deadletterstoreinterface) — `rafalmasiarek\Mailer`

## `DeadLetterStoreInterface`

*interface* — `rafalmasiarek\Mailer\DeadLetterStoreInterface` · [source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/DeadLetterStoreInterface.php#L15)

Receives a FailedDelivery after SmtpClient::dispatch() exhausts its own error handling. Implement to persist failed sends for inspection or retry — SmtpClient has no built-in retry of its own, and still throws SmtpException after calling push().

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`push()`](#deadletterstoreinterface-push) | public | `void` | — |

<details>
<summary><code>push()</code></summary>

#### `DeadLetterStoreInterface push()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/DeadLetterStoreInterface.php#L22)

```php
public function push(FailedDelivery $delivery): void
```

| Param | Type | Description |
|---|---|---|
| `$delivery` | [`FailedDelivery`](#faileddelivery) | — |

</details>

## `DkimSigner`

*final class* — `rafalmasiarek\Mailer\DkimSigner` · [source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/DkimSigner.php#L16)

Signs a complete RFC 5322 message with DKIM (RFC 6376), relaxed/relaxed canonicalization, rsa-sha256. Operates on the raw message string produced by MimeBuilder::build() — no knowledge of SMTP or transport; the returned string is prepended with a DKIM-Signature header and otherwise identical, ready to pass to SmtpClient as-is.

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`sign()`](#dkimsigner-sign) | public static | `string` | — |
| [`splitMessage()`](#dkimsigner-splitmessage) | private static | `array{headerBlock: string, body: string}` | — |
| [`parseHeaders()`](#dkimsigner-parseheaders) | private static | `list<array{name: string, value: string}>` | Parses an unfolded header block into an ordered list of [name, value] pairs, reassembling folded continuation lines into the owning header's value. |
| [`findHeader()`](#dkimsigner-findheader) | private static | `array{string, string}\|null` | — |
| [`canonicalizeHeaderRelaxed()`](#dkimsigner-canonicalizeheaderrelaxed) | private static | `string` | RFC 6376 §3.4.2 relaxed header canonicalization: lowercase the name, unfold continuation lines, collapse whitespace runs to a single space, trim the value. |
| [`canonicalizeBodyRelaxed()`](#dkimsigner-canonicalizebodyrelaxed) | private static | `string` | RFC 6376 §3.4.4 relaxed body canonicalization: collapse whitespace runs within each line, strip trailing whitespace per line, remove trailing blank lines, end with exactly one CRLF unless the body is empty. |

<details>
<summary><code>sign()</code></summary>

#### `DkimSigner sign()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/DkimSigner.php#L29)

```php
public static function sign(string $rawMessage, string $privateKeyPem, string $domain, string $selector, array $headersToSign): string
```

| Param | Type | Description |
|---|---|---|
| `$rawMessage` | `string` | Complete message from MimeBuilder::build(). |
| `$privateKeyPem` | `string` | PEM-encoded RSA private key. |
| `$domain` | `string` | Signing domain (d= tag). |
| `$selector` | `string` | DKIM selector (s= tag). |
| `$headersToSign` | `list<string>` | Header field names to sign, in signing order. |

**Returns:** `string` — The message with a DKIM-Signature header prepended.

| Throws | When |
|---|---|
| [`SmtpException`](#smtpexception) | When the private key is invalid or signing fails. |

</details>

<details>
<summary><code>splitMessage()</code></summary>

#### `DkimSigner splitMessage()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/DkimSigner.php#L84)

```php
private static function splitMessage(string $rawMessage): array
```

| Param | Type | Description |
|---|---|---|
| `$rawMessage` | `string` | — |

**Returns:** `array{headerBlock: string, body: string}` — [headerBlock, body]

</details>

<details>
<summary><code>parseHeaders()</code></summary>

#### `DkimSigner parseHeaders()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/DkimSigner.php#L102)

```php
private static function parseHeaders(string $headerBlock): array
```

Parses an unfolded header block into an ordered list of [name, value] pairs, reassembling folded continuation lines into the owning header's value.

| Param | Type | Description |
|---|---|---|
| `$headerBlock` | `string` | — |

**Returns:** `list<array{name: string, value: string}>`

</details>

<details>
<summary><code>findHeader()</code></summary>

#### `DkimSigner findHeader()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/DkimSigner.php#L135)

```php
private static function findHeader(array $headers, string $name): ?array
```

| Param | Type | Description |
|---|---|---|
| `$headers` | `list<array{string, string}>` | — |
| `$name` | `string` | — |

**Returns:** `array{string, string}\|null`

</details>

<details>
<summary><code>canonicalizeHeaderRelaxed()</code></summary>

#### `DkimSigner canonicalizeHeaderRelaxed()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/DkimSigner.php#L156)

```php
private static function canonicalizeHeaderRelaxed(string $name, string $value): string
```

RFC 6376 §3.4.2 relaxed header canonicalization: lowercase the name, unfold continuation lines, collapse whitespace runs to a single space, trim the value.

| Param | Type | Description |
|---|---|---|
| `$name` | `string` | — |
| `$value` | `string` | — |

**Returns:** `string`

</details>

<details>
<summary><code>canonicalizeBodyRelaxed()</code></summary>

#### `DkimSigner canonicalizeBodyRelaxed()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/DkimSigner.php#L175)

```php
private static function canonicalizeBodyRelaxed(string $body): string
```

RFC 6376 §3.4.4 relaxed body canonicalization: collapse whitespace runs within each line, strip trailing whitespace per line, remove trailing blank lines, end with exactly one CRLF unless the body is empty.

| Param | Type | Description |
|---|---|---|
| `$body` | `string` | — |

**Returns:** `string`

</details>

## `DsnOptions`

*final class* — `rafalmasiarek\Mailer\DsnOptions` · [source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/DsnOptions.php#L14)

Delivery Status Notification request (RFC 3461). Applied to the MAIL FROM/RCPT TO commands only when the server's EHLO response advertises the DSN extension — silently omitted otherwise.

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`__construct()`](#dsnoptions-__construct) | public | — | — |

<details>
<summary><code>__construct()</code></summary>

#### `DsnOptions __construct()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/DsnOptions.php#L24)

```php
public function __construct(string $ret, array $notify, ?string $envId)
```

| Param | Type | Description |
|---|---|---|
| `$ret` | `'HDRS'\|'FULL'` | How much of the original message a DSN report should include. |
| `$notify` | `list<'SUCCESS'\|'FAILURE'\|'DELAY'\|'NEVER'>` | Events to request a DSN report for. |
| `$envId` | `string\|null` | Opaque id echoed back as Original-Envelope-Id in any DSN report; must be xtext-safe (printable ASCII, no "+" or "="). |

</details>

## `FailedDelivery`

*final class* — `rafalmasiarek\Mailer\FailedDelivery` · [source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/FailedDelivery.php#L13)

Snapshot of one failed SmtpClient::dispatch() call, handed to a DeadLetterStoreInterface so the message is not lost.

**Depends on:** `\DateTimeImmutable`

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`__construct()`](#faileddelivery-__construct) | public | — | — |

<details>
<summary><code>__construct()</code></summary>

#### `FailedDelivery __construct()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/FailedDelivery.php#L22)

```php
public function __construct(string $envelopeFrom, array $envelopeRecipients, string $rawMessage, string $error, \DateTimeImmutable $failedAt)
```

| Param | Type | Description |
|---|---|---|
| `$envelopeFrom` | `string` | MAIL FROM address. |
| `$envelopeRecipients` | `list<string>` | RCPT TO addresses. |
| `$rawMessage` | `string` | Complete message from MimeBuilder::build(). |
| `$error` | `string` | The exception message that caused the failure. |
| `$failedAt` | `\DateTimeImmutable` | When the failure occurred. |

</details>

## `MimeBuilder`

*final class* — `rafalmasiarek\Mailer\MimeBuilder` · [source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/MimeBuilder.php#L23)

Builds a complete RFC 5322 message (headers + body) ready to send as SMTP DATA content. Body nesting, innermost to outermost: resolveBody() (text/plain, text/html, or multipart/alternative, or a verbatim RawMimeBody) is wrapped in multipart/related when inline-embedded parts are present, which is in turn wrapped in multipart/mixed when attachments are present. Matches the RFC 2046 §5.1 recommended nesting order (mixed > related > alternative). Header values are passed through PHP's own mb_encode_mimeheader() (RFC 2047) so non-ASCII subjects/display names degrade safely rather than corrupting the message.

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`build()`](#mimebuilder-build) | public static | `string` | — |
| [`resolveBody()`](#mimebuilder-resolvebody) | private static | `array{contentType: string, encoding: string, body: string}` | Resolves the top-level content-type/encoding/body before any related/mixed wrapping. |
| [`wrapInRelated()`](#mimebuilder-wrapinrelated) | private static | `string` | Wraps the already-resolved body plus inline parts in multipart/related. |
| [`wrapInMixed()`](#mimebuilder-wrapinmixed) | private static | `string` | Wraps the already-resolved body plus attachments in multipart/mixed. |
| [`chunkBase64()`](#mimebuilder-chunkbase64) | private static | `string` | Base64-encodes content and splits it into RFC-compliant 76-char lines. |
| [`formatAddress()`](#mimebuilder-formataddress) | private static | `string` | Formats a "Name <email>" address header value, encoding the name if needed. |
| [`encodeHeader()`](#mimebuilder-encodeheader) | private static | `string` | Encodes a header value per RFC 2047 when it contains non-ASCII bytes. |
| [`boundary()`](#mimebuilder-boundary) | private static | `string` | Generates a unique MIME boundary string. |
| [`guessMimeType()`](#mimebuilder-guessmimetype) | private static | `string` | Best-effort MIME type guess from a file extension; falls back to a generic binary type when unknown. |

<details>
<summary><code>build()</code></summary>

#### `MimeBuilder build()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/MimeBuilder.php#L51)

```php
public static function build(string $fromEmail, string $fromName, string $toEmail, string $toName, ?string $replyTo, string $subject, ?string $htmlBody, ?string $textBody, array $attachments, ?RawMimeBody $rawBody, string $messageId, array $cc, array $embeds, array $customHeaders, ?string $inReplyTo, array $references): string
```

| Param | Type | Description |
|---|---|---|
| `$fromEmail` | `string` | Sender address. |
| `$fromName` | `string` | Sender display name (may be empty). |
| `$toEmail` | `string` | Recipient address. |
| `$toName` | `string` | Recipient display name (may be empty). |
| `$replyTo` | `string\|null` | Reply-To address, or null. |
| `$subject` | `string` | Subject line. |
| `$htmlBody` | `string\|null` | HTML body, or null when not used. |
| `$textBody` | `string\|null` | Plain-text body, or null when not used. |
| `$attachments` | `list<array{path: string, name?: string, mimeType?: string}>` | Files to attach. |
| `$rawBody` | `RawMimeBody\|null` | Verbatim body, takes precedence over html/text when set. |
| `$messageId` | `string` | Value for the Message-ID header (without angle brackets). |
| `$cc` | `list<array{email: string, name?: string}>` | Carbon-copy recipients (header only — envelope recipients are a transport concern, not built here). |
| `$embeds` | `list<array{path: string, cid: string, name?: string, mimeType?: string}>` | Inline parts referenced from the HTML body as "cid:...". Ignored when $rawBody is set. |
| `$customHeaders` | `array<string, string>` | Additional raw header lines, name => value. |
| `$inReplyTo` | `string\|null` | Message-ID this message replies to (without angle brackets). |
| `$references` | `list<string>` | Message-IDs for the References header (without angle brackets). |

**Returns:** `string` — Complete message: headers, blank line, body.

| Throws | When |
|---|---|
| [`SmtpException`](#smtpexception) | When an attachment/embed path is not readable, or a custom header value contains embedded line breaks. |

</details>

<details>
<summary><code>resolveBody()</code></summary>

#### `MimeBuilder resolveBody()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/MimeBuilder.php#L144)

```php
private static function resolveBody(?string $htmlBody, ?string $textBody, ?RawMimeBody $rawBody): array
```

Resolves the top-level content-type/encoding/body before any related/mixed wrapping.

| Param | Type | Description |
|---|---|---|
| `$htmlBody` | `string\|null` | — |
| `$textBody` | `string\|null` | — |
| `$rawBody` | `RawMimeBody\|null` | — |

**Returns:** `array{contentType: string, encoding: string, body: string}` — [contentType, encoding, body]

</details>

<details>
<summary><code>wrapInRelated()</code></summary>

#### `MimeBuilder wrapInRelated()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/MimeBuilder.php#L186)

```php
private static function wrapInRelated(string $boundary, string $innerContentType, string $innerEncoding, string $innerBody, array $embeds): string
```

Wraps the already-resolved body plus inline parts in multipart/related.

| Param | Type | Description |
|---|---|---|
| `$boundary` | `string` | — |
| `$innerContentType` | `string` | — |
| `$innerEncoding` | `string` | — |
| `$innerBody` | `string` | — |
| `$embeds` | `list<array{path: string, cid: string, name?: string, mimeType?: string}>` | — |

**Returns:** `string`

| Throws | When |
|---|---|
| [`SmtpException`](#smtpexception) | When an embed path is not readable. |

</details>

<details>
<summary><code>wrapInMixed()</code></summary>

#### `MimeBuilder wrapInMixed()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/MimeBuilder.php#L237)

```php
private static function wrapInMixed(string $boundary, string $innerContentType, string $innerEncoding, string $innerBody, array $attachments): string
```

Wraps the already-resolved body plus attachments in multipart/mixed.

| Param | Type | Description |
|---|---|---|
| `$boundary` | `string` | — |
| `$innerContentType` | `string` | — |
| `$innerEncoding` | `string` | — |
| `$innerBody` | `string` | — |
| `$attachments` | `list<array{path: string, name?: string, mimeType?: string}>` | — |

**Returns:** `string`

| Throws | When |
|---|---|
| [`SmtpException`](#smtpexception) | When an attachment path is not readable. |

</details>

<details>
<summary><code>chunkBase64()</code></summary>

#### `MimeBuilder chunkBase64()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/MimeBuilder.php#L280)

```php
private static function chunkBase64(string $data): string
```

Base64-encodes content and splits it into RFC-compliant 76-char lines.

| Param | Type | Description |
|---|---|---|
| `$data` | `string` | — |

**Returns:** `string` — Base64 content, chunked, CRLF-terminated.

</details>

<details>
<summary><code>formatAddress()</code></summary>

#### `MimeBuilder formatAddress()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/MimeBuilder.php#L293)

```php
private static function formatAddress(string $email, string $name): string
```

Formats a "Name <email>" address header value, encoding the name if needed.

| Param | Type | Description |
|---|---|---|
| `$email` | `string` | — |
| `$name` | `string` | — |

**Returns:** `string`

</details>

<details>
<summary><code>encodeHeader()</code></summary>

#### `MimeBuilder encodeHeader()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/MimeBuilder.php#L309)

```php
private static function encodeHeader(string $value): string
```

Encodes a header value per RFC 2047 when it contains non-ASCII bytes.

| Param | Type | Description |
|---|---|---|
| `$value` | `string` | — |

**Returns:** `string`

</details>

<details>
<summary><code>boundary()</code></summary>

#### `MimeBuilder boundary()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/MimeBuilder.php#L325)

```php
private static function boundary(string $label): string
```

Generates a unique MIME boundary string.

| Param | Type | Description |
|---|---|---|
| `$label` | `string` | — |

**Returns:** `string`

</details>

<details>
<summary><code>guessMimeType()</code></summary>

#### `MimeBuilder guessMimeType()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/MimeBuilder.php#L338)

```php
private static function guessMimeType(string $path): string
```

Best-effort MIME type guess from a file extension; falls back to a generic binary type when unknown.

| Param | Type | Description |
|---|---|---|
| `$path` | `string` | — |

**Returns:** `string`

</details>

## `PgpMimeBodyBuilder`

*final class* — `rafalmasiarek\Mailer\PgpMimeBodyBuilder` · [source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/PgpMimeBodyBuilder.php#L17)

Builds a `multipart/encrypted` (RFC 3156, PGP/MIME) body from an already PGP-armored ciphertext block. Standalone — has no dependency on MailMessage/Mailer/anything else in this library. Feed the returned RawMimeBody into whatever "send this exact body verbatim" mechanism the caller's higher-level mail API provides.

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`build()`](#pgpmimebodybuilder-build) | public static | [`RawMimeBody`](#rawmimebody) | Builds the raw multipart/encrypted body for an armored PGP MESSAGE block. |
| [`normalizeCrlf()`](#pgpmimebodybuilder-normalizecrlf) | private static | `string` | Normalises line endings to CRLF, as required by MIME specs. |

<details>
<summary><code>build()</code></summary>

#### `PgpMimeBodyBuilder build()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/PgpMimeBodyBuilder.php#L26)

```php
public static function build(string $armoredCiphertext): RawMimeBody
```

Builds the raw multipart/encrypted body for an armored PGP MESSAGE block.

| Param | Type | Description |
|---|---|---|
| `$armoredCiphertext` | `string` | Armored "-----BEGIN PGP MESSAGE-----" block. |

**Returns:** [`RawMimeBody`](#rawmimebody) — Body/content-type/encoding to send verbatim.

</details>

<details>
<summary><code>normalizeCrlf()</code></summary>

#### `PgpMimeBodyBuilder normalizeCrlf()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/PgpMimeBodyBuilder.php#L60)

```php
private static function normalizeCrlf(string $s): string
```

Normalises line endings to CRLF, as required by MIME specs.

| Param | Type | Description |
|---|---|---|
| `$s` | `string` | Input string with any line endings. |

**Returns:** `string` — String with all line endings replaced by CRLF.

</details>

## `RawMimeBody`

*final readonly class* — `rafalmasiarek\Mailer\RawMimeBody` · [source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/RawMimeBody.php#L13)

A fully-formed MIME body to send verbatim, bypassing normal alternative/mixed construction.

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`__construct()`](#rawmimebody-__construct) | public | — | — |

<details>
<summary><code>__construct()</code></summary>

#### `RawMimeBody __construct()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/RawMimeBody.php#L20)

```php
public function __construct(string $body, string $contentType, string $encoding)
```

| Param | Type | Description |
|---|---|---|
| `$body` | `string` | Raw MIME body content. |
| `$contentType` | `string` | Content-Type header value for this body (may include boundary=…). |
| `$encoding` | `string` | Content-Transfer-Encoding value. |

</details>

## `SmtpClient`

*final class* — `rafalmasiarek\Mailer\SmtpClient` · [source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/SmtpClient.php#L38)

From-scratch SMTP client (RFC 5321) — no PHPMailer dependency. Resolves the target host through an injected DnsResolverInterface (so DNS follows the same resolver chain as the rest of the app) and connects directly to the resolved IP, passing the original hostname via the stream context's ssl.peer_name option for correct TLS certificate/SNI verification. Supports STARTTLS and implicit TLS, AUTH PLAIN/LOGIN, and an optional debug callback receiving "C: ..."/"S: ..." transcript lines (pair with SmtpDebugCapture for AUTH redaction + structured logging). open()/dispatch()/close() allow sending multiple messages over one connection (session reuse); send() is a single-message convenience wrapper around the three. $maxPerSecond throttles between dispatch() calls within a session; $restartThreshold reconnects automatically after N dispatches, for servers that rate-limit or drop long-lived connections. DsnOptions requests a Delivery Status Notification (RFC 3461) per dispatch() call, applied only when the server's EHLO advertises DSN. $deadLetterStore, when set, receives a FailedDelivery on any dispatch() failure — SmtpException is still thrown either way. $tls controls certificate verification and an optional client certificate for mutual TLS.

**Depends on:** `DnsResolverInterface`, `LoggerInterface`, [`DeadLetterStoreInterface`](#deadletterstoreinterface), [`TlsOptions`](#tlsoptions), `ClockInterface`

| Property | Type | Visibility | Description |
|---|---|---|---|
| `$connection` | `resource\|null` | private | — |
| `$debugCallback` | `callable(string):` | private | void\|null |
| `$dispatchCount` | `int` | private | — |
| `$lastDispatchAt` | `float` | private | — |
| `$openHost` | `string` | private | — |
| `$openPort` | `int` | private | — |
| `$openEncryption` | `string` | private | — |
| `$openUsername` | `string` | private | — |
| `$openPassword` | `string` | private | — |
| `$capabilities` | `list<string>` | private | EHLO capability lines from the most recent open(), uppercase. |

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`__construct()`](#smtpclient-__construct) | public | — | — |
| [`setDebugCallback()`](#smtpclient-setdebugcallback) | public | `void` | Sets the debug callback invoked with each raw transcript line ("C: ..." for client-sent, "S: ..." for server lines). |
| [`send()`](#smtpclient-send) | public | `void` | Sends one message over a fresh connection, then disconnects. Convenience wrapper around open()/dispatch()/close() for callers that don't need session reuse. |
| [`open()`](#smtpclient-open) | public | `void` | Opens a connection and authenticates, ready for one or more dispatch() calls. |
| [`dispatch()`](#smtpclient-dispatch) | public | `void` | Sends one message over the already-open connection. Applies the configured $maxPerSecond throttle before sending, and reconnects automatically afterward once $restartThreshold dispatches have been reached on this connection. |
| [`close()`](#smtpclient-close) | public | `void` | Sends QUIT and closes the connection opened by open(). Best-effort — never throws. |
| [`throttle()`](#smtpclient-throttle) | private | `void` | Sleeps as needed so dispatch() calls within a session respect $maxPerSecond. |
| [`pushToDeadLetterStore()`](#smtpclient-pushtodeadletterstore) | private | `void` | Best-effort push to the configured DeadLetterStoreInterface. A store failure must never mask the real SmtpException about to be thrown. |
| [`restart()`](#smtpclient-restart) | private | `void` | Closes and reopens the connection with the same credentials used by the last open() call. |
| [`connect()`](#smtpclient-connect) | private | `void` | Resolves the host via DnsResolverInterface and opens the socket. |
| [`enableCrypto()`](#smtpclient-enablecrypto) | private | `void` | Upgrades the current connection to TLS after STARTTLS. |
| [`readGreeting()`](#smtpclient-readgreeting) | private | `void` | Reads and validates the server's initial 220 greeting. |
| [`ehlo()`](#smtpclient-ehlo) | private | `list<string>` | Sends EHLO and returns the server's advertised capability lines. |
| [`authenticate()`](#smtpclient-authenticate) | private | `void` | Authenticates using AUTH PLAIN when the server advertises it, else AUTH LOGIN. |
| [`sendData()`](#smtpclient-senddata) | private | `void` | Sends the DATA command and the message body with dot-stuffing. |
| [`quit()`](#smtpclient-quit) | private | `void` | Sends QUIT and closes the connection. Best-effort — never throws. |
| [`command()`](#smtpclient-command) | private | `string` | Sends one command and validates the response code. Rejects embedded CR/LF in the command — prevents SMTP command/header injection if a caller-supplied value (address, subject) ever contained a newline. |
| [`write()`](#smtpclient-write) | private | `void` | Writes raw bytes to the connection. |
| [`readResponse()`](#smtpclient-readresponse) | private | `array{code: int, text: string}` | Reads one (possibly multiline) SMTP response. Continuation lines are "CODE-text\r\n"; the final line is "CODE text\r\n" (4th byte is a space, or end of line). Accumulates until the terminator — never assumes one line is the whole response. Retries stream_select() on EINTR (a signal interrupting the call) instead of treating it as a read failure. |
| [`readLine()`](#smtpclient-readline) | private | `string` | Reads a single line (up to MAX_LINE_LENGTH bytes), retrying on EINTR. |
| [`mustConnection()`](#smtpclient-mustconnection) | private | `resource` | — |
| [`debug()`](#smtpclient-debug) | private | `void` | — |

<details>
<summary><code>__construct()</code></summary>

#### `SmtpClient __construct()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/SmtpClient.php#L81)

```php
public function __construct(DnsResolverInterface $dns, LoggerInterface $logger, float $timeout, float $maxPerSecond, int $restartThreshold, ?DeadLetterStoreInterface $deadLetterStore, TlsOptions $tls, ClockInterface $clock)
```

| Param | Type | Description |
|---|---|---|
| `$dns` | `DnsResolverInterface` | Resolver used to pin the connection's target IP. |
| `$logger` | `LoggerInterface\|null` | PSR-3 logger; NullLogger when omitted. |
| `$timeout` | `float` | Socket read/connect timeout in seconds. |
| `$maxPerSecond` | `float` | Maximum dispatch() calls per second within a session; 0 disables throttling. |
| `$restartThreshold` | `int` | Reconnect automatically after this many dispatch() calls; 0 disables restarting. |
| `$deadLetterStore` | `DeadLetterStoreInterface\|null` | Receives a FailedDelivery when dispatch() fails; SmtpException is still thrown either way. |
| `$tls` | [`TlsOptions`](#tlsoptions) | TLS verification/client-certificate behavior; defaults (verify against the system CA store, no client certificate) when omitted. Applies to both implicit TLS and STARTTLS. |
| `$clock` | `ClockInterface` | Timestamps FailedDelivery::$failedAt; system clock when omitted. |

</details>

<details>
<summary><code>setDebugCallback()</code></summary>

#### `SmtpClient setDebugCallback()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/SmtpClient.php#L101)

```php
public function setDebugCallback(callable $callback): void
```

Sets the debug callback invoked with each raw transcript line ("C: ..." for client-sent, "S: ..." for server lines).

| Param | Type | Description |
|---|---|---|
| `$callback` | `callable` | — |

</details>

<details>
<summary><code>send()</code></summary>

#### `SmtpClient send()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/SmtpClient.php#L128)

```php
public function send(string $host, int $port, string $encryption, string $username, string $password, string $envelopeFrom, array $envelopeRecipients, string $rawMessage, ?DsnOptions $dsn): void
```

Sends one message over a fresh connection, then disconnects. Convenience wrapper around open()/dispatch()/close() for callers that don't need session reuse.

| Param | Type | Description |
|---|---|---|
| `$host` | `string` | SMTP server hostname. |
| `$port` | `int` | SMTP server port. |
| `$encryption` | `string` | 'tls' (STARTTLS), 'ssl' (implicit TLS), or '' (none). |
| `$username` | `string` | SMTP username, or '' to skip authentication. |
| `$password` | `string` | SMTP password. |
| `$envelopeFrom` | `string` | Envelope sender address (MAIL FROM). |
| `$envelopeRecipients` | `list<string>` | Envelope recipient addresses (RCPT TO) — to, cc, and bcc addresses combined; headers are built separately by MimeBuilder and never derived from this list. |
| `$rawMessage` | `string` | Complete RFC 5322 message (headers + body) from MimeBuilder. |
| `$dsn` | `DsnOptions\|null` | Delivery Status Notification request; ignored when the server doesn't advertise the DSN extension. |

| Throws | When |
|---|---|
| [`SmtpException`](#smtpexception) | On any protocol or transport failure. |

</details>

<details>
<summary><code>open()</code></summary>

#### `SmtpClient open()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/SmtpClient.php#L166)

```php
public function open(string $host, int $port, string $encryption, string $username, string $password): void
```

Opens a connection and authenticates, ready for one or more dispatch() calls.

| Param | Type | Description |
|---|---|---|
| `$host` | `string` | SMTP server hostname. |
| `$port` | `int` | SMTP server port. |
| `$encryption` | `string` | 'tls' (STARTTLS), 'ssl' (implicit TLS), or '' (none). |
| `$username` | `string` | SMTP username, or '' to skip authentication. |
| `$password` | `string` | SMTP password. |

| Throws | When |
|---|---|
| [`SmtpException`](#smtpexception) | On any protocol or transport failure. |

</details>

<details>
<summary><code>dispatch()</code></summary>

#### `SmtpClient dispatch()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/SmtpClient.php#L207)

```php
public function dispatch(string $envelopeFrom, array $envelopeRecipients, string $rawMessage, ?DsnOptions $dsn): void
```

Sends one message over the already-open connection. Applies the configured $maxPerSecond throttle before sending, and reconnects automatically afterward once $restartThreshold dispatches have been reached on this connection.

| Param | Type | Description |
|---|---|---|
| `$envelopeFrom` | `string` | Envelope sender address (MAIL FROM). |
| `$envelopeRecipients` | `list<string>` | Envelope recipient addresses (RCPT TO). |
| `$rawMessage` | `string` | Complete RFC 5322 message (headers + body) from MimeBuilder. |
| `$dsn` | `DsnOptions\|null` | Delivery Status Notification request; ignored when the server doesn't advertise the DSN extension. |

| Throws | When |
|---|---|
| [`SmtpException`](#smtpexception) | On any protocol or transport failure. |

</details>

<details>
<summary><code>close()</code></summary>

#### `SmtpClient close()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/SmtpClient.php#L252)

```php
public function close(): void
```

Sends QUIT and closes the connection opened by open(). Best-effort — never throws.

</details>

<details>
<summary><code>throttle()</code></summary>

#### `SmtpClient throttle()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/SmtpClient.php#L262)

```php
private function throttle(): void
```

Sleeps as needed so dispatch() calls within a session respect $maxPerSecond.

</details>

<details>
<summary><code>pushToDeadLetterStore()</code></summary>

#### `SmtpClient pushToDeadLetterStore()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/SmtpClient.php#L286)

```php
private function pushToDeadLetterStore(string $envelopeFrom, array $envelopeRecipients, string $rawMessage, string $error): void
```

Best-effort push to the configured DeadLetterStoreInterface. A store failure must never mask the real SmtpException about to be thrown.

| Param | Type | Description |
|---|---|---|
| `$envelopeFrom` | `string` | — |
| `$envelopeRecipients` | `list<string>` | — |
| `$rawMessage` | `string` | — |
| `$error` | `string` | — |

</details>

<details>
<summary><code>restart()</code></summary>

#### `SmtpClient restart()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/SmtpClient.php#L312)

```php
private function restart(): void
```

Closes and reopens the connection with the same credentials used by the last open() call.

| Throws | When |
|---|---|
| [`SmtpException`](#smtpexception) | On any protocol or transport failure. |

</details>

<details>
<summary><code>connect()</code></summary>

#### `SmtpClient connect()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/SmtpClient.php#L329)

```php
private function connect(string $host, int $port, bool $implicitTls): void
```

Resolves the host via DnsResolverInterface and opens the socket.

| Param | Type | Description |
|---|---|---|
| `$host` | `string` | — |
| `$port` | `int` | — |
| `$implicitTls` | `bool` | — |

| Throws | When |
|---|---|
| [`SmtpException`](#smtpexception) | — |

</details>

<details>
<summary><code>enableCrypto()</code></summary>

#### `SmtpClient enableCrypto()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/SmtpClient.php#L390)

```php
private function enableCrypto(string $host): void
```

Upgrades the current connection to TLS after STARTTLS.

| Param | Type | Description |
|---|---|---|
| `$host` | `string` | Original hostname, for SNI (already set on the stream context at connect time). |

| Throws | When |
|---|---|
| [`SmtpException`](#smtpexception) | — |

</details>

<details>
<summary><code>readGreeting()</code></summary>

#### `SmtpClient readGreeting()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/SmtpClient.php#L405)

```php
private function readGreeting(): void
```

Reads and validates the server's initial 220 greeting.

| Throws | When |
|---|---|
| [`SmtpException`](#smtpexception) | — |

</details>

<details>
<summary><code>ehlo()</code></summary>

#### `SmtpClient ehlo()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/SmtpClient.php#L422)

```php
private function ehlo(string $host): array
```

Sends EHLO and returns the server's advertised capability lines.

| Param | Type | Description |
|---|---|---|
| `$host` | `string` | — |

**Returns:** `list<string>` — Capability lines, uppercase.

| Throws | When |
|---|---|
| [`SmtpException`](#smtpexception) | — |

</details>

<details>
<summary><code>authenticate()</code></summary>

#### `SmtpClient authenticate()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/SmtpClient.php#L441)

```php
private function authenticate(string $username, string $password, array $capabilities): void
```

Authenticates using AUTH PLAIN when the server advertises it, else AUTH LOGIN.

| Param | Type | Description |
|---|---|---|
| `$username` | `string` | — |
| `$password` | `string` | — |
| `$capabilities` | `list<string>` | EHLO capability lines. |

| Throws | When |
|---|---|
| [`SmtpException`](#smtpexception) | — |

</details>

<details>
<summary><code>sendData()</code></summary>

#### `SmtpClient sendData()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/SmtpClient.php#L476)

```php
private function sendData(string $rawMessage): void
```

Sends the DATA command and the message body with dot-stuffing.

| Param | Type | Description |
|---|---|---|
| `$rawMessage` | `string` | — |

| Throws | When |
|---|---|
| [`SmtpException`](#smtpexception) | — |

</details>

<details>
<summary><code>quit()</code></summary>

#### `SmtpClient quit()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/SmtpClient.php#L499)

```php
private function quit(): void
```

Sends QUIT and closes the connection. Best-effort — never throws.

</details>

<details>
<summary><code>command()</code></summary>

#### `SmtpClient command()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/SmtpClient.php#L531)

```php
private function command(string $command, array $expect): string
```

Sends one command and validates the response code. Rejects embedded CR/LF in the command — prevents SMTP command/header injection if a caller-supplied value (address, subject) ever contained a newline.

| Param | Type | Description |
|---|---|---|
| `$command` | `string` | — |
| `$expect` | `list<int>` | Acceptable response codes. |

**Returns:** `string` — The full (possibly multiline) response text.

| Throws | When |
|---|---|
| [`SmtpException`](#smtpexception) | — |

</details>

<details>
<summary><code>write()</code></summary>

#### `SmtpClient write()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/SmtpClient.php#L557)

```php
private function write(string $data): void
```

Writes raw bytes to the connection.

| Param | Type | Description |
|---|---|---|
| `$data` | `string` | — |

| Throws | When |
|---|---|
| [`SmtpException`](#smtpexception) | — |

</details>

<details>
<summary><code>readResponse()</code></summary>

#### `SmtpClient readResponse()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/SmtpClient.php#L579)

```php
private function readResponse(): array
```

Reads one (possibly multiline) SMTP response. Continuation lines are "CODE-text\r\n"; the final line is "CODE text\r\n" (4th byte is a space, or end of line). Accumulates until the terminator — never assumes one line is the whole response. Retries stream_select() on EINTR (a signal interrupting the call) instead of treating it as a read failure.

**Returns:** `array{code: int, text: string}`

| Throws | When |
|---|---|
| [`SmtpException`](#smtpexception) | — |

</details>

<details>
<summary><code>readLine()</code></summary>

#### `SmtpClient readLine()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/SmtpClient.php#L613)

```php
private function readLine( $connection): string
```

Reads a single line (up to MAX_LINE_LENGTH bytes), retrying on EINTR.

| Param | Type | Description |
|---|---|---|
| `$connection` | `resource` | — |

**Returns:** `string`

| Throws | When |
|---|---|
| [`SmtpException`](#smtpexception) | — |

</details>

<details>
<summary><code>mustConnection()</code></summary>

#### `SmtpClient mustConnection()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/SmtpClient.php#L656)

```php
private function mustConnection(): 
```

**Returns:** `resource`

| Throws | When |
|---|---|
| [`SmtpException`](#smtpexception) | — |

</details>

<details>
<summary><code>debug()</code></summary>

#### `SmtpClient debug()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/SmtpClient.php#L670)

```php
private function debug(string $line): void
```

| Param | Type | Description |
|---|---|---|
| `$line` | `string` | — |

</details>

## `SmtpDebugCapture`

*final class* — `rafalmasiarek\Mailer\SmtpDebugCapture` · [source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/SmtpDebugCapture.php#L24)

Captures the raw SMTP transcript and redacts AUTH credential exchange. Pass the callable from getCallback() to SmtpClient's debug hook. Expects SmtpClient's own transcript line format: "C: <command>" for client-sent lines, "S: <response>" for server lines. AUTH filtering strategy: - the "C: AUTH ..." line is replaced with a redacted marker. - all subsequent lines (challenge/response base64 exchange) are dropped. - capture resumes after the server's 235 (success) or 535 (failure) response. - the 235/535 line itself is kept so the auth outcome is visible.

**Depends on:** `ClockInterface`

| Property | Type | Visibility | Description |
|---|---|---|---|
| `$lines` | `list<string>` | private | — |
| `$inAuthExchange` | `bool` | private | Whether we are currently inside an AUTH credential exchange. |

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`__construct()`](#smtpdebugcapture-__construct) | public | — | — |
| [`getCallback()`](#smtpdebugcapture-getcallback) | public | `callable(string):` | Returns the callable to pass to SmtpClient's debug hook. |
| [`hasLines()`](#smtpdebugcapture-haslines) | public | `bool` | Returns true when at least one SMTP debug line was captured. |
| [`getLines()`](#smtpdebugcapture-getlines) | public | `list<string>` | Returns all captured lines. |
| [`writeToFile()`](#smtpdebugcapture-writetofile) | public | `void` | Writes the captured transcript to a file, creating parent directories as needed. |
| [`append()`](#smtpdebugcapture-append) | private | `void` | Prepends a timestamp and stores a line. |

<details>
<summary><code>__construct()</code></summary>

#### `SmtpDebugCapture __construct()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/SmtpDebugCapture.php#L35)

```php
public function __construct(ClockInterface $clock)
```

| Param | Type | Description |
|---|---|---|
| `$clock` | `ClockInterface` | Clock used for log line timestamps. |

</details>

<details>
<summary><code>getCallback()</code></summary>

#### `SmtpDebugCapture getCallback()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/SmtpDebugCapture.php#L44)

```php
public function getCallback(): callable
```

Returns the callable to pass to SmtpClient's debug hook.

**Returns:** `callable(string):` — void

</details>

<details>
<summary><code>hasLines()</code></summary>

#### `SmtpDebugCapture hasLines()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/SmtpDebugCapture.php#L75)

```php
public function hasLines(): bool
```

Returns true when at least one SMTP debug line was captured.

**Returns:** `bool`

</details>

<details>
<summary><code>getLines()</code></summary>

#### `SmtpDebugCapture getLines()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/SmtpDebugCapture.php#L85)

```php
public function getLines(): array
```

Returns all captured lines.

**Returns:** `list<string>`

</details>

<details>
<summary><code>writeToFile()</code></summary>

#### `SmtpDebugCapture writeToFile()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/SmtpDebugCapture.php#L96)

```php
public function writeToFile(string $path): void
```

Writes the captured transcript to a file, creating parent directories as needed.

| Param | Type | Description |
|---|---|---|
| `$path` | `string` | Absolute path to the target log file. |

</details>

<details>
<summary><code>append()</code></summary>

#### `SmtpDebugCapture append()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/SmtpDebugCapture.php#L111)

```php
private function append(string $line): void
```

Prepends a timestamp and stores a line.

| Param | Type | Description |
|---|---|---|
| `$line` | `string` | — |

</details>

## `SmtpErrorCode`

*final class* — `rafalmasiarek\Mailer\SmtpErrorCode` · [source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/SmtpErrorCode.php#L16)

Canonical SMTP error codes and classifier. All needle-matching logic lives here so every caller (logging, API error responses, …) derives the same structured reason code from a raw SMTP error string.

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`message()`](#smtperrorcode-message) | public static | `string` | Returns the human-readable message for an ERR_SMTP_* code. |
| [`classify()`](#smtperrorcode-classify) | public static | `string` | Classifies an SMTP error string into one of the ERR_SMTP_* constants. Checks for an RFC 3463 enhanced status code first — Postfix and most modern MTAs include one on nearly every rejection, and it is far more reliable than matching human-readable text that varies between server software. Falls back to free-text needles for connection-level failures that never reach the SMTP protocol layer, where no such code exists. |

<details>
<summary><code>message()</code></summary>

#### `SmtpErrorCode message()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/SmtpErrorCode.php#L81)

```php
public static function message(string $code): string
```

Returns the human-readable message for an ERR_SMTP_* code.

| Param | Type | Description |
|---|---|---|
| `$code` | `string` | One of the ERR_SMTP_* constant values. |

**Returns:** `string` — Human-readable error description.

</details>

<details>
<summary><code>classify()</code></summary>

#### `SmtpErrorCode classify()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/SmtpErrorCode.php#L101)

```php
public static function classify(string $errorInfo, string $exceptionMessage): string
```

Classifies an SMTP error string into one of the ERR_SMTP_* constants. Checks for an RFC 3463 enhanced status code first — Postfix and most modern MTAs include one on nearly every rejection, and it is far more reliable than matching human-readable text that varies between server software. Falls back to free-text needles for connection-level failures that never reach the SMTP protocol layer, where no such code exists.

| Param | Type | Description |
|---|---|---|
| `$errorInfo` | `string` | Raw SMTP server response / error text. |
| `$exceptionMessage` | `string` | Exception::getMessage() for the same failure. |

**Returns:** `string` — One of the ERR_SMTP_* constant values.

</details>

## `SmtpException`

*class* — `rafalmasiarek\Mailer\SmtpException` · [source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/SmtpException.php#L12)

Thrown when an SMTP operation fails.

extends `\RuntimeException`

## `SystemClock`

*final class* — `rafalmasiarek\Mailer\SystemClock` · [source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/SystemClock.php#L14)

Default PSR-20 clock backed by the system time.

implements `ClockInterface`

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`now()`](#systemclock-now) | public | `\DateTimeImmutable` | {@inheritDoc} |

<details>
<summary><code>now()</code></summary>

#### `SystemClock now()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/SystemClock.php#L19)

```php
public function now(): \DateTimeImmutable
```

{@inheritDoc}

**Returns:** `\DateTimeImmutable`

</details>

## `TlsOptions`

*final class* — `rafalmasiarek\Mailer\TlsOptions` · [source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/TlsOptions.php#L12)

TLS behavior for SmtpClient's connection (implicit TLS and STARTTLS alike).

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`__construct()`](#tlsoptions-__construct) | public | — | — |

<details>
<summary><code>__construct()</code></summary>

#### `TlsOptions __construct()`

[source](https://github.com/rafalmasiarek/php-mailer/blob/main/src/TlsOptions.php#L28)

```php
public function __construct(bool $verifyPeer, ?string $caFile, ?string $clientCertFile, ?string $clientKeyFile, ?string $clientKeyPassphrase)
```

| Param | Type | Description |
|---|---|---|
| `$verifyPeer` | `bool` | Verify the server's certificate chain and hostname against the system CA store. Set false only to trust any certificate unconditionally — prefer $caFile to pin a specific self-signed certificate or private CA instead. |
| `$caFile` | `string\|null` | PEM file containing the specific certificate (or CA) to trust, used instead of the system CA store. Verification still runs when $verifyPeer is true — this only changes what counts as trusted. |
| `$clientCertFile` | `string\|null` | PEM file with the client certificate, for servers requiring mutual TLS. |
| `$clientKeyFile` | `string\|null` | PEM file with the client private key; same file as $clientCertFile when both are stored together. |
| `$clientKeyPassphrase` | `string\|null` | Passphrase for $clientKeyFile, when encrypted. |

</details>

<!-- END_PHP_DOCS -->
