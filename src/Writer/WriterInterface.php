<?php

namespace DiamondDove\SimpleJson\Writer;

interface WriterInterface {

    public function insert(string $file, array $records): bool;

    public function update(string $file, array $records): bool;

    public function delete(string $file): bool;

    public function where(array $where, string $merge);
}
