<?php

namespace KelvinKurniawan\LightORM\Validation;

use KelvinKurniawan\LightORM\Contracts\ValidatorInterface;

class Validator implements ValidatorInterface {
    protected array $data        = [];
    protected array $rules       = [];
    protected array $messages    = [];
    protected array $errors      = [];
    protected array $customRules = [];

    public function validate(array $data, array $rules, array $messages = []): bool {
        $this->data     = $data;
        $this->rules    = $rules;
        $this->messages = $messages;
        $this->errors   = [];

        foreach($rules as $field => $fieldRules) {
            $this->validateField($field, $fieldRules);
        }

        return empty($this->errors);
    }

    public function errors(): array {
        return $this->errors;
    }

    public function addRule(string $name, callable $callback): void {
        $this->customRules[$name] = $callback;
    }

    /**
     * Validate a single field
     */
    protected function validateField(string $field, string|array $rules): void {
        if(is_string($rules)) {
            $rules = explode('|', $rules);
        }

        $value = $this->data[$field] ?? NULL;

        foreach($rules as $rule) {
            $this->validateRule($field, $value, $rule);
        }
    }

    /**
     * Validate a single rule
     */
    protected function validateRule(string $field, mixed $value, string $rule): void {
        // Parse rule and parameters
        $ruleParts  = explode(':', $rule, 2);
        $ruleName   = $ruleParts[0];
        $parameters = isset($ruleParts[1]) ? explode(',', $ruleParts[1]) : [];

        // Check if it's a custom rule
        if(isset($this->customRules[$ruleName])) {
            $isValid = call_user_func($this->customRules[$ruleName], $value, $parameters, $this->data);
            if(!$isValid) {
                $this->addError($field, $ruleName, $parameters);
            }
            return;
        }

        // Built-in validation rules
        $methodName = 'validate' . ucfirst($ruleName);
        if(method_exists($this, $methodName)) {
            $isValid = $this->$methodName($value, $parameters);
            if(!$isValid) {
                $this->addError($field, $ruleName, $parameters);
            }
        }
    }

    /**
     * Add an error message
     */
    protected function addError(string $field, string $rule, array $parameters = []): void {
        $message = $this->getErrorMessage($field, $rule, $parameters);

        if(!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }

        $this->errors[$field][] = $message;
    }

    /**
     * Get error message for a field and rule
     */
    protected function getErrorMessage(string $field, string $rule, array $parameters = []): string {
        $key = "{$field}.{$rule}";

        if(isset($this->messages[$key])) {
            return $this->messages[$key];
        }

        if(isset($this->messages[$rule])) {
            return str_replace(':field', $field, $this->messages[$rule]);
        }

        return $this->getDefaultMessage($field, $rule, $parameters);
    }

    /**
     * Get default error message
     */
    protected function getDefaultMessage(string $field, string $rule, array $parameters = []): string {
        $messages = [
            'required'  => "The {$field} field is required.",
            'email'     => "The {$field} must be a valid email address.",
            'min'       => "The {$field} must be at least {$parameters[0]} characters.",
            'max'       => "The {$field} may not be greater than {$parameters[0]} characters.",
            'numeric'   => "The {$field} must be a number.",
            'integer'   => "The {$field} must be an integer.",
            'boolean'   => "The {$field} must be true or false.",
            'date'      => "The {$field} must be a valid date.",
            'url'       => "The {$field} must be a valid URL.",
            'unique'    => "The {$field} has already been taken.",
            'exists'    => "The selected {$field} is invalid.",
            'confirmed' => "The {$field} confirmation does not match.",
            'in'        => "The selected {$field} is invalid.",
            'not_in'    => "The selected {$field} is invalid.",
            'regex'     => "The {$field} format is invalid.",
        ];

        return $messages[$rule] ?? "The {$field} is invalid.";
    }

    // Built-in validation rules

    protected function validateRequired(mixed $value, array $parameters = []): bool {
        if($value === NULL || $value === '') {
            return FALSE;
        }

        if(is_array($value) && empty($value)) {
            return FALSE;
        }

        return TRUE;
    }

    protected function validateEmail(mixed $value, array $parameters = []): bool {
        if($value === NULL || $value === '') {
            return TRUE; // Use 'required' rule for null/empty checks
        }

        return filter_var($value, FILTER_VALIDATE_EMAIL) !== FALSE;
    }

