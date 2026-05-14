<?php

class Request
{
    private array  $data    = [];
    private array  $query   = [];
    private array  $params  = [];
    private string $method;
    private array  $headers = [];

    public function __construct()
    {
        $this->method  = strtoupper($_SERVER['REQUEST_METHOD']);
        $this->query   = $this->clean($_GET);
        $this->headers = $this->parseHeaders();
        $this->data    = $this->parseBody();
    }

    // ── Route params (set by Router) ──────────────────────────────────────────
    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    // ── Accessors ─────────────────────────────────────────────────────────────
    public function method(): string
    {
        return $this->method;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->data;
    }

    public function header(string $key, mixed $default = null): mixed
    {
        return $this->headers[strtolower($key)] ?? $default;
    }

    // ── Validation ────────────────────────────────────────────────────────────
    /**
     * Validate body data.
     *
     * Scalar rules (pipe-separated):
     *   required                – must be present and not empty
     *   string                  – must be a string
     *   numeric                 – must be numeric
     *   integer                 – must be a whole number
     *   float                   – must be a float / decimal
     *   boolean                 – must be true/false/1/0
     *   email                   – valid email format
     *   min:{n}                 – string length / numeric value >= n
     *   max:{n}                 – string length / numeric value <= n
     *   in:{a},{b},...          – value must be one of listed options
     *   not_in:{a},{b},...      – value must NOT be one of listed options
     *   date                    – valid date  (Y-m-d)
     *   datetime                – valid datetime (Y-m-d H:i:s)
     *   time                    – valid time (H:i or H:i:s)
     *   before:{date}           – date must be before given date
     *   after:{date}            – date must be after given date
     *   regex:{pattern}         – value must match regex pattern
     *   url                     – must be a valid URL
     *   alpha                   – letters only
     *   alpha_num               – letters and numbers only
     *   alpha_dash              – letters, numbers, dashes, underscores
     *
     * Array rules:
     *   array                   – must be an array
     *   array|min:{n}           – array must have at least n items
     *   array|max:{n}           – array must have at most n items
     *
     * Dot notation for nested / wildcard:
     *   'data'        => 'required|array'
     *   'data.name'   => 'required|string'       – specific key in array
     *   'data.*.id'   => 'required|integer'      – every item in array must have id
     *   'data.*.email'=> 'required|email'        – every item must have valid email
     *
     * Throws ValidationException on failure.
     * Returns merged data (all input + validated fields) on success.
     */
    public function validate(array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleString) {
            $fieldRules = array_filter(
                explode('|', $ruleString),
                fn($r) => trim($r) !== ''
            );

            // ── Wildcard rule  e.g. data.*.id ────────────────────────────────
            if (str_contains($field, '.*.')) {
                $this->validateWildcard($field, $fieldRules, $errors);
                continue;
            }

            // ── Dot notation  e.g. data.name ─────────────────────────────────
            if (str_contains($field, '.')) {
                $value = $this->getDotValue($field, $this->data);
                $this->applyRules($field, $value, $fieldRules, $errors);
                continue;
            }

            // ── Standard field ────────────────────────────────────────────────
            $value = $this->data[$field] ?? null;
            $this->applyRules($field, $value, $fieldRules, $errors);
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        $validated = [];
        foreach (array_keys($rules) as $field) {
            if (!str_contains($field, '*') && !str_contains($field, '.')) {
                if (isset($this->data[$field])) {
                    $validated[$field] = $this->data[$field];
                }
            }
        }

        return array_merge($this->data, $validated);
    }

    // ── Rule engine ───────────────────────────────────────────────────────────

