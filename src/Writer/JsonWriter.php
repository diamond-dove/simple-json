<?php

namespace DiamondDove\SimpleJson\Writer;

use DiamondDove\SimpleJson\Traits\PathHandle;
use Jajo\JSONDB;

class JsonWriter implements WriterInterface
{
    use PathHandle;

    protected JSONDB $writer;
    protected array $where = [];
    protected string $merge = JSONDB::OR;

    public function __construct(string  $path)
    {
        $this->writer = new JSONDB($this->getDirName($path));
    }

    /**
     * @throws \Exception
     */
    public function insert(string $file, array $records): bool
    {
        $this->writer->insert($file, $records);
        return true;
    }

    public function update(string $file, array $records): bool
    {
        $this->writer
            ->from($file)
            ->update($records)
            ->where($this->where, $this->merge)
            ->trigger();
        return true;
    }

    public function delete(string $file): bool
    {
       $this->writer
           ->from($file)
            ->where($this->where, $this->merge)
            ->delete()
            ->trigger();
       return true;
    }

    public function where(array $where, string $merge)
    {
        $this->where = $where;
        $this->merge = $merge;
    }
}
