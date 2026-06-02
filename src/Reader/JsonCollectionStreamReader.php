<?php

namespace DiamondDove\SimpleJson\Reader;

class JsonCollectionStreamReader
{
    public const CHUNK_SIZE = 8192;
    protected const START_OF_COLLECTION = '[';
    protected const END_OF_COLLECTION = ']';
    protected const START_OF_ITEM = '{';
    protected const END_OF_ITEM = '}';

    private $resource;

    protected bool $asArray = false;

    protected string $buffer = '';

    protected int $nestingLevel = 0;

    public function __construct(string $path, bool $asArray = false)
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException('There is no file at given path');
        }

        $this->asArray = $asArray;

        $this->resource = fopen($path, 'rb');
    }

    public function close(): void
    {
        if (is_resource($this->resource)) {
            fclose($this->resource);
        }
    }

    public function get(): \Generator
    {
        $this->buffer = '';
        $this->nestingLevel = 0;

        rewind($this->resource);

        while (!feof($this->resource)) {
            $chunk = fread($this->resource, JsonCollectionStreamReader::CHUNK_SIZE);

            yield from $this->parseChunk($chunk);
        }
    }

    private function parseChunk($chunk): \Generator
    {
        // Continue from where we left off
        $this->buffer .= $chunk;

        $start = 0;
        $keepFrom = 0;
        $itemInProgress = false;

        // We want to iterate over chars but since,
        // we can have multibyte strings we can't access them by position alone
        $split = mb_str_split($this->buffer);

        foreach ($split as $position => $char) {
            // Start of the collection
            if ($this->nestingLevel === 0 && $char === self::START_OF_COLLECTION) {
                $keepFrom = $position + 1;
                continue;
            }

            // We reached the end of the collection
            if ($this->nestingLevel === 0 && $char === self::END_OF_COLLECTION) {
                break;
            }

            // Maybe start of an item, but we need to check if we're not nested
            if ($char === self::START_OF_ITEM) {
                // Definitely start of the item
                if ($this->nestingLevel === 0) {
                    $start = $position;
                    $itemInProgress = true;
                }

                $this->nestingLevel++;
            } elseif ($char === self::END_OF_ITEM) {
                // Maybe end of the item?
                $this->nestingLevel--;

                // Definitely end of the item
                if ($this->nestingLevel === 0) {
                    yield json_decode(
                        mb_substr($this->buffer, $start, $position - $start + 1),
                        $this->asArray
                    );

                    $keepFrom = $position + 1;
                    $itemInProgress = false;
                }
            }
        }

        // We ran out of buffer in the middle of an item. Retain that item from its
        // opening brace and reset nesting so the next chunk re-parses it cleanly —
        // otherwise the brace would be counted twice and the depth would desync,
        // silently dropping every item after the first chunk boundary.
        if ($itemInProgress) {
            $keepFrom = $start;
            $this->nestingLevel = 0;
        }

        // Keep the unparsed part for later.
        $this->buffer = mb_substr($this->buffer, $keepFrom);
    }

    public function __destruct()
    {
        $this->close();
    }
}
