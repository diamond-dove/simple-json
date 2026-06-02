<?php

namespace DiamondDove\SimpleJson;

use Illuminate\Support\LazyCollection;

interface ReaderInterface
{
    public function select(string $column): self;

    public function from(string $path): self;

    /**
     * @return LazyCollection<int, mixed>
     */
    public function get(): LazyCollection;
}