    private function applyRules(string $field, mixed $value, array $rules, array &$errors): void
    {
        $label    = str_replace(['_', '.', '*'], [' ', ' ', ''], $field);
        $isArray  = is_array($value);
        $required = in_array('required', $rules);

        // ── If field is not required and not present in payload → skip entirely ───
        if (!$required && ($value === null || $value === '')) {
            return;
        }

        foreach ($rules as $rule) {
            [$ruleName, $ruleParam] = array_pad(explode(':', $rule, 2), 2, null);
            $ruleName = trim($ruleName);

            switch ($ruleName) {

                // ── Presence ──────────────────────────────────────────────────
                case 'required':
                    if ($value === null || $value === '' || ($isArray && empty($value))) {
                        $errors[$field][] = "{$label} is required.";
                    }
                    break;

                // ── Type checks ───────────────────────────────────────────────
                case 'string':
                    if (!is_string($value) && !is_numeric($value)) {
                        $errors[$field][] = "{$label} must be a string.";
                    }
                    break;

                case 'numeric':
                    if (!is_numeric($value)) {
                        $errors[$field][] = "{$label} must be numeric.";
                    }
                    break;

                case 'integer':
                    if (!filter_var($value, FILTER_VALIDATE_INT)) {
                        $errors[$field][] = "{$label} must be an integer.";
                    }
                    break;

                case 'float':
                    if (!filter_var($value, FILTER_VALIDATE_FLOAT)) {
                        $errors[$field][] = "{$label} must be a decimal number.";
                    }
                    break;

                case 'boolean':
                    if (!in_array($value, [true, false, 1, 0, '1', '0', 'true', 'false'], true)) {
                        $errors[$field][] = "{$label} must be true or false.";
                    }
                    break;

                case 'array':
                    if (!is_array($value)) {
                        $errors[$field][] = "{$label} must be an array.";
                    }
                    break;

                // ── String format ─────────────────────────────────────────────
                case 'email':
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[$field][] = "{$label} must be a valid email address.";
                    }
                    break;

                case 'url':
                    if (!filter_var($value, FILTER_VALIDATE_URL)) {
                        $errors[$field][] = "{$label} must be a valid URL.";
                    }
                    break;

                case 'alpha':
                    if (!ctype_alpha((string)$value)) {
                        $errors[$field][] = "{$label} must contain letters only.";
                    }
                    break;

                case 'alpha_num':
                    if (!ctype_alnum((string)$value)) {
                        $errors[$field][] = "{$label} must contain letters and numbers only.";
                    }
                    break;

                case 'alpha_dash':
                    if (!preg_match('/^[a-zA-Z0-9_\-]+$/', (string)$value)) {
                        $errors[$field][] = "{$label} must contain letters, numbers, dashes, or underscores only.";
                    }
                    break;

                case 'regex':
                    if ($ruleParam && !preg_match($ruleParam, (string)$value)) {
                        $errors[$field][] = "{$label} format is invalid.";
                    }
                    break;

                // ── Size ──────────────────────────────────────────────────────
                case 'min':
                    $n = (int) $ruleParam;
                    if ($isArray) {
                        if (count($value) < $n) {
                            $errors[$field][] = "{$label} must have at least {$n} item(s).";
                        }
                    } elseif (is_numeric($value)) {
                        if ((float)$value < $n) {
                            $errors[$field][] = "{$label} must be at least {$n}.";
                        }
                    } else {
                        if (strlen((string)$value) < $n) {
                            $errors[$field][] = "{$label} must be at least {$n} characters.";
                        }
                    }
                    break;

                case 'max':
                    $n = (int) $ruleParam;
                    if ($isArray) {
                        if (count($value) > $n) {
                            $errors[$field][] = "{$label} must not exceed {$n} item(s).";
                        }
                    } elseif (is_numeric($value)) {
                        if ((float)$value > $n) {
                            $errors[$field][] = "{$label} must not exceed {$n}.";
                        }
                    } else {
                        if (strlen((string)$value) > $n) {
                            $errors[$field][] = "{$label} must not exceed {$n} characters.";
                        }
                    }
                    break;

                // ── Inclusion ─────────────────────────────────────────────────
                case 'in':
                    $allowed = explode(',', $ruleParam ?? '');
                    if (!in_array($value, $allowed, true)) {
                        $errors[$field][] = "{$label} must be one of: " . implode(', ', $allowed) . ".";
                    }
                    break;

                case 'not_in':
                    $forbidden = explode(',', $ruleParam ?? '');
                    if (in_array($value, $forbidden, true)) {
                        $errors[$field][] = "{$label} contains an invalid value.";
                    }
                    break;

                // ── Date / Time ───────────────────────────────────────────────
                case 'date':
                    $d = \DateTime::createFromFormat('Y-m-d', (string)$value);
                    if (!$d || $d->format('Y-m-d') !== (string)$value) {
                        $errors[$field][] = "{$label} must be a valid date (YYYY-MM-DD).";
                    }
                    break;

                case 'datetime':
                    $d = \DateTime::createFromFormat('Y-m-d H:i:s', (string)$value);
                    if (!$d || $d->format('Y-m-d H:i:s') !== (string)$value) {
                        $errors[$field][] = "{$label} must be a valid datetime (YYYY-MM-DD HH:MM:SS).";
                    }
                    break;

                case 'time':
                    $d = \DateTime::createFromFormat('H:i:s', (string)$value)
                        ?: \DateTime::createFromFormat('H:i', (string)$value);
                    if (!$d) {
                        $errors[$field][] = "{$label} must be a valid time (HH:MM or HH:MM:SS).";
                    }
                    break;

                case 'before':
                    $d     = strtotime((string)$value);
                    $limit = strtotime($ruleParam ?? '');
                    if ($d === false || $limit === false || $d >= $limit) {
                        $errors[$field][] = "{$label} must be a date before {$ruleParam}.";
                    }
                    break;

                case 'after':
                    $d     = strtotime((string)$value);
                    $limit = strtotime($ruleParam ?? '');
                    if ($d === false || $limit === false || $d <= $limit) {
                        $errors[$field][] = "{$label} must be a date after {$ruleParam}.";
                    }
                    break;
            }
        }
    }

    // ── Wildcard handler  data.*.id ───────────────────────────────────────────

    private function validateWildcard(string $field, array $rules, array &$errors): void
    {
        // e.g.  data.*.id  →  parent=data  child=id
        [$parent,, $child] = explode('.', $field, 3);

        $array = $this->data[$parent] ?? null;

        if (!is_array($array)) {
            $errors[$field][] = "{$parent} must be an array.";
            return;
        }

        foreach ($array as $index => $item) {
            $value     = is_array($item) ? ($item[$child] ?? null) : null;
            $errorKey  = "{$parent}[{$index}].{$child}";
            $this->applyRules($errorKey, $value, $rules, $errors);
        }
    }

    // ── Dot notation value getter ─────────────────────────────────────────────

    private function getDotValue(string $field, array $data): mixed
    {
        $keys = explode('.', $field);
        $val  = $data;
        foreach ($keys as $key) {
            if (!is_array($val) || !array_key_exists($key, $val)) {
                return null;
            }
            $val = $val[$key];
        }
        return $val;
    }

    // ── Body parser ───────────────────────────────────────────────────────────

    private function parseBody(): array
    {
        if ($this->method === 'GET' || $this->method === 'DELETE') {
            return $this->clean($_GET);
        }

        $raw         = file_get_contents('php://input');
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (str_contains($contentType, 'application/json') && !empty($raw)) {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $this->cleanMixed($decoded) : [];
        }

        if ($this->method === 'PUT' || $this->method === 'PATCH') {
            parse_str($raw, $parsed);
            return $this->clean($parsed);
        }

        return $this->clean($_POST);
    }

    private function parseHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name           = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }
        return $headers;
    }

    // ── Cleaners ──────────────────────────────────────────────────────────────

    /** Clean flat array — scalar values only */
    // private function clean(mixed $data): array
    // {
    //     if (!is_array($data)) return [];
    //     $out = [];
    //     foreach ($data as $k => $v) {
    //         $out[$k] = is_array($v)
    //             ? $this->clean($v)
    //             : trim(strip_tags(stripslashes((string)$v)));
    //     }
    //     return $out;
    // }
    private function clean(mixed $data): array
    {
        if (!is_array($data)) return [];

        $out = [];
        foreach ($data as $k => $v) {
            $out[$k] = is_array($v)
                ? $this->clean($v)
                : $v; // keep raw value
        }
        return $out;
    }

    /**
     * Clean mixed data — preserves arrays/objects (for JSON bodies
     * that contain nested arrays like data[].id).
     * Only strips tags on actual string leaves.
     */
    private function cleanMixed(mixed $data): mixed
    {
        if (is_array($data)) {
            $out = [];
            foreach ($data as $k => $v) {
                $out[$k] = $this->cleanMixed($v);
            }
            return $out;
        }

        if (is_string($data)) {
            return trim(strip_tags(stripslashes($data)));
        }

        return $data; // int, float, bool, null — pass through as-is
    }
}
