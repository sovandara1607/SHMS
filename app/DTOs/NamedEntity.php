<?php

namespace App\DTOs;

/**
 * Tiny read-only stand-in for a cross-domain reference that a view only
 * ever displays by name (e.g. "the doctor on this assignment"). Exposes
 * both name() and fullName() so it drops into whichever accessor the
 * existing Blade call site already used, without touching the view.
 */
class NamedEntity implements \JsonSerializable
{
    public function __construct(public readonly string $label) {}

    public function name(): string
    {
        return $this->label;
    }

    public function fullName(): string
    {
        return $this->label;
    }

    public function jsonSerialize(): mixed
    {
        return $this->label;
    }
}
