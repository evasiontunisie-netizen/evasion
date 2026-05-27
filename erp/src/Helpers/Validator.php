<?php
// ============================================================
// ERP PRO - Input Validator
// ============================================================

class Validator {
    private array $errors = [];
    private array $data   = [];

    public function __construct(private array $input) {}

    public static function make(array $input, array $rules): self {
        $v = new self($input);
        foreach ($rules as $field => $ruleStr) {
            $rules_list = explode('|', $ruleStr);
            foreach ($rules_list as $rule) {
                $v->applyRule($field, $rule);
            }
        }
        return $v;
    }

    private function applyRule(string $field, string $rule): void {
        $value  = $this->input[$field] ?? null;
        [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);

        switch ($name) {
            case 'required':
                if ($value === null || $value === '') $this->errors[$field][] = "$field est requis";
                else $this->data[$field] = $value;
                break;
            case 'email':
                if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL))
                    $this->errors[$field][] = "$field doit être un email valide";
                else if ($value) $this->data[$field] = strtolower(trim($value));
                break;
            case 'min':
                if ($value !== null && strlen((string)$value) < (int)$param)
                    $this->errors[$field][] = "$field doit avoir au moins $param caractères";
                break;
            case 'max':
                if ($value !== null && strlen((string)$value) > (int)$param)
                    $this->errors[$field][] = "$field ne peut pas dépasser $param caractères";
                break;
            case 'numeric':
                if ($value !== null && !is_numeric($value))
                    $this->errors[$field][] = "$field doit être un nombre";
                else if ($value !== null) $this->data[$field] = (float)$value;
                break;
            case 'integer':
                if ($value !== null && !filter_var($value, FILTER_VALIDATE_INT))
                    $this->errors[$field][] = "$field doit être un entier";
                else if ($value !== null) $this->data[$field] = (int)$value;
                break;
            case 'in':
                $allowed = explode(',', $param);
                if ($value !== null && !in_array($value, $allowed))
                    $this->errors[$field][] = "$field doit être l'une des valeurs: $param";
                break;
            case 'nullable':
                if (!isset($this->data[$field])) $this->data[$field] = $value;
                break;
            case 'sometimes':
                // Only validate if present
                if (!array_key_exists($field, $this->input)) {
                    unset($this->errors[$field]);
                }
                break;
            case 'url':
                if ($value && !filter_var($value, FILTER_VALIDATE_URL))
                    $this->errors[$field][] = "$field doit être une URL valide";
                break;
            case 'string':
                if ($value !== null) {
                    $this->data[$field] = htmlspecialchars(strip_tags((string)$value), ENT_QUOTES, 'UTF-8');
                }
                break;
        }
        if (!isset($this->errors[$field]) && !isset($this->data[$field]) && isset($this->input[$field])) {
            $this->data[$field] = $this->input[$field];
        }
    }

    public function fails(): bool { return !empty($this->errors); }
    public function errors(): array { return $this->errors; }
    public function validated(): array { return $this->data; }
    public function get(string $field, mixed $default = null): mixed {
        return $this->data[$field] ?? $this->input[$field] ?? $default;
    }
}
