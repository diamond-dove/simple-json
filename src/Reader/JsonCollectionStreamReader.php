<?php

namespace DiamondDove\SimpleJson\Reader;

class JsonCollectionStreamReader
{
    public const CHUNK_SIZE = 8192;
    protected const START_OF_COLLECTION = '[';
    protected const END_OF_COLLECTION = ']';
    protected const START_OF_ITEM = '{';
    protected const END_OF_ITEM = '}';
    protected const STRING_DELIMITER = '"';
    protected const ESCAPE = '\\';

    /** @var resource */
    private $resource;

    protected bool $asArray = false;

    protected string $buffer = '';

    protected int $nestingLevel = 0;

    /**
     * Whether we opened the resource ourselves and are therefore responsible
     * for closing it. Caller-supplied resources are left open.
     */
    private bool $shouldClose;

    /**
     * @param  resource|string  $source  An open stream resource or a path to a JSON file.
     */
    public function __construct($source, bool $asArray = false)
    {
        $this->asArray = $asArray;

        if (is_resource($source)) {
            $this->resource = $source;
            $this->shouldClose = false;

            return;
        }

        if (!file_exists($source)) {
            throw new \InvalidArgumentException('There is no file at given path');
        }

        $this->resource = fopen($source, 'rb');
        $this->shouldClose = true;
    }

    public static function fromString(string $json, bool $asArray = false): self
    {
        $resource = fopen('php://temp', 'r+b');
        fwrite($resource, $json);

        $reader = new self($resource, $asArray);
        // We created this in-memory stream, so we own it.
        $reader->shouldClose = true;

        return $reader;
    }

    public function close(): void
    {
        if ($this->shouldClose && is_resource($this->resource)) {
            fclose($this->resource);
        }
    }

    /**
     * @return \Generator<int, mixed>
     */
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

    /**
     * @return \Generator<int, mixed>
     */
    private function parseChunk(string $chunk): \Generator
    {
        // Continue from where we left off
        $this->buffer .= $chunk;

        $start = 0;
        $keepFrom = 0;
        $itemInProgress = false;

        // String state is local because every chunk is re-scanned from a clean
        // boundary (the root bracket or an item's opening brace), never from the
        // middle of a string — so it can safely start fresh on each call.
        $inString = false;
        $escaped = false;

        // We want to iterate over chars but since,
        // we can have multibyte strings we can't access them by position alone
        $split = mb_str_split($this->buffer);

        foreach ($split as $position => $char) {
            // Inside a string literal nothing is structural: braces, brackets and
            // even escaped quotes are just data. Track escapes so an escaped quote
            // (\") doesn't prematurely close the string.
            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === self::ESCAPE) {
                    $escaped = true;
                } elseif ($char === self::STRING_DELIMITER) {
                    $inString = false;
                }

                continue;
            }

            // Opening quote of a string value — suspend structural parsing.
            if ($char === self::STRING_DELIMITER) {
                $inString = true;
                continue;
            }

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
