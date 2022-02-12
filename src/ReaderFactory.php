<?php

namespace DiamondDove\SimpleJson;

class ReaderFactory
{
    public static function createFromFile(string $path): ReaderInterface
    {
        return new JsonReader(new SimpleJsonDB($path));
    }
}
