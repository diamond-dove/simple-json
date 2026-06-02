<?php

namespace DiamondDove\SimpleJson\Tests\Fixtures;

use DiamondDove\SimpleJson\Mapping\Attributes\ListOf;

final readonly class UserData
{
    /**
     * @param  list<TagData>  $tags
     */
    public function __construct(
        public string $name,
        public int $age,
        public ?AddressData $address = null,
        public Status $status = Status::Active,
        #[ListOf(TagData::class)]
        public array $tags = [],
    ) {
    }
}
