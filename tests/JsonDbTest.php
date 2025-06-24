<?php

namespace DiamondDove\SimpleJson\Tests;

use Spatie\TemporaryDirectory\TemporaryDirectory;

class JsonDbTest extends TestCase
{

    public function testInsert(): void
    {
        $temporaryDirectory = new TemporaryDirectory(__DIR__ . '/temp');
        $this->assertMatchesFileSnapshot($temporaryDirectory->path('test.json'));
    }
}
