<?php

namespace DiamondDove\SimpleJson\Tests;

use DiamondDove\SimpleJson\JsonDb;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Spatie\Snapshots\MatchesSnapshots;

class JsonDbTest extends TestCase
{
    public readonly JsonDb $jsonDb;
    public readonly string $pathToJson;

	use MatchesSnapshots;

    public function setUp(): void
    {
		$temporaryDirectory = new TemporaryDirectory(__DIR__ . '/temp');
		$this->pathToJson = $temporaryDirectory->path('test.json');
        $this->jsonDb = new JsonDb($this->pathToJson);
    }

    public function testInsert(): void
    {
        $this->assertMatchesFileSnapshot($this->pathToJson);
    }
}
