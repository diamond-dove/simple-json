<?php

namespace DiamondDove\SimpleJson;

use DiamondDove\SimpleJson\Exceptions\InvalidJsonException;
use DiamondDove\SimpleJson\Reader\JsonCollectionStreamReader;

class JsonDb
{
    public string $file;
    protected string $dir;

    protected bool $asArray = true;

    public function __construct($dir) {
        $this->dir = $dir;
    }

    public function toObject(): void
    {
        $this->asArray = false;
    }

    public function from($file): self
    {
        $this->file = sprintf( '%s/%s.json', $this->dir, str_replace('.json', '', $file)); // Adding .json extension is no longer necessary

        return $this;
    }

    public function get(): \Closure
    {
        return function () {
            foreach ((new JsonCollectionStreamReader($this->file, $this->asArray))->get() as $item) {
                yield $item;
            }
        };
    }
}
