<?php

namespace DiamondDove\SimpleJson;

use DiamondDove\SimpleJson\Traits\PathHandle;
use DiamondDove\SimpleJson\Writer\WriterFactory;
use DiamondDove\SimpleJson\Writer\WriterInterface;

class SimpleJsonWriter
{
    use PathHandle;

    public readonly WriterInterface $writer;
    public readonly string $file;

    public static function create(string $path): self
    {
        return new static($path);
    }

    public function __construct(string $path)
    {
        $this->file = $this->getBaseName($path);
        $this->writer = WriterFactory::createFromFile($path);
    }

    public function push(object|array $record): self
    {
        $this->writer->push($record);
        return $this;
    }
}
