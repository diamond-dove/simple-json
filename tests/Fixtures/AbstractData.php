<?php

namespace DiamondDove\SimpleJson\Tests\Fixtures;

abstract class AbstractData
{
    public function __construct(
        public string $name,
    ) {
    }
}
