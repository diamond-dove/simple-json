<?php

namespace DiamondDove\SimpleJson\Tests\Fixtures;

final readonly class RequiredData
{
    public function __construct(
        public string $required,
    ) {
    }
}
