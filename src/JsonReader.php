<?php

namespace DiamondDove\SimpleJson;

use Illuminate\Support\LazyCollection;

class JsonReader implements ReaderInterface
{
    protected  JsonDb $simpleJsonDB;
    protected string $path;

    public function __construct(JsonDb $simpleJsonDB)
    {
        $this->simpleJsonDB = $simpleJsonDB;
    }

    public function select(string $column): ReaderInterface
    {
        return $this->simpleJsonDB->select($column);
    }

    public function from(string $path): ReaderInterface
    {
        $this->path = $path;
        return $this;
    }

    /**
     * @return LazyCollection
     */
    public function get(): LazyCollection
    {
           return $this->simpleJsonDB
               ->from($this->path)
               ->get();
    }
}
