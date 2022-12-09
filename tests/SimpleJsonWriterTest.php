<?php

namespace DiamondDove\SimpleJson\Tests;

use DiamondDove\SimpleJson\SimpleJsonWriter;
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
                        ->insert([
                            'first_name' => 'john',
                            'last_name'  => 'Doe',
                        ]);

        $this->assertMatchesFileSnapshot($this->pathToJson);
    }

    public function testCanInsertMultiRecords(): void
    {
        SimpleJsonWriter::create($this->pathToJson)
                        ->insert([
                            'name'  => 'Thomas',
                            'state' => 'Nigeria',
                            'age'   => 22,
                        ])
                        ->insert([
                            'name'  => 'Luis',
                            'state' => 'Nigeria',
                            'age'   => 32,
                        ]);

        $this->assertMatchesFileSnapshot($this->pathToJson);
    }

    public function testCanUpdateRecords(): void
    {
        SimpleJsonWriter::create($this->pathToJson)
                        ->insert([
                            'name'  => 'Thomas',
                            'state' => 'Nigeria',
                            'age'   => 22,
                        ])
                        ->insert([
                            'name'  => 'Luis',
                            'state' => 'Nigeria',
                            'age'   => 32,
                        ])
                        ->where(['name' => 'Luis'])
                        ->update(['name' => 'Juan', 'age' => 28]);

        $this->assertMatchesFileSnapshot($this->pathToJson);
    }

    public function testCanDeleteRecords(): void
    {
        SimpleJsonWriter::create($this->pathToJson)
                        ->insert([
                            'name'  => 'Thomas',
                            'state' => 'Nigeria',
                            'age'   => 22,
                        ])
                        ->insert([
                            'name'  => 'Luis',
                            'state' => 'Nigeria',
                            'age'   => 32,
                        ])
                        ->where(['name' => 'Luis'])
                        ->delete();

        $this->assertMatchesFileSnapshot($this->pathToJson);
    }

}
