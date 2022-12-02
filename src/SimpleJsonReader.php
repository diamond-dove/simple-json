<?php

namespace DiamondDove\SimpleJson;

use DiamondDove\SimpleJson\Traits\PathHandle;
use Illuminate\Support\LazyCollection;

class SimpleJsonReader
{
    use PathHandle;
    protected SimpleJsonDB $reader;
    protected string $file;

    public static function create(string $path): self
    {
        return new static($path);
    }

    public function __construct(string $path)
    {
        $this->reader = ReaderFactory::createFromFile($path);
        $this->file = $this->getBaseName($path);
    }

    public  function getFile(): string
    {
        return $this->file;
    }

    public function get(): LazyCollection
    {
        return $this->reader->get();
    }
}
