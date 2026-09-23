<?php

declare(strict_types=1);

namespace Dirthara\Http;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\MessageInterface;
use Dirthara\Http\Exception\InvalidMessageException;

/**
 * @internal
 *
 * @require-implements MessageInterface
 */
trait MessageTrait
{
    private const string PROTOCOL_VERSION_PATTERN = '/^\d+(?:\.\d+)?$/D';

    private const string TOKEN_PATTERN = '/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/D';

    private const string INVALID_HEADER_VALUE_PATTERN = '/[\x00-\x08\x0A-\x1F\x7F]/';

    /**
     * @var array<array-key, list<string>>
     */
    private array $headers = [];

    /**
     * Lowercase header name => header name as first given.
     *
     * @var array<array-key, string>
     */
    private array $headerNames = [];

    private string $protocolVersion = '1.1';

    private StreamInterface $body;

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    /**
     * @throws InvalidMessageException
     */
    public function withProtocolVersion(string $version): MessageInterface
    {
        $version = $this->validateProtocolVersion($version);

        if ($version === $this->protocolVersion) {
            return $this;
        }

        return clone($this, [
            'protocolVersion' => $version,
        ]);
    }

    /**
     * @return array<array-key, list<string>>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader(string $name): bool
    {
        return array_key_exists(strtolower($name), $this->headerNames);
    }

    /**
     * @return list<string>
     */
    public function getHeader(string $name): array
    {
        $originalName = $this->headerNames[strtolower($name)] ?? null;

        return $originalName === null ? [] : $this->headers[$originalName];
    }

    public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    /**
     * @param string|array<array-key, string> $value
     *
     * @throws InvalidMessageException
     */
    public function withHeader(string $name, $value): MessageInterface
    {
        $clone = clone $this;
        $clone->setHeader($name, $value);

        return $clone;
    }

    /**
     * @param string|array<array-key, string> $value
     *
     * @throws InvalidMessageException
     */
    public function withAddedHeader(string $name, $value): MessageInterface
    {
        $clone = clone $this;
        $clone->addHeader($name, $value);

        return $clone;
    }

    public function withoutHeader(string $name): MessageInterface
    {
        if (!$this->hasHeader($name)) {
            return $this;
        }

        $clone = clone $this;
        $clone->removeHeader($name);

        return $clone;
    }

    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    public function withBody(StreamInterface $body): MessageInterface
    {
        if ($body === $this->body) {
            return $this;
        }

        return clone($this, [
            'body' => $body,
        ]);
    }

    /**
     * @throws InvalidMessageException
     */
    private function setHeader(string $name, mixed $value): void
    {
        $values = $this->validateHeader($name, $value);

        $this->removeHeader($name);

        $this->headerNames[strtolower($name)] = $name;
        $this->headers[$name] = $values;
    }

    /**
     * @param array<array-key, string|array<array-key, string>> $headers
     *
     * @throws InvalidMessageException
     */
    private function setHeaders(array $headers): void
    {
        // A list such as ['Accept: text/html'] holds header lines, not headers keyed by name.
        if ($headers !== [] && array_is_list($headers)) {
            throw InvalidMessageException::headersNotKeyedByName();
        }

        foreach ($headers as $name => $value) {
            // A numeric header name such as "123" arrives as an int key.
            $this->setHeader((string) $name, $value);
        }
    }

    /**
     * @throws InvalidMessageException
     */
    private function addHeader(string $name, mixed $value): void
    {
        $values = $this->validateHeader($name, $value);
        $normalized = strtolower($name);
        $originalName = $this->headerNames[$normalized] ?? $name;

        $this->headerNames[$normalized] = $originalName;
        $this->headers[$originalName] = [...($this->headers[$originalName] ?? []), ...$values];
    }

    private function removeHeader(string $name): void
    {
        $normalized = strtolower($name);
        $originalName = $this->headerNames[$normalized] ?? null;

        if ($originalName === null) {
            return;
        }

        unset($this->headers[$originalName], $this->headerNames[$normalized]);
    }

    /**
     * @throws InvalidMessageException
     */
    private function validateProtocolVersion(string $version): string
    {
        if (!preg_match(self::PROTOCOL_VERSION_PATTERN, $version)) {
            throw InvalidMessageException::invalidProtocolVersion($version);
        }

        return $version;
    }

    /**
     * @return list<string>
     *
     * @throws InvalidMessageException
     */
    private function validateHeader(string $name, mixed $value): array
    {
        if (!preg_match(self::TOKEN_PATTERN, $name)) {
            throw InvalidMessageException::invalidHeaderName($name);
        }

        if (is_string($value)) {
            $value = [$value];
        }

        if (!is_array($value)) {
            throw InvalidMessageException::invalidHeaderValueType($name, $value);
        }

        if ($value === []) {
            throw InvalidMessageException::emptyHeaderValue($name);
        }

        $values = [];

        // @mago-expect analysis:mixed-assignment -- each value is checked right below
        foreach ($value as $headerValue) {
            if (!is_string($headerValue)) {
                throw InvalidMessageException::invalidHeaderValueType($name, $headerValue);
            }

            if (preg_match(self::INVALID_HEADER_VALUE_PATTERN, $headerValue)) {
                throw InvalidMessageException::invalidHeaderValue($name);
            }

            // Surrounding whitespace is not part of a field value (RFC 9110, section 5.5).
            $values[] = trim($headerValue, characters: " \t");
        }

        return $values;
    }
}
