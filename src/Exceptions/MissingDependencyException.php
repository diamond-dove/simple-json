<?php

namespace DiamondDove\SimpleJson\Exceptions;

/**
 * Raised when an optional feature is used without its backing package installed.
 * The package keeps zero hard dependencies, so optional escape hatches (such as
 * full JSONPath queries) guard their entry point and fail with an actionable
 * install hint rather than a fatal "class not found" error.
 */
final class MissingDependencyException extends \RuntimeException implements JsonException
{
    public static function for(string $package, string $feature): self
    {
        return new self(
            "{$feature} requires the \"{$package}\" package. "
            . "Install it with: composer require {$package}",
        );
    }

    /**
     * Assert that an optional dependency is installed, throwing an actionable
     * exception when it is not. Acts as a testable seam: passing $installed
     * explicitly lets tests exercise the failure path without uninstalling.
     *
     * @throws MissingDependencyException when $installed is false
     */
    public static function ensure(bool $installed, string $package, string $feature): void
    {
        if ($installed === false) {
            throw self::for($package, $feature);
        }
    }
}
