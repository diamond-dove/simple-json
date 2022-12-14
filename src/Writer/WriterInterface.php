<?php

namespace DiamondDove\SimpleJson\Writer;

interface WriterInterface {
    public function push(object|array $record): bool;
}
