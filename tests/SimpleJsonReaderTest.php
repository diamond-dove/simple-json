<?php

namespace DiamondDove\SimpleJson\Tests;

use DiamondDove\SimpleJson\SimpleJsonReader;

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
}
