<?php

namespace DiamondDove\SimpleJson\Writer;

use DiamondDove\SimpleJson\Traits\PathHandle;
use Jajo\JSONDB;

class JsonWriter implements WriterInterface
{
    use PathHandle;

    protected JsonCollectionStreamWriter $writer;

    public function __construct(string  $path)
    {
        $this->writer = new JsonCollectionStreamWriter($path);
    }

    /**
     * @throws \Exception
     */
    public function push(object|array $record): bool
    {
        $this->writer->push($record);

        return true;
    }
}
