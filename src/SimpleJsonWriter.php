<?php

namespace DiamondDove\SimpleJson;

use DiamondDove\SimpleJson\Traits\PathHandle;
use DiamondDove\SimpleJson\Writer\WriterFactory;
use DiamondDove\SimpleJson\Writer\WriterInterface;
use Jajo\JSONDB;
use phpDocumentor\Reflection\Utils;

class SimpleJsonWriter
{
    use PathHandle;

    protected WriterInterface $writer;
    protected string $file;

    public static function create(string $path): self
    {
        return new static($path);
    }

    public function __construct(string $path)
    {
        $this->file = $this->getBaseName($path);
        $this->writer = WriterFactory::createFromFile($path);
    }

    public function insert(array $records): self
    {
        $this->writer->insert($this->file, $records);
        return $this;
    }

    public function where(array $where, string $merge = JSONDB::OR): self
    {
        $this->writer->where($where, $merge);
        return $this;
    }

    public  function update(array $record): self
    {
        $this->writer->update($this->file, $record);
        return $this;
    }

    public function delete(): self
    {
        $this->writer->delete($this->file);
        return $this;
    }

    public function getWriter(): WriterInterface
    {
        return $this->writer;
    }
}
