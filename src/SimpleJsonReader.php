<?php

namespace DiamondDove\SimpleJson;

use Closure;
use DiamondDove\SimpleJson\Reader\JsonCollectionStreamReader;
use DiamondDove\SimpleJson\Traits\PathHandle;
use Illuminate\Support\LazyCollection;

final class SimpleJsonReader
{
    use PathHandle;

    /** @var Closure(): LazyCollection<int, mixed> */
    protected Closure $source;

    protected string $file;

    public static function create(string $path): self
    {
        return new static($path);
    }

    public function __construct(string $path)
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException('There is no file at given path');
        }

        $this->file = $this->getBaseName($path);

        // A fresh reader per get() keeps the collection re-iterable and closes
        // the file handle as soon as iteration is done. LazyCollection requires a
        // generator *function*, not a bare generator, hence the inner closure.
        $this->source = fn (): LazyCollection => LazyCollection::make(
            static function () use ($path) {
                yield from (new JsonCollectionStreamReader($path, asArray: true))->get();
            }
        );
    }

    /**
     * Read a JSON collection held entirely in a string.
     */
    public static function createFromString(string $json): self
    {
        return static::fromStreamReader(
            JsonCollectionStreamReader::fromString($json, asArray: true),
            'string'
        );
    }

    /**
     * Read a JSON collection from an already-open stream resource. The caller
     * keeps ownership of the resource and is responsible for closing it.
     *
     * @param  resource  $resource
     */
    public static function createFromResource($resource, string $file = 'resource'): self
    {
        return static::fromStreamReader(
            new JsonCollectionStreamReader($resource, asArray: true),
            $file
        );
    }

    public function getFile(): string
    {
        return $this->file;
    }

    /**
     * @return LazyCollection<int, mixed>
     */
    public function get(): LazyCollection
    {
        return ($this->source)();
    }

    private static function fromStreamReader(JsonCollectionStreamReader $reader, string $file): self
    {
        $instance = (new \ReflectionClass(static::class))->newInstanceWithoutConstructor();
        $instance->file = $file;
        // Hold the reader in the closure so the underlying stream lives as long
        // as this SimpleJsonReader does; get() rewinds it, so it stays re-iterable.
        $instance->source = fn (): LazyCollection => LazyCollection::make(
            static function () use ($reader) {
                yield from $reader->get();
            }
        );

        return $instance;
    }
}
