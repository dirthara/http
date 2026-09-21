<?php

declare(strict_types=1);

namespace Dirthara\Http\Exception;

trait HasExceptionContext
{
    /**
     * @var array<string, mixed>
     */
    protected array $context = [] {
        get {
            return $this->context;
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    public function addContext(array $context): static
    {
        $this->context = array_merge($this->context, $context);

        return $this;
    }
}
