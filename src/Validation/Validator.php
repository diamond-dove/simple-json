<?php

namespace DiamondDove\SimpleJson\Validation;

use DiamondDove\SimpleJson\JsonAccessor;

/**
 * Runs a set of field rules against a decoded JSON accessor, accumulating
 * human-readable error messages per field and collecting the validated subset.
 *
 * Type predicates are STRICT (no coercion). Rule order per field follows the
 * required/nullable short-circuit logic before evaluating type/format rules.
 */
final class Validator
{
    /**
     * @var array<string, list<ParsedRule>>
     */
    private array $rules;

    /**
     * @param  array<string, string|list<string>>  $rules
     */
    public function __construct(array $rules)
    {
        $parser = new RuleParser();

        $parsed = [];

        foreach ($rules as $field => $definition) {
            $parsed[$field] = $parser->parse($definition);
        }

        $this->rules = $parsed;
    }

    public function validate(JsonAccessor $root): ValidationResult
    {
        /** @var array<string, list<string>> $errors */
        $errors = [];
        /** @var array<string, mixed> $validated */
        $validated = [];

        foreach ($this->rules as $field => $parsedRules) {
            $node = $root->path($field);
            $present = $node->isPresent();
            $value = $present ? $node->value() : null;

            if ($present) {
                $validated[$field] = $value;
            }

            $fieldErrors = $this->validateField($field, $parsedRules, $present, $value);

            if ($fieldErrors !== []) {
                $errors[$field] = $fieldErrors;
            }
        }

        return new ValidationResult($errors, $validated);
    }

    /**
     * @param  list<ParsedRule>  $parsedRules
     * @return list<string>
     */
    private function validateField(string $field, array $parsedRules, bool $present, mixed $value): array
    {
        $hasRequired = $this->hasRule($parsedRules, Rule::Required);
        $hasNullable = $this->hasRule($parsedRules, Rule::Nullable);

        if ($hasRequired && $this->isEmpty($present, $value)) {
            return [$this->message($field, Rule::Required)];
        }

        if ($hasNullable && $value === null) {
            return [];
        }

        if (!$present && !$hasRequired) {
            return [];
        }

        $errors = [];

        foreach ($parsedRules as $parsedRule) {
            if ($parsedRule->rule === Rule::Required || $parsedRule->rule === Rule::Nullable) {
                continue;
            }

            if (!$this->satisfies($parsedRule, $value)) {
                $errors[] = $this->message($field, $parsedRule->rule, $parsedRule->parameters, $value);
            }
        }

        return $errors;
    }

    /**
     * @param  list<ParsedRule>  $parsedRules
     */
    private function hasRule(array $parsedRules, Rule $rule): bool
    {
        foreach ($parsedRules as $parsedRule) {
            if ($parsedRule->rule === $rule) {
                return true;
            }
        }

        return false;
    }

    private function isEmpty(bool $present, mixed $value): bool
    {
        return !$present || $value === null || $value === '' || $value === [];
    }

    private function satisfies(ParsedRule $parsedRule, mixed $value): bool
    {
        return match ($parsedRule->rule) {
            Rule::Str => is_string($value),
            Rule::Integer => is_int($value),
            Rule::Numeric => is_int($value) || is_float($value),
            Rule::Boolean => is_bool($value),
            Rule::Arr => is_array($value),
            Rule::Email => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            Rule::Min => $this->satisfiesMin($value, $parsedRule->parameters),
            Rule::Max => $this->satisfiesMax($value, $parsedRule->parameters),
            Rule::Between => $this->satisfiesBetween($value, $parsedRule->parameters),
            Rule::In => is_scalar($value) && in_array((string) $value, $parsedRule->parameters, true),
            Rule::Regex => is_string($value) && preg_match($parsedRule->parameters[0], $value) === 1,
            Rule::Required, Rule::Nullable => true,
        };
    }

    /**
     * @param  list<string>  $parameters
     */
    private function satisfiesMin(mixed $value, array $parameters): bool
    {
        $size = $this->size($value);

        return $size !== null && $size >= (float) $parameters[0];
    }

    /**
     * @param  list<string>  $parameters
     */
    private function satisfiesMax(mixed $value, array $parameters): bool
    {
        $size = $this->size($value);

        return $size !== null && $size <= (float) $parameters[0];
    }

    /**
     * @param  list<string>  $parameters
     */
    private function satisfiesBetween(mixed $value, array $parameters): bool
    {
        $size = $this->size($value);

        return $size !== null
            && $size >= (float) $parameters[0]
            && $size <= (float) $parameters[1];
    }

    /**
     * The size used by min/max/between, derived from the runtime type: the
     * number itself for int|float, mb_strlen for strings, count for arrays.
     * Returns null for any other type (which fails the bound).
     */
    private function size(mixed $value): int|float|null
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value)) {
            return mb_strlen($value);
        }

        if (is_array($value)) {
            return count($value);
        }

        return null;
    }

    /**
     * @param  list<string>  $parameters
     */
    private function message(string $field, Rule $rule, array $parameters = [], mixed $value = null): string
    {
        return match ($rule) {
            Rule::Required => "The {$field} field is required.",
            Rule::Str => "The {$field} must be of type string.",
            Rule::Integer => "The {$field} must be a valid integer.",
            Rule::Numeric => "The {$field} must be numeric.",
            Rule::Boolean => "The {$field} must be of type boolean.",
            Rule::Arr => "The {$field} must be of type array.",
            Rule::Email => "The {$field} must be a valid email address.",
            Rule::Min => $this->sizeMessage($field, 'at least', $parameters[0], $value),
            Rule::Max => $this->sizeMessage($field, 'at most', $parameters[0], $value),
            Rule::Between => "The {$field} must be between {$parameters[0]} and {$parameters[1]}.",
            Rule::In => "The {$field} is invalid.",
            Rule::Regex => "The {$field} format is invalid.",
            Rule::Nullable => throw new \LogicException('Nullable does not produce a validation message.'),
        };
    }

    private function sizeMessage(string $field, string $qualifier, string $bound, mixed $value): string
    {
        if (is_string($value)) {
            return "The {$field} must be {$qualifier} {$bound} characters.";
        }

        if (is_array($value)) {
            return "The {$field} must be {$qualifier} {$bound} items.";
        }

        return "The {$field} must be {$qualifier} {$bound}.";
    }
}
