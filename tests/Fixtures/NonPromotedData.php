<?php

namespace DiamondDove\SimpleJson\Tests\Fixtures;

/**
 * Constructor parameters are NOT promoted; the mapper must still fill them by
 * name and the class assigns them to its own properties.
 */
final readonly class NonPromotedData
{
    public string $name;

    public int $age;

    public function __construct(string $name, int $age)
    {
        $this->name = $name;
        $this->age = $age;
    }
}
