<?php

namespace DiamondDove\SimpleJson\Tests;

use DiamondDove\SimpleJson\SimpleJsonDB;

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
        $records = SimpleJsonDB::create($this->getPath('emptyFile.json'))->get();

        $this->assertTrue($records->isEmpty());
    }
}
