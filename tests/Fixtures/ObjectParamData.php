<?php

namespace DiamondDove\SimpleJson\Tests\Fixtures;

final readonly class ObjectParamData
{
    public function __construct(
        public object $payload,
    ) {
    }
}
