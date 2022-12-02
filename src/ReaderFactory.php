<?php

namespace DiamondDove\SimpleJson;

class ReaderFactory
{
    public static function createFromFile(string $path): SimpleJsonDB
    {
        return new SimpleJsonDB($path);
    }
}
