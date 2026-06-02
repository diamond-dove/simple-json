<?php

namespace DiamondDove\SimpleJson\Tests;

use DiamondDove\SimpleJson\Reader\JsonCollectionStreamReader;
use DiamondDove\SimpleJson\SimpleJsonReader;
use DiamondDove\SimpleJson\SimpleJsonWriter;
use Spatie\TemporaryDirectory\TemporaryDirectory;

class SimpleJsonReaderTest extends TestCase
{
    protected SimpleJsonReader $jsonReader;

    public function setUp(): void
    {
        $this->jsonReader = new SimpleJsonReader($this->getPath('users.json'));
    }

    public function testGetUserShortFile(): void
    {
        $this->assertNotEmpty($this->jsonReader->get()->toArray());
    }

    public function testCanGetUserEmptyFile(): void
    {
        $this->assertEmpty((new SimpleJsonReader($this->getPath('emptyFile.json')))->get()->toArray());
    }

    public function testCanGetLargeFile(): void
    {
        $expectedText = "Aggressive Ponytail #freebandnames";
        $actual = (new SimpleJsonReader($this->getPath('twitter_example_0.json')))->get()->last();
        $this->assertEquals($expectedText, $actual['statuses'][0]['text']);
    }

    public function testCanGetLargeFileWhereIn(): void
    {
        $expectedText = 24012619984051000;
        $actual = (new SimpleJsonReader($this->getPath('twitter_example_0.json')))
            ->get()
            ->where('search_metadata.since_id', $expectedText)
            ->first();

        $this->assertEquals($expectedText, $actual['search_metadata']['since_id']);
    }

    public function testGetWhereName(): void
    {
        $expectedName = 'User 3';
        $user = $this->jsonReader->get()->where('name', '===', $expectedName)->first();
        $this->assertEquals($expectedName, $user['name']);
    }

    public function testGetFile(): void
    {
        $expectedFileName = $this->getBaseName($this->getPath('users.json'));

        $this->assertEquals($expectedFileName, $this->jsonReader->getFile());
    }

    public function testStreamedContentMatchesFileGetContents(): void
    {
        $path = $this->getPath('users.json');

        $expected = json_decode(file_get_contents($path), true);
        $actual = (new SimpleJsonReader($path))->get()->toArray();

        $this->assertEquals($expected, $actual);
    }

    public function testStreamingUsesLessMemoryThanFileGetContents(): void
    {
        $temporaryDirectory = (new TemporaryDirectory(__DIR__ . '/temp'))->empty();
        $path = $temporaryDirectory->path('large.json');

        $writer = SimpleJsonWriter::create($path);
        for ($i = 0; $i < 5000; $i++) {
            $writer->push([
                'id'    => $i,
                'name'  => "User {$i}",
                'email' => "user{$i}@example.com",
            ]);
        }
        // Force the writer to flush the closing bracket so the file is valid JSON.
        unset($writer);

        // Baseline: decode the whole file into memory at once.
        gc_collect_cycles();
        $before = memory_get_usage();
        $whole = json_decode(file_get_contents($path), true);
        $fileGetContentsUsage = memory_get_usage() - $before;
        $this->assertCount(5000, $whole);
        unset($whole);

        // Streaming: process one item at a time, never holding the full set.
        gc_collect_cycles();
        $before = memory_get_usage();
        $count = 0;
        (new SimpleJsonReader($path))->get()->each(function (array $item) use (&$count) {
            $count++;
        });
        $streamedUsage = memory_get_usage() - $before;

        $this->assertSame(5000, $count);
        $this->assertLessThan($fileGetContentsUsage, $streamedUsage);

        $temporaryDirectory->delete();
    }

    public function testHandlesStructuralCharactersInsideStringValues(): void
    {
        $records = [
            ['id' => 1, 'note' => 'has a } brace and { another, plus [ ] brackets'],
            ['id' => 2, 'note' => 'a "quoted" word and a path C:\\temp\\x'],
            ['id' => 3, 'note' => 'looks like json: {"fake":"object"}'],
            ['id' => 4, 'note' => 'normal'],
        ];

        $temporaryDirectory = (new TemporaryDirectory(__DIR__ . '/temp'))->empty();
        $path = $temporaryDirectory->path('tricky.json');
        file_put_contents($path, json_encode($records));

        $expected = json_decode(file_get_contents($path), true);
        $actual = (new SimpleJsonReader($path))->get()->toArray();

        $this->assertEquals($expected, $actual);
        $this->assertCount(4, $actual);

        $temporaryDirectory->delete();
    }

    public function testHandlesStringWithBracesSpanningAChunkBoundary(): void
    {
        // A single record whose string value is far larger than one read chunk
        // and is full of structural characters, forcing the parser to keep its
        // string state correct across many fread() boundaries.
        $noisy = str_repeat('}{][",\\ ', JsonCollectionStreamReader::CHUNK_SIZE);
        $records = [
            ['id' => 1, 'blob' => $noisy],
            ['id' => 2, 'blob' => 'after the big one'],
        ];

        $temporaryDirectory = (new TemporaryDirectory(__DIR__ . '/temp'))->empty();
        $path = $temporaryDirectory->path('chunky.json');
        file_put_contents($path, json_encode($records));

        $actual = (new SimpleJsonReader($path))->get()->toArray();

        $this->assertEquals(json_decode(file_get_contents($path), true), $actual);
        $this->assertSame($noisy, $actual[0]['blob']);

        $temporaryDirectory->delete();
    }
}
