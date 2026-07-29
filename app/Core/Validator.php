<?php

declare(strict_types=1);

namespace App\Core;

class Validator
{
    private array $errors = [];

    public function validate(array $data, array $rules): array
    {
        $this->errors = [];

        foreach ($rules as $field => $fieldRules) {
            $fieldRules = is_array($fieldRules) ? $fieldRules : explode('|', $fieldRules);
            $value = $data[$field] ?? null;

            foreach ($fieldRules as $rule) {
                $params = [];
                if (str_contains($rule, ':')) {
                    [$rule, $paramStr] = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                }

                $result = $this->applyRule($field, $value, $rule, $params, $data);
                if ($result === false) {
                    break;
                }
            }
        }

        return $this->errors;
    }

    private function applyRule(string $field, mixed $value, string $rule, array $params, array $data): bool
    {
        $error = match ($rule) {
            'required' => $this->validateRequired($field, $value),
            'email' => $this->validateEmail($field, $value),
            'min' => $this->validateMin($field, $value, (int)($params[0] ?? 0)),
            'max' => $this->validateMax($field, $value, (int)($params[0] ?? 0)),
            'alpha' => $this->validateAlpha($field, $value),
            'numeric' => $this->validateNumeric($field, $value),
            'url' => $this->validateUrl($field, $value),
            'matches' => $this->validateMatches($field, $value, $params[0] ?? '', $data),
            'unique' => $this->validateUnique($field, $value, $params),
            'slug' => $this->validateSlug($field, $value),
            default => null,
        };

        return $error === null;
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    private function validateRequired(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            $this->addError($field, "The {$field} field is required.");
        }
        return null;
    }

    private function validateEmail(string $field, mixed $value): ?string
    {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, "The {$field} must be a valid email address.");
        }
        return null;
    }

    private function validateMin(string $field, mixed $value, int $min): ?string
    {
        if ($value !== null && $value !== '') {
            $length = is_string($value) ? mb_strlen($value) : (is_numeric($value) ? $value : 0);
            if ($length < $min) {
                $this->addError($field, "The {$field} must be at least {$min}.");
            }
        }
        return null;
    }

    private function validateMax(string $field, mixed $value, int $max): ?string
    {
        if ($value !== null && $value !== '') {
            $length = is_string($value) ? mb_strlen($value) : (is_numeric($value) ? $value : PHP_INT_MAX);
            if ($length > $max) {
                $this->addError($field, "The {$field} must not exceed {$max}.");
            }
        }
        return null;
    }

    private function validateAlpha(string $field, mixed $value): ?string
    {
        if ($value !== null && $value !== '' && !ctype_alpha(str_replace(' ', '', $value))) {
            $this->addError($field, "The {$field} may only contain letters.");
        }
        return null;
    }

    private function validateNumeric(string $field, mixed $value): ?string
    {
        if ($value !== null && $value !== '' && !is_numeric($value)) {
            $this->addError($field, "The {$field} must be a number.");
        }
        return null;
    }

    private function validateUrl(string $field, mixed $value): ?string
    {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
            $this->addError($field, "The {$field} must be a valid URL.");
        }
        return null;
    }

    private function validateMatches(string $field, mixed $value, string $otherField, array $data): ?string
    {
        if ($value !== null && $value !== '' && $value !== ($data[$otherField] ?? null)) {
            $this->addError($field, "The {$field} must match {$otherField}.");
        }
        return null;
    }

    private function validateUnique(string $field, mixed $value, array $params): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $table = $params[0] ?? null;
        $column = $params[1] ?? $field;
        $excludeId = $params[2] ?? null;
        $excludeColumn = $params[3] ?? 'id';

        if ($table === null) {
            return null;
        }

        try {
            $db = Database::getInstance()->getPdo();
            $query = "SELECT COUNT(*) FROM {$table} WHERE {$column} = :value";
            $bindings = ['value' => $value];

            if ($excludeId !== null) {
                $query .= " AND {$excludeColumn} != :exclude_id";
                $bindings['exclude_id'] = $excludeId;
            }

            $stmt = $db->prepare($query);
            $stmt->execute($bindings);

            if ((int)$stmt->fetchColumn() > 0) {
                $this->addError($field, "The {$field} has already been taken.");
            }
        } catch (\Throwable) {
            $this->addError($field, "Unable to validate uniqueness of {$field}.");
        }

        return null;
    }

    private function validateSlug(string $field, mixed $value): ?string
    {
        if ($value !== null && $value !== '' && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value)) {
            $this->addError($field, "The {$field} must be a valid slug.");
        }
        return null;
    }

    public static function sanitize(string $value): string
    {
        return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public static function sanitizeEmail(string $email): string
    {
        return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    }

    public static function sanitizeUrl(string $url): string
    {
        return filter_var(trim($url), FILTER_SANITIZE_URL);
    }

    public static function sanitizeInt(mixed $value): int
    {
        return (int)filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    }

    public static function stripTags(string $value, string $allowedTags = ''): string
    {
        return strip_tags($value, $allowedTags);
    }
}
