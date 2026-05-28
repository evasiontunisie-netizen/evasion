<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function ok(array $data = [], int $status = 200): void
    {
        Response::json(['success' => true, 'data' => $data], $status);
    }

    protected function error(string $message, int $status = 422, array $details = []): void
    {
        Response::json(['success' => false, 'error' => $message, 'details' => $details], $status);
    }

    protected function requireFields(Request $request, array $fields): ?array
    {
        $errors = [];
        foreach ($fields as $field) {
            if ($request->input($field) === null || $request->input($field) === '') {
                $errors[$field] = 'required';
            }
        }

        return $errors === [] ? null : $errors;
    }
}
