<?php

namespace DiamondDove\SimpleJson;

use DiamondDove\SimpleJson\Traits\PathHandle;
use Illuminate\Support\LazyCollection;

class SimpleJsonReader
{
    use PathHandle;
    protected ReaderInterface $reader;
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

    public function getReader(): ReaderInterface
    {
        return $this->reader;
    }

    public function get(): LazyCollection
    {
        return LazyCollection::make(function () {
                $items = $this->reader
                    ->from($this->file)
                    ->get();
                foreach ($items as $item) {
                    yield $item;
                }
        });
    }
}
