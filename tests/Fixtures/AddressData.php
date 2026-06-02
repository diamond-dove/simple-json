<?php

namespace DiamondDove\SimpleJson\Tests\Fixtures;

final readonly class AddressData
{
    public function __construct(
        public string $city,
        public ?string $zip = null,
    ) {
    }
}
