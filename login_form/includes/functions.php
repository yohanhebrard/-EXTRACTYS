<?php
// functions.php

// Function to sanitize user input to prevent XSS attacks
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Function to hash passwords securely
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

// Function to verify hashed passwords
function verifyPassword($password, $hashedPassword) {
    return password_verify($password, $hashedPassword);
}

// Function to generate a random token for password reset
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Function to validate email format
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Function to check if a string is empty
function isEmpty($value) {
    return empty(trim($value));
}
?>