<?php

namespace DiamondDove\SimpleJson\Tests\Fixtures;

final readonly class PrivateCtorData
{
    private function __construct(
        public string $name,
    ) {
    }
}
