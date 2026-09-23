<?php

declare(strict_types=1);

namespace Dirthara\Http;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\ResponseInterface;
use Dirthara\Http\Exception\InvalidMessageException;
use Dirthara\Http\Exception\InvalidResponseException;

final class Response implements ResponseInterface
{
    use MessageTrait;

    private const string INVALID_REASON_PHRASE_PATTERN = '/[\x00-\x08\x0A-\x1F\x7F]/';

    private int $statusCode;

    private string $reasonPhrase;

    /**
     * @param array<array-key, string|array<array-key, string>> $headers
     *
     * @throws InvalidMessageException
     * @throws InvalidResponseException
     */
    public function __construct(
        int|StatusCode $statusCode,
        StreamInterface $body,
        array $headers = [],
        string $protocolVersion = '1.1',
        string $reasonPhrase = '',
    ) {
        $this->statusCode = $this->validateStatusCode($statusCode);
        $this->reasonPhrase = $this->resolveReasonPhrase($this->statusCode, $reasonPhrase);
        $this->body = $body;
        $this->protocolVersion = $this->validateProtocolVersion($protocolVersion);

        foreach ($headers as $name => $value) {
            $this->setHeader((string) $name, $value);
        }
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @throws InvalidResponseException
     */
    public function withStatus(int|StatusCode $code, string $reasonPhrase = ''): self
    {
        $code = $this->validateStatusCode($code);
        $reasonPhrase = $this->resolveReasonPhrase($code, $reasonPhrase);

        if ($code === $this->statusCode && $reasonPhrase === $this->reasonPhrase) {
            return $this;
        }

        return clone($this, [
            'statusCode' => $code,
            'reasonPhrase' => $reasonPhrase,
        ]);
    }

    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    /**
     * @throws InvalidResponseException
     */
    private function validateStatusCode(int|StatusCode $code): int
    {
        if ($code instanceof StatusCode) {
            return $code->value;
        }

        if ($code < 100 || $code > 599) {
            throw InvalidResponseException::invalidStatusCode($code);
        }

        return $code;
    }

    /**
     * @throws InvalidResponseException
     */
    private function resolveReasonPhrase(int $code, string $reasonPhrase): string
    {
        if ($reasonPhrase === '') {
            return StatusCode::tryFrom($code)?->reasonPhrase() ?? '';
        }

        if (preg_match(self::INVALID_REASON_PHRASE_PATTERN, $reasonPhrase)) {
            throw InvalidResponseException::invalidReasonPhrase($reasonPhrase);
        }

        return $reasonPhrase;
    }
}
