<?php
require_once __DIR__ . '/exceptions.php';

/*
 * The checks most services share, written once. Every method throws a ValidationException (400) with a message
 * naming the field, and does nothing when the value is fine. The input can come from anywhere (the form, Postman,
 * a script), so the server checks it even when the page already did.
 */
class Validator
{
    public static function required($data, $fields)
    {
        foreach ($fields as $field) {
            $value = $data[$field] ?? null;
            // 0 and false are answers ("0 kg", "not active"), so only null and blank text count as missing.
            if ($value === null || (is_string($value) && trim($value) === '')) {
                throw new ValidationException("Field '$field' is required.");
            }
        }
    }

    public static function length($value, $field, $min, $max)
    {
        $length = mb_strlen(trim((string) $value));
        if ($length < $min || $length > $max) {
            throw new ValidationException("Field '$field' must be between $min and $max characters long.");
        }
    }

    // filter_var accepts "5" from a form but not "4.5", "five" or "5abc".
    public static function integer($value, $field, $min = null, $max = null)
    {
        if (is_bool($value) || filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new ValidationException("Field '$field' must be a whole number.");
        }
        self::range((int) $value, $field, $min, $max);
    }

    // Quantities are DECIMAL columns. Only plain digits with an optional point: is_numeric() would also accept
    // "1e3", which MySQL reads as 1000.
    public static function decimal($value, $field, $min = null, $max = null)
    {
        if (is_bool($value) || !preg_match('/^-?\d+(\.\d+)?$/', (string) $value)) {
            throw new ValidationException("Field '$field' must be a number.");
        }
        self::range((float) $value, $field, $min, $max);
    }

    // Strict comparison: an ENUM column only accepts the exact text.
    public static function oneOf($value, $field, $allowed)
    {
        if (!in_array($value, $allowed, true)) {
            throw new ValidationException("Field '$field' must be one of: " . implode(', ', $allowed) . '.');
        }
    }

    public static function email($value, $field = 'email')
    {
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException("Field '$field' must be a valid email address.");
        }
    }

    // Formatting the parsed value back and comparing catches dates PHP would silently roll over (30 February).
    public static function datetime($value, $field)
    {
        $parsed = DateTime::createFromFormat('Y-m-d H:i:s', (string) $value);
        if (!$parsed || $parsed->format('Y-m-d H:i:s') !== $value) {
            throw new ValidationException("Field '$field' must be a date and time (YYYY-MM-DD HH:MM:SS).");
        }
    }

    public static function password($value, $field = 'password')
    {
        $value = (string) $value;
        if (strlen($value) < 8 || !preg_match('/[A-Za-z]/', $value) || !preg_match('/\d/', $value)) {
            throw new ValidationException("Field '$field' must have at least 8 characters, including a letter and a number.");
        }
    }

    private static function range($number, $field, $min, $max)
    {
        if ($min !== null && $number < $min) {
            throw new ValidationException("Field '$field' must be at least $min.");
        }
        if ($max !== null && $number > $max) {
            throw new ValidationException("Field '$field' must be at most $max.");
        }
    }
}
