<?php

namespace DiamondDove\SimpleJson\Validation;

/**
 * The supported validation rule tokens. The backing string is the token used
 * in a rule definition (e.g. 'min' in 'min:18'); case names avoid PHP reserved
 * words such as 'string', 'int', 'array' and 'bool'.
 */
enum Rule: string
{
    case Required = 'required';
    case Nullable = 'nullable';
    case Str = 'string';
    case Integer = 'int';
    case Numeric = 'numeric';
    case Boolean = 'bool';
    case Arr = 'array';
    case Email = 'email';
    case Min = 'min';
    case Max = 'max';
    case Between = 'between';
    case In = 'in';
    case Regex = 'regex';
}
