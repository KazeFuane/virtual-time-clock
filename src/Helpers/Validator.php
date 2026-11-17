<?php
/**
 * Virtual Time Clock - Input Validator
 *
 * Sanitization and validation helper for user inputs.
 */

class Validator
{
    /**
     * Sanitize a string value (trim + escape HTML entities).
     */
    public static function sanitizeString(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Validate that a value is not empty.
     * Returns an error message or null if valid.
     */
    public static function validateRequired(?string $value, string $fieldName): ?string
    {
        if ($value === null || trim($value) === '') {
            return "$fieldName is required.";
        }
        return null;
    }

    /**
     * Validate an email address.
     * Returns an error message or null if valid.
     */
    public static function validateEmail(?string $email): ?string
    {
        if ($email === null || trim($email) === '') {
            return 'Email is required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Please enter a valid email address.';
        }
        return null;
    }

    /**
     * Validate a username (alphanumeric + underscore, 3-50 chars).
     * Returns an error message or null if valid.
     */
    public static function validateUsername(?string $username): ?string
    {
        if ($username === null || trim($username) === '') {
            return 'Username is required.';
        }
        if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
            return 'Username must be 3-50 characters (letters, numbers, underscores only).';
        }
        return null;
    }

    /**
     * Validate a password (minimum 8 characters).
     * Returns an error message or null if valid.
     */
    public static function validatePassword(?string $password): ?string
    {
        if ($password === null || trim($password) === '') {
            return 'Password is required.';
        }
        if (strlen($password) < 8) {
            return 'Password must be at least 8 characters.';
        }
        return null;
    }

    /**
     * Read and decode JSON input from the request body.
     */
    public static function getJsonInput(): array
    {
        $rawInput = file_get_contents('php://input');
        if (empty($rawInput)) {
            return [];
        }
        $decoded = json_decode($rawInput, true);
        return is_array($decoded) ? $decoded : [];
    }
}
