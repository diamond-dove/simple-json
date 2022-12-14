<?php

namespace DiamondDove\SimpleJson\Writer;

class JsonCollectionStreamWriter
{
    /**
     * @var resource
     */
    private $resource;

    /**
     * Keep track of collection item we're inserting.
     */
    private int $key = 0;

    public function __construct(string $path)
    {
        // Make sure we have an empty file.
        file_put_contents($path, '');

        // We're just going to write to this file, that's why we use "w".
        // In order to avoid transformations by the OS we'll go binary with "b".
        $this->resource = fopen($path, 'wb');

        // Start the collection
        fwrite($this->resource, '[');
    }

    public static function create(string $path): self
    {
        return new static($path);
    }

    /**
     * Insert closing bracket and close the resource.
     */
    public function close(): void
    {
        // In case we attempt to close twice
        if (is_resource($this->resource)) {
            fwrite($this->resource, ']');

            fclose($this->resource);
        }
    }

    /**
     * Serialize the item and write it to the collection.
     *
     * @param  array|object  $item
     */
    public function push(object| array $item): void
    {
        // We don't need to separate from the previous item if there are none.
        if ($this->key !== 0) {
            fwrite($this->resource, ',');
        }

        fwrite($this->resource, json_encode($item));

        $this->key++;
    }

    /**
     * In case we have some loose ends, close the collection.
     */
    public function __destruct()
    {
        $this->close();
    }
}
