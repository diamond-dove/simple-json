<?php

namespace DiamondDove\SimpleJson\Validation;

/**
 * An immutable pairing of a parsed {@see Rule} with its (already split) string
 * parameters, e.g. Rule::Between with ['1', '5'].
 */
final readonly class ParsedRule
{
    /**
     * @param  list<string>  $parameters
     */
    public function __construct(
        public Rule $rule,
        public array $parameters = [],
    ) {
    }
}
