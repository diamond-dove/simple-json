<?php

namespace DiamondDove\SimpleJson;

use Jajo\JSONDB;
use PHPUnit\Exception;

class JsonReader implements ReaderInterface
{
    protected  ReaderInterface $simpleJsonDB;

    public function __construct(ReaderInterface $simpleJsonDB)
    {
        $this->simpleJsonDB = $simpleJsonDB;
    }

    public function select(string $column): ReaderInterface
    {
        return $this->simpleJsonDB->select($column);
    }

    public function from(string $path): ReaderInterface
    {
        return $this->simpleJsonDB->from($path);
    }

    /**
     * @return array
     */
    public function get(): array
    {
           return $this->simpleJsonDB->get();
    }

    public function where(array $columns, $merge = JSONDB::OR): ReaderInterface
    {
        return $this->simpleJsonDB->where($columns, $merge);
    }

    public function andWhereRegx(string $name, string $regx): ReaderInterface
    {
        return $this->simpleJsonDB->andWhereRegx($name, $regx);
    }

    public function orWhereRegx(string $name, string $regx): ReaderInterface
    {
       return $this->simpleJsonDB->orWhereRegx($name, $regx);
    }

    public function orderBy(string $column, string $order = JSONDB::ASC): ReaderInterface
    {
        return $this->simpleJsonDB->orderBy($column, $order);
    }

    public function orderByDesc(string $column): ReaderInterface
    {
        return $this->simpleJsonDB->orderByDesc($column);
    }

    public function orderByAsc(string $column): ReaderInterface
    {
        return $this->simpleJsonDB->orderByAsc($column);
    }
}
