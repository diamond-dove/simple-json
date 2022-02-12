<?php

namespace DiamondDove\SimpleJson\Tests;

use PHPUnit\Framework\TestCase as PhpUnitTestCase;

abstract class TestCase extends PhpUnitTestCase
{
    public function getPath(string $name): string
    {
        return __DIR__ . "/stubs/{$name}";
    }

    public function getBaseName(string $path): string
    {
        return \strtolower(\pathinfo($path, PATHINFO_BASENAME));
    }
}
