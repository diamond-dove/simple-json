<?php

namespace DiamondDove\SimpleJson\Tests\Fixtures;

final readonly class ScalarsData
{
    /**
     * @param  array<array-key, mixed>  $meta
     */
    public function __construct(
        public string $name,
        public int $count,
        public float $ratio,
        public bool $active,
        public array $meta,
    ) {
    }
}
