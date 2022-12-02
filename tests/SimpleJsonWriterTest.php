<?php

namespace DiamondDove\SimpleJson\Tests;

use DiamondDove\SimpleJson\SimpleJsonWriter;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Spatie\Snapshots\MatchesSnapshots;

class SimpleJsonWriterTest extends TestCase
{
    use MatchesSnapshots;

    private TemporaryDirectory $temporaryDirectory;
    private string             $pathToJson;

    public function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = new TemporaryDirectory(__DIR__ . '/temp');
        $this->temporaryDirectory->delete();
        $this->pathToJson = $this->temporaryDirectory->path('test.json');
    }

    public function testCanInsertRecords()
    {
        SimpleJsonWriter::create($this->pathToJson)
                        ->insert([
                            'first_name' => 'john',
                            'last_name'  => 'Doe',
                        ]);

        $this->assertMatchesFileSnapshot($this->pathToJson);
    }

    public function testCanInsertMultiRecords()
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

    public function testCanUpdateRecords()
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

    public function testCanDeleteRecords()
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
