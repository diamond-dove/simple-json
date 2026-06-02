<?php

namespace DiamondDove\SimpleJson\Validation;

/**
 * Parses a single field's rule definition into a list of {@see ParsedRule}.
 *
 * A definition is either a pipe-delimited string ('required|min:18') or an
 * already-split list of tokens (['required', 'min:18']). Each token is split
 * once on ':' into a rule name and its raw parameter string; parameters are
 * comma-split, except for 'regex' whose remainder is kept verbatim.
 */
final class RuleParser
{
    /**
     * Rules whose presence requires at least one parameter.
     */
    private const REQUIRES_PARAMETERS = [
        Rule::Min,
        Rule::Max,
        Rule::In,
        Rule::Regex,
    ];

    /**
     * @param  string|list<string>  $definition
     * @return list<ParsedRule>
     */
    public function parse(string|array $definition): array
    {
        $tokens = is_string($definition)
            ? explode('|', $definition)
            : $definition;

        $parsed = [];

        foreach ($tokens as $token) {
            $token = trim($token);

            if ($token === '') {
                continue;
            }

            $parsed[] = $this->parseToken($token);
        }

        return $parsed;
    }

    private function parseToken(string $token): ParsedRule
    {
        [$name, $rawParameters] = array_pad(explode(':', $token, 2), 2, null);

        $rule = Rule::tryFrom($name);

        if ($rule === null) {
            throw new \InvalidArgumentException("Unknown validation rule: {$name}");
        }

        $parameters = $this->parseParameters($rule, $rawParameters);

        if ($rule === Rule::Between && count($parameters) !== 2) {
            throw new \InvalidArgumentException('The between rule requires exactly two parameters.');
        }

        if ($parameters === [] && in_array($rule, self::REQUIRES_PARAMETERS, true)) {
            throw new \InvalidArgumentException(
                "Validation rule \"{$rule->value}\" requires at least one parameter.",
            );
        }

        return new ParsedRule($rule, $parameters);
    }

    /**
     * @return list<string>
     */
    private function parseParameters(Rule $rule, ?string $rawParameters): array
    {
        if ($rawParameters === null || $rawParameters === '') {
            return [];
        }

        if ($rule === Rule::Regex) {
            return [$rawParameters];
        }

        return explode(',', $rawParameters);
    }
}
