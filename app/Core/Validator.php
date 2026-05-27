<?php

declare(strict_types=1);

namespace App\Core;

final class Validator
{
    public static function validate(array $data, array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            foreach ((array) $fieldRules as $rule) {
                if ($rule === 'required' && ($value === null || $value === '')) {
                    $errors[$field][] = 'required';
                }
                if ($rule === 'email' && $value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field][] = 'email';
                }
                if (str_starts_with($rule, 'min:') && is_string($value) && strlen($value) < (int) substr($rule, 4)) {
                    $errors[$field][] = $rule;
                }
                if ($rule === 'numeric' && $value !== null && !is_numeric($value)) {
                    $errors[$field][] = 'numeric';
                }
            }
        }

        return $errors;
    }
}
