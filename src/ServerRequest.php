<?php

declare(strict_types=1);

namespace Dirthara\Http;

use Psr\Http\Message\UriInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\ServerRequestInterface;
use Dirthara\Http\Exception\InvalidMessageException;
use Dirthara\Http\Exception\InvalidRequestException;

final class ServerRequest implements ServerRequestInterface
{
    use RequestTrait;

    /**
     * @var array<array-key, mixed>
     */
    private array $cookieParams = [];

    /**
     * @var array<array-key, mixed>
     */
    private array $queryParams = [];

    /**
     * @var array<array-key, mixed>
     */
    private array $uploadedFiles = [];

    /**
     * @var null|array<array-key, mixed>|object
     */
    private array|object|null $parsedBody = null;

    /**
     * @var array<array-key, mixed>
     */
    private array $attributes = [];

    /**
     * @param array<array-key, string|array<array-key, string>> $headers
     * @param array<array-key, mixed> $serverParams
     *
     * @throws InvalidMessageException
     * @throws InvalidRequestException
     */
    public function __construct(
        string $method,
        UriInterface $uri,
        StreamInterface $body,
        array $headers = [],
        string $protocolVersion = '1.1',
        private readonly array $serverParams = [],
    ) {
        $this->initializeRequest($method, $uri, $body, $headers, $protocolVersion);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    /**
     * @param array<array-key, mixed> $cookies
     */
    public function withCookieParams(array $cookies): self
    {
        return clone($this, [
            'cookieParams' => $cookies,
        ]);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * @param array<array-key, mixed> $query
     */
    public function withQueryParams(array $query): self
    {
        return clone($this, [
            'queryParams' => $query,
        ]);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    /**
     * @param array<array-key, mixed> $uploadedFiles
     *
     * @throws InvalidRequestException
     */
    public function withUploadedFiles(array $uploadedFiles): self
    {
        $this->validateUploadedFiles($uploadedFiles);

        return clone($this, [
            'uploadedFiles' => $uploadedFiles,
        ]);
    }

    /**
     * @return null|array<array-key, mixed>|object
     */
    public function getParsedBody(): array|object|null
    {
        return $this->parsedBody;
    }

    /**
     * @param null|array<array-key, mixed>|object $data
     *
     * @throws InvalidRequestException
     */
    public function withParsedBody($data): self
    {
        return clone($this, [
            'parsedBody' => $this->validateParsedBody($data),
        ]);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute(string $name, $default = null): mixed
    {
        return array_key_exists($name, $this->attributes) ? $this->attributes[$name] : $default;
    }

    public function withAttribute(string $name, $value): self
    {
        $attributes = $this->attributes;
        $attributes[$name] = $value;

        return clone($this, [
            'attributes' => $attributes,
        ]);
    }

    public function withoutAttribute(string $name): self
    {
        if (!array_key_exists($name, $this->attributes)) {
            return $this;
        }

        $attributes = $this->attributes;
        unset($attributes[$name]);

        return clone($this, [
            'attributes' => $attributes,
        ]);
    }

    /**
     * @return null|array<array-key, mixed>|object
     *
     * @throws InvalidRequestException
     */
    private function validateParsedBody(mixed $data): array|object|null
    {
        if ($data !== null && !is_array($data) && !is_object($data)) {
            throw InvalidRequestException::invalidParsedBody($data);
        }

        return $data;
    }

    /**
     * @param array<array-key, mixed> $uploadedFiles
     *
     * @throws InvalidRequestException
     */
    private function validateUploadedFiles(array $uploadedFiles, string $path = ''): void
    {
        // @mago-expect analysis:mixed-assignment -- each entry is checked right below
        foreach ($uploadedFiles as $key => $file) {
            $filePath = $path === '' ? (string) $key : $path . '[' . $key . ']';

            if (is_array($file)) {
                $this->validateUploadedFiles($file, $filePath);

                continue;
            }

            if (!$file instanceof UploadedFileInterface) {
                throw InvalidRequestException::invalidUploadedFile($filePath, $file);
            }
        }
    }
}
