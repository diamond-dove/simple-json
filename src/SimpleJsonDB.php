<?php

namespace DiamondDove\SimpleJson;

use DiamondDove\SimpleJson\Traits\PathHandle;
use Jajo\JSONDB;
use Exception;

class SimpleJsonDB implements ReaderInterface
{
    use PathHandle;
    protected JSONDB $jsonDB;
    protected string $file;
    protected string $column;
    protected const INVALID_JSON_ERROR = 'json is invalid';

    public static function create(string $path): self
    {
        return new static($path);
    }

    public function __construct(string $path)
    {
        $dir = $this->getDirName($path);
        $this->jsonDB = new JSONDB($dir);
        $this->file = $this->getBaseName($path);
        $this->column = '*';
    }

    public function select(string $column): self
    {
        $this->column = $column;
        return $this;
    }

    public function from(string $path): self
    {
        $this->file = $path;
        return $this;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function get(): array
    {
        try {
            return $this->jsonDB
                ->select($this->column )
                ->from($this->file)->get();
        } catch (Exception $e) {
            $message = $e->getMessage();
            if ($message !== self::INVALID_JSON_ERROR) {
                throw new Exception($message, $e->getCode());
            }
        }
        return [];
    }

    public function size(): int
    {
        return $this->jsonDB->check_fp_size();
    }

    public function where( array $columns, $merge = JSONDB::OR ): self
    {
        $this->jsonDB->where($columns, $merge);
        return $this;
    }

    public function andWhereRegx(string $name, string $regx): self
    {
        $this->jsonDB->where([ $name => JSONDB::regex( $regx )], JSONDB::AND);
        return $this;
    }

    public function orWhereRegx(string $name, string $regx): self
    {
        $this->jsonDB->where([ $name => JSONDB::regex( $regx )], JSONDB::OR);
        return $this;
    }

    public function orderBy(string $column, string $order = JSONDB::ASC): self
    {
        $this->jsonDB->order_by($column, $order);
        return $this;
    }

    public function orderByDesc(string $column): self
    {
        $this->jsonDB->order_by($column, JSONDB::DESC);
        return $this;
    }

    public function orderByAsc(string $column): self
    {
        $this->jsonDB->order_by($column, JSONDB::ASC);
        return $this;
    }

    public function delete(): self
    {
        $this->jsonDB->delete();
        return $this;
    }

    public function update(array $columns): self
    {
        $this->jsonDB->update($columns);
        return $this;
    }

    /**
     * @throws Exception
     */
    public function insert(array $columns): bool
    {
        try {
            $this->jsonDB->insert($this->file, $columns);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        return true;
    }

    public function trigger(): void
    {
        $this->jsonDB->trigger();
    }

    public function toXml(string $toFile): bool
    {
        return $this->jsonDB->to_xml($this->file, $toFile);
    }

    public function toMysql(string $toFile, bool $createTable = true ): bool
    {
        return $this->jsonDB->to_mysql($this->file, $toFile, $createTable);
    }
}
