<?php

declare(strict_types=1);

namespace Dirthara\Http;

use function stream_get_meta_data;

/**
 * @internal
 */
final readonly class StreamMetadata
{
    /**
     * @param array<string, mixed> $metadata
     */
    private function __construct(
        private array $metadata,
        public StreamMode $mode,
        public bool $seekable,
    ) {}

    /**
     * @param resource $resource
     */
    public static function fromResource($resource): self
    {
        $metadata = stream_get_meta_data($resource);

        return new self($metadata, StreamMode::fromMetadata($metadata['mode']), $metadata['seekable']);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->metadata;
    }

    public function get(string $key): mixed
    {
        return $this->metadata[$key] ?? null;
    }
}
