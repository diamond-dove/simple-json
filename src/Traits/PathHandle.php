<?php

namespace DiamondDove\SimpleJson\Traits;

trait PathHandle
{
    public function getBaseName(string $path): string
    {
        return $this->getPathInfo($path, PATHINFO_BASENAME);
    }

    public function getDirName(string $path): string
    {
        return $this->getPathInfo($path, PATHINFO_DIRNAME);
    }

    public function getPathInfo(string $path, $flags): string
    {
        return \strtolower(\pathinfo($path, $flags));
    }
}
