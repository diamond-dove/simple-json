<?php

namespace DiamondDove\SimpleJson\Tests\Fixtures;

final readonly class VariadicData
{
    /** @var list<int> */
    public array $items;

    public function __construct(int ...$items)
    {
        $this->items = $items;
    }
}
