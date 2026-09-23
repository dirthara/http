<?php

declare(strict_types=1);

namespace Dirthara\Http\Tests\Doubles;

use RuntimeException;

use function fopen;
use function in_array;
use function is_resource;
use function stream_get_wrappers;
use function stream_wrapper_register;
use function stream_wrapper_unregister;

/**
 * A stream wrapper whose writes fail without raising a PHP notice, which no
 * real stream does: a full device reports the failure through the error
 * handler as well.
 */
final class FailingStreamWrapper
{
    public const string SCHEME = 'dirthara-failing';

    /**
     * Set by PHP when the stream is opened with a context.
     *
     * @var resource|null
     */
    public $context;

    private int $position = 0;

    public static function register(): void
    {
        if (!in_array(self::SCHEME, stream_get_wrappers(), strict: true)) {
            stream_wrapper_register(self::SCHEME, self::class);
        }
    }

    public static function unregister(): void
    {
        if (in_array(self::SCHEME, stream_get_wrappers(), strict: true)) {
            stream_wrapper_unregister(self::SCHEME);
        }
    }

    /**
     * @return resource
     */
    public static function open(string $mode)
    {
        $handle = fopen(self::SCHEME . '://stream', mode: $mode);

        if (!is_resource($handle)) {
            throw new RuntimeException('Unable to open the failing stream.');
        }

        return $handle;
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        $this->position = 0;

        return true;
    }

    public function stream_read(int $count): string
    {
        $this->position += $count;

        return '';
    }

    public function stream_write(string $data): false
    {
        return false;
    }

    public function stream_eof(): bool
    {
        return true;
    }

    /**
     * @return array<array-key, int>
     */
    public function stream_stat(): array
    {
        return [];
    }

    public function stream_close(): void {}
}
