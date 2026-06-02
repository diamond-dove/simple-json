<?php

namespace DiamondDove\SimpleJson\Mapping\Attributes;

/**
 * Declares that a constructor parameter typed as `array` should be hydrated as a
 * list of the given DTO/builtin/enum type, mapping each element in turn.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class ListOf
{
    /**
     * @param  class-string|string  $type  the per-element type each list item is mapped to
     */
    public function __construct(
        public string $type,
    ) {
    }
}
