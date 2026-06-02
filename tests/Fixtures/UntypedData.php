<?php

namespace DiamondDove\SimpleJson\Tests\Fixtures;

final readonly class UntypedData
{
    public mixed $value;

    /** @param mixed $value */
    public function __construct($value = 'default')
    {
        $this->value = $value;
    }
}
