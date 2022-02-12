<?php

namespace DiamondDove\SimpleJson;

use Jajo\JSONDB;

interface ReaderInterface
{
    public function select(string $column): self;

    public function from(string $path): self;

    public function get(): array;

    public function where( array $columns, $merge = JSONDB::OR ): self;

    public function andWhereRegx(string $name, string $regx): self;

    public function orWhereRegx(string $name, string $regx): self;

    public function orderBy(string $column, string $order = JSONDB::ASC): self;

    public function orderByDesc(string $column): self;

    public function orderByAsc(string $column): self;
}
