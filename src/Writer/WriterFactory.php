<?php

namespace DiamondDove\SimpleJson\Writer;

class WriterFactory
{
    public static function createFromFile(string $path): WriterInterface
    {
        return new JsonWriter($path);
    }
}
