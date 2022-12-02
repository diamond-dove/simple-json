<?php

namespace DiamondDove\SimpleJson;

use DiamondDove\SimpleJson\Traits\PathHandle;
use Illuminate\Support\LazyCollection;
use Exception;

class SimpleJsonDB implements ReaderInterface
{
    use PathHandle;
    protected JsonDb $jsonDB;
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
        $this->jsonDB = new JsonDb($dir);
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
     * @return LazyCollection
     * @throws Exception
     */
    public function get(): LazyCollection
    {
        try {
            return LazyCollection::make($this->jsonDB
                ->from($this->file)->get());
        } catch (Exception $e) {
            $message = $e->getMessage();
            if ($message !== self::INVALID_JSON_ERROR) {
                throw new Exception($message, $e->getCode());
            }
        }
        return LazyCollection::make([]);
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
