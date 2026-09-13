<?php

/**
 * Custom validation messages — English
 *
 * These override or extend CI4's built-in validation messages
 * from vendor/codeigniter4/framework/system/Language/en/Validation.php.
 */
return [
    // -------------------------------------------------------------------------
    // Generic overrides (cleaner wording)
    // -------------------------------------------------------------------------
    'required'              => 'The {field} field is required.',
    'min_length'            => 'The {field} must be at least {param} characters.',
    'max_length'            => 'The {field} must not exceed {param} characters.',
    'valid_email'           => 'Please enter a valid email address.',
    'matches'               => '{field} does not match {param}.',
    'is_unique'             => 'This {field} is already in use.',
    'in_list'               => 'The {field} must be one of: {param}.',
    'integer'               => 'The {field} must be a whole number.',
    'decimal'               => 'The {field} must be a valid number.',
    'greater_than_equal_to' => 'The {field} must be {param} or greater.',
];
