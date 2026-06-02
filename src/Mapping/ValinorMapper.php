<?php

namespace DiamondDove\SimpleJson\Mapping;

use DiamondDove\SimpleJson\Exceptions\InvalidJsonException;
use DiamondDove\SimpleJson\Exceptions\JsonMappingException;
use DiamondDove\SimpleJson\Exceptions\MissingDependencyException;
use DiamondDove\SimpleJson\Json;

/**
 * Optional adapter wrapping cuyz/valinor. It is the escape hatch for advanced
 * type signatures -- {@code list<string>}, {@code int<0, 100>},
 * {@code non-empty-string}, shaped arrays, and the like -- that exceed the
 * constructor-by-name reach of {@see ObjectMapper}.
 *
 * The third-party class is only referenced inside the guarded method body, so
 * autoloading this adapter never fails when the optional package is absent. No
 * raw valinor exception type escapes: data that fails a valid signature becomes
 * a {@see JsonMappingException}, and an invalid signature becomes an
 * {@see \InvalidArgumentException}.
 */
final class ValinorMapper
{
    /**
     * Map a JSON string onto the valinor type $signature.
     *
     * @throws MissingDependencyException when cuyz/valinor is not installed
     * @throws InvalidJsonException when $json is not valid JSON
     * @throws JsonMappingException when the data does not satisfy $signature
     * @throws \InvalidArgumentException when $signature itself is not a valid type
     */
    public function map(string $json, string $signature): mixed
    {
        MissingDependencyException::ensure(
            class_exists(\CuyZ\Valinor\MapperBuilder::class),
            'cuyz/valinor',
            'advanced type mapping',
        );

        $data = Json::parse($json)->value();

        try {
            return (new \CuyZ\Valinor\MapperBuilder())->mapper()->map($signature, $data);
        } catch (\CuyZ\Valinor\Mapper\MappingError $e) {
            throw JsonMappingException::fromValinor($signature, $this->messagesFor($e), $e);
        } catch (\CuyZ\Valinor\Mapper\Exception\InvalidMappingTypeSignature $e) {
            // A developer error (bad signature), distinct from data failing a
            // valid signature -- surface it as an argument error, not a data one.
            throw new \InvalidArgumentException(
                "Invalid mapping type signature \"{$signature}\": {$e->getMessage()}",
                0,
                $e,
            );
        }
    }

    /**
     * @return list<string>
     */
    private function messagesFor(\CuyZ\Valinor\Mapper\MappingError $error): array
    {
        $messages = [];

        foreach ($error->messages() as $message) {
            $path = $message->path();
            $body = (string) $message;

            $messages[] = $path === '' || $path === '*root*'
                ? $body
                : "{$path}: {$body}";
        }

        return $messages;
    }
}
