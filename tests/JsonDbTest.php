<?php

namespace DiamondDove\SimpleJson\Tests;

use DiamondDove\SimpleJson\SimpleJsonDB;
use Spatie\TemporaryDirectory\TemporaryDirectory;

class JsonDbTest extends TestCase
{
    public function testItReadsRecordsFromAFile(): void
    {
        $records = SimpleJsonDB::create($this->getPath('users.json'))->get();

        $this->assertCount(4, $records);
        $this->assertSame('User 1', $records->first()['name']);
    }

    public function testItReturnsAnEmptyCollectionForAnEmptyFile(): void
    {
        // SimpleJsonDB lowercases the filename, so use an already-lowercase name
        // to stay correct on case-sensitive filesystems.
        $temporaryDirectory = (new TemporaryDirectory(__DIR__ . '/temp'))->empty();
        $path = $temporaryDirectory->path('empty.json');
        file_put_contents($path, '');

        $records = SimpleJsonDB::create($path)->get();

        $this->assertTrue($records->isEmpty());

        $temporaryDirectory->delete();
    }
}
