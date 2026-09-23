<?php

declare(strict_types=1);

namespace Dirthara\Http;

use Dirthara\Http\Exception\InvalidMessageException;

use function trim;
use function implode;
use function is_array;
use function is_string;
use function preg_match;
use function strtolower;
use function array_is_list;
use function array_key_exists;

/**
 * The headers of a message, looked up without regard to case and kept under the name each was first given.
 *
 * @internal
 */
final readonly class Headers
{
    /**
     * An RFC 9110 token, which header names and request methods both are.
     */
    public const string TOKEN_PATTERN = '/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/D';

    private const string INVALID_VALUE_PATTERN = '/[\x00-\x08\x0A-\x1F\x7F]/';

    /**
     * @param array<array-key, list<string>> $values
     * @param array<array-key, string>       $names
     */
    private function __construct(
        private array $values,
        private array $names,
    ) {}

    /**
     * @param array<array-key, string|array<array-key, string>> $headers
     *
     * @throws InvalidMessageException
     */
    public static function fromArray(array $headers): self
    {
        if ($headers !== [] && array_is_list($headers)) {
            throw InvalidMessageException::headersNotKeyedByName();
        }

        $result = new self([], []);

        foreach ($headers as $name => $value) {
            $result = $result->with((string) $name, $value);
        }

        return $result;
    }

    /**
     * A numeric header name such as "123" comes back as an int key, because PHP turns every numeric string array key
     * into an int.
     *
     * @return array<array-key, list<string>>
     */
    public function all(): array
    {
        return $this->values;
    }

    public function has(string $name): bool
    {
        return array_key_exists(strtolower($name), $this->names);
    }

    /**
     * @return list<string>
     */
    public function get(string $name): array
    {
        $originalName = $this->names[strtolower($name)] ?? null;

        return $originalName === null ? [] : $this->values[$originalName];
    }

    public function line(string $name): string
    {
        return implode(', ', $this->get($name));
    }

    /**
     * @throws InvalidMessageException
     */
    public function with(string $name, mixed $value): self
    {
        $validated = self::validate($name, $value);
        $without = $this->without($name);

        $values = $without->values;
        $values[$name] = $validated;

        $names = $without->names;
        $names[strtolower($name)] = $name;

        return new self($values, $names);
    }

    /**
     * @throws InvalidMessageException
     */
    public function withAdded(string $name, mixed $value): self
    {
        $validated = self::validate($name, $value);
        $normalized = strtolower($name);
        $originalName = $this->names[$normalized] ?? $name;

        $values = $this->values;
        $values[$originalName] = [...($values[$originalName] ?? []), ...$validated];

        $names = $this->names;
        $names[$normalized] = $originalName;

        return new self($values, $names);
    }

    /**
     * Replaces a header under the name it already has, and moves it to the front.
     *
     * @throws InvalidMessageException
     */
    public function withFirst(string $name, string $value): self
    {
        $originalName = $this->names[strtolower($name)] ?? $name;
        $replaced = $this->with($originalName, $value);

        return new self([$originalName => $replaced->values[$originalName]] + $replaced->values, $replaced->names);
    }

    public function without(string $name): self
    {
        $normalized = strtolower($name);
        $originalName = $this->names[$normalized] ?? null;

        if ($originalName === null) {
            return $this;
        }

        $values = $this->values;
        $names = $this->names;

        unset($values[$originalName], $names[$normalized]);

        return new self($values, $names);
    }

    /**
     * @throws InvalidMessageException
     *
     * @return list<string>
     */
    private static function validate(string $name, mixed $value): array
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

            if (preg_match(self::INVALID_VALUE_PATTERN, $headerValue)) {
                throw InvalidMessageException::invalidHeaderValue($name);
            }

            // Surrounding whitespace is not part of a field value (RFC 9110, section 5.5).
            $values[] = trim($headerValue, characters: " \t");
        }

        return $values;
    }
}