    protected function validateMin(mixed $value, array $parameters = []): bool {
        if($value === NULL || $value === '') {
            return TRUE;
        }

        $min = (int) ($parameters[0] ?? 0);

        if(is_string($value)) {
            return mb_strlen($value) >= $min;
        }

        if(is_numeric($value)) {
            return $value >= $min;
        }

        if(is_array($value)) {
            return count($value) >= $min;
        }

        return FALSE;
    }

    protected function validateMax(mixed $value, array $parameters = []): bool {
        if($value === NULL || $value === '') {
            return TRUE;
        }

        $max = (int) ($parameters[0] ?? 0);

        if(is_string($value)) {
            return mb_strlen($value) <= $max;
        }

        if(is_numeric($value)) {
            return $value <= $max;
        }

        if(is_array($value)) {
            return count($value) <= $max;
        }

        return FALSE;
    }

    protected function validateNumeric(mixed $value, array $parameters = []): bool {
        if($value === NULL || $value === '') {
            return TRUE;
        }

        return is_numeric($value);
    }

    protected function validateInteger(mixed $value, array $parameters = []): bool {
        if($value === NULL || $value === '') {
            return TRUE;
        }

        return filter_var($value, FILTER_VALIDATE_INT) !== FALSE;
    }

    protected function validateBoolean(mixed $value, array $parameters = []): bool {
        if($value === NULL || $value === '') {
            return TRUE;
        }

        $acceptable = [TRUE, FALSE, 0, 1, '0', '1', 'true', 'false'];
        return in_array($value, $acceptable, TRUE);
    }

    protected function validateDate(mixed $value, array $parameters = []): bool {
        if($value === NULL || $value === '') {
            return TRUE;
        }

        if($value instanceof \DateTime) {
            return TRUE;
        }

        if(is_string($value)) {
            return strtotime($value) !== FALSE;
        }

        return FALSE;
    }

    protected function validateUrl(mixed $value, array $parameters = []): bool {
        if($value === NULL || $value === '') {
            return TRUE;
        }

        return filter_var($value, FILTER_VALIDATE_URL) !== FALSE;
    }

    protected function validateConfirmed(mixed $value, array $parameters = []): bool {
        $confirmationField = ($parameters[0] ?? '') ?: ($this->getCurrentField() . '_confirmation');
        $confirmationValue = $this->data[$confirmationField] ?? NULL;

        return $value === $confirmationValue;
    }

    protected function validateIn(mixed $value, array $parameters = []): bool {
        if($value === NULL || $value === '') {
            return TRUE;
        }

        return in_array((string) $value, $parameters);
    }

    protected function validateNotIn(mixed $value, array $parameters = []): bool {
        if($value === NULL || $value === '') {
            return TRUE;
        }

        return !in_array((string) $value, $parameters);
    }

    protected function validateRegex(mixed $value, array $parameters = []): bool {
        if($value === NULL || $value === '') {
            return TRUE;
        }

        if(empty($parameters[0])) {
            return FALSE;
        }

        return preg_match($parameters[0], (string) $value) === 1;
    }

    /**
     * Get the current field being validated (helper for complex rules)
     */
    protected function getCurrentField(): string {
        // This would need to be tracked during validation
        // For now, returning empty string
        return '';
    }

    /**
     * Create a validator instance
     */
    public static function make(array $data, array $rules, array $messages = []): self {
        $validator = new static();
        $validator->validate($data, $rules, $messages);
        return $validator;
    }

    /**
     * Get the first error message for a field
     */
    public function getFirstError(string $field): ?string {
        return $this->errors[$field][0] ?? NULL;
    }

    /**
     * Get all error messages as a flat array
     */
    public function getAllErrors(): array {
        $allErrors = [];
        foreach($this->errors as $fieldErrors) {
            $allErrors = array_merge($allErrors, $fieldErrors);
        }
        return $allErrors;
    }

    /**
     * Check if validation has any errors
     */
    public function fails(): bool {
        return !empty($this->errors);
    }

    /**
     * Check if validation passed
     */
    public function passes(): bool {
        return empty($this->errors);
    }
}
