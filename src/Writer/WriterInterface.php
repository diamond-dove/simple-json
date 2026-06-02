<?php

namespace DiamondDove\SimpleJson\Writer;

interface WriterInterface
{
    /**
     * @param  object|array<array-key, mixed>  $record
     */
    public function push(object|array $record): bool;
}
