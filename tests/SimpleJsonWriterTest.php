<?php

namespace DiamondDove\SimpleJson\Tests;

use DiamondDove\SimpleJson\SimpleJsonWriter;
use DiamondDove\SimpleJson\Writer\JsonCollectionStreamWriter;
use DiamondDove\SimpleJson\Writer\JsonWriter;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Spatie\Snapshots\MatchesSnapshots;

class SimpleJsonWriterTest extends TestCase
{
    use MatchesSnapshots;

    private string $pathToJson;

    public function setUp(): void
    {
        parent::setUp();

        $temporaryDirectory = new TemporaryDirectory(__DIR__ . '/temp');
        $temporaryDirectory->delete();
        $this->pathToJson = $temporaryDirectory->path('test.json');
    }

    public function testCanInsertRecords(): void
    {
        SimpleJsonWriter::create($this->pathToJson)
                        ->push([
                            'first_name' => 'john',
                            'last_name'  => 'Doe',
                        ]);

        $this->assertMatchesFileSnapshot($this->pathToJson);
    }

    public function testCanInsertMultiRecords(): void
    {
        SimpleJsonWriter::create($this->pathToJson)
                        ->push([
                            'name'  => 'Thomas',
                            'state' => 'Nigeria',
                            'age'   => 22,
                        ])
                        ->push([
                            'name'  => 'Luis',
                            'state' => 'Nigeria',
                            'age'   => 32,
                        ]);

        $this->assertMatchesFileSnapshot($this->pathToJson);
    }

    public function testPushReturnsFalseWhenTheUnderlyingWriteFails(): void
    {
        $writer = new JsonWriter($this->pathToJson);

        // Simulate a write that blows up mid-insert (e.g. a full disk).
        $failingStreamWriter = new class ($this->pathToJson) extends JsonCollectionStreamWriter {
            public function push(object|array $item): void
            {
                throw new \RuntimeException('write failed');
            }
        };

        $property = new \ReflectionProperty(JsonWriter::class, 'writer');
        $property->setValue($writer, $failingStreamWriter);

        $this->assertFalse($writer->push(['first_name' => 'john']));
    }
}
