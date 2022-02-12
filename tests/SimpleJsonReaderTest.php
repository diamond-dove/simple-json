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

    public function testGetUserShortFile()
    {
        $this->assertNotEmpty($this->jsonReader->get()->toArray());
    }

    public function testCanGetUserEmptyFile()
    {
        $this->assertEmpty((new SimpleJsonReader($this->getPath('emptyFile.json')))->get()->toArray());
    }

    public function testCanGetLargeFile()
    {
        $expectedText = "Aggressive Ponytail #freebandnames";
        $actual = (new SimpleJsonReader($this->getPath('twitter_example_0.json')))->get()->last();
        $this->assertEquals($expectedText, $actual['statuses'][0]['text']);
    }

    public function testGetWhereName()
    {
        $expectedName = 'User 3';
        $user = $this->jsonReader->get()->where('name', '===', $expectedName)->first();
        $this->assertNotEmpty($expectedName, $user['name']);
    }

    public function testGetFile()
    {
        $expectedFileName = $this->getBaseName($this->getPath('users.json'));

        $this->assertEquals($expectedFileName, $this->jsonReader->getFile());
    }
}
