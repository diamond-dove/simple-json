<?php

namespace DiamondDove\SimpleJson\Writer;

use DiamondDove\SimpleJson\Traits\PathHandle;

class JsonWriter implements WriterInterface
{
    use PathHandle;

    protected JsonCollectionStreamWriter $writer;

    public function __construct(string  $path)
    {
        $this->writer = new JsonCollectionStreamWriter($path);
    }

    /**
     * @param  object|array<array-key, mixed>  $record
     */
    public function push(object|array $record): bool
    {
        try {
            $this->writer->push($record);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
