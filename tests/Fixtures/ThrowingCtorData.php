<?php

namespace DiamondDove\SimpleJson\Tests\Fixtures;

final readonly class ThrowingCtorData
{
    public function __construct(
        public int $age,
    ) {
        if ($age < 0) {
            throw new \InvalidArgumentException('age must not be negative');
        }
    }
}
