<?php

namespace DiamondDove\SimpleJson\JsonPath;

use DiamondDove\SimpleJson\Exceptions\JsonQueryException;
use DiamondDove\SimpleJson\Exceptions\MissingDependencyException;
use DiamondDove\SimpleJson\Json;

/**
 * Optional adapter wrapping softcreatr/jsonpath (namespaced Flow\JSONPath for
 * backwards compatibility). It is the escape hatch for full JSONPath syntax
 * — recursive descent, wildcards, slices, and filters — that exceeds the
 * dot-notation reach of {@see \DiamondDove\SimpleJson\JsonAccessor}.
 *
 * The third-party class is only referenced inside guarded method bodies, so
 * autoloading this adapter never fails when the optional package is absent.
 */
final class JsonPath
{
    /**
     * Evaluate a JSONPath expression against a JSON string and return the list
     * of matched values. An expression that matches nothing yields an empty
     * array; it never returns null or throws.
     *
     * @return array<array-key, mixed>
     *
     * @throws \DiamondDove\SimpleJson\Exceptions\MissingDependencyException when softcreatr/jsonpath is not installed
     * @throws \DiamondDove\SimpleJson\Exceptions\InvalidJsonException when $json is not valid JSON
     * @throws JsonQueryException when $jsonPath is not a valid JSONPath expression
     */
    public function query(string $json, string $jsonPath): array
    {
        MissingDependencyException::ensure(
            class_exists(\Flow\JSONPath\JSONPath::class),
            'softcreatr/jsonpath',
            'JSONPath queries',
        );

        $data = Json::parse($json)->value();

        try {
            $result = (new \Flow\JSONPath\JSONPath($data))->find($jsonPath)->getData();
        } catch (\Flow\JSONPath\JSONPathException $e) {
            throw JsonQueryException::invalidExpression($jsonPath, $e);
        }

        return $result;
    }
}
