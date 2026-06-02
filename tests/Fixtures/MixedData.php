<?php

namespace DiamondDove\SimpleJson\Tests\Fixtures;

final readonly class MixedData
{
    public function __construct(
        public mixed $value = 'default',
    ) {
    }
}
