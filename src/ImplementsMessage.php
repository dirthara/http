<?php

declare(strict_types=1);

namespace Dirthara\Http;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\MessageInterface;
use Dirthara\Http\Exception\InvalidMessageException;

use function preg_match;

/**
 * @internal
 *
 * @require-implements MessageInterface
 */
trait ImplementsMessage
{
    private const string PROTOCOL_VERSION_PATTERN = '/^\d+(?:\.\d+)?$/D';

    private readonly string $protocolVersion;

    private readonly Headers $headers;

    private readonly StreamInterface $body;

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
        return $this->headers->all();
    }

    public function hasHeader(string $name): bool
    {
        return $this->headers->has($name);
    }

    /**
     * @return list<string>
     */
    public function getHeader(string $name): array
    {
        return $this->headers->get($name);
    }

    public function getHeaderLine(string $name): string
    {
        return $this->headers->line($name);
    }

    /**
     * @param string|array<array-key, string> $value
     *
     * @throws InvalidMessageException
     */
    public function withHeader(string $name, $value): MessageInterface
    {
        return clone($this, [
            'headers' => $this->headers->with($name, $value),
        ]);
    }

    /**
     * @param string|array<array-key, string> $value
     *
     * @throws InvalidMessageException
     */
    public function withAddedHeader(string $name, $value): MessageInterface
    {
        return clone($this, [
            'headers' => $this->headers->withAdded($name, $value),
        ]);
    }

    public function withoutHeader(string $name): MessageInterface
    {
        if (!$this->headers->has($name)) {
            return $this;
        }

        return clone($this, [
            'headers' => $this->headers->without($name),
        ]);
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
    private function validateProtocolVersion(string $version): string
    {
        if (!preg_match(self::PROTOCOL_VERSION_PATTERN, $version)) {
            throw InvalidMessageException::invalidProtocolVersion($version);
        }

        return $version;
    }
}
