<?php
/**
 * Input validation and sanitization helper.
 * Use these before passing user input to the database.
 */

/** Allowed document request statuses (whitelist) */
const VALID_REQUEST_STATUSES = ['pending', 'processing', 'ready', 'claimed'];

/** Allowed export formats (whitelist) */
const VALID_EXPORT_FORMATS = ['csv', 'xls'];

/** Max lengths matching schema */
const MAX_PURPOSE_LENGTH = 255;
const MAX_NOTES_LENGTH = 65535;  // TEXT
const MAX_DEPARTMENT_LENGTH = 255;
const MAX_CHAT_MESSAGE_LENGTH = 65535;
const MAX_EMAIL_LENGTH = 255;
const MAX_NAMES_LENGTH = 100;
const MAX_USERNAME_LENGTH = 100;
const MAX_ACTIVATION_TOKEN_LENGTH = 100;
const MAX_PASSWORD_INPUT = 256;  // Limit input to prevent DoS from excessive hashing

/**
 * Validate status against whitelist. Returns valid status or empty string.
 */
function validateRequestStatus(string $status): string {
    $s = strtolower(trim($status));
    return in_array($s, VALID_REQUEST_STATUSES, true) ? $s : '';
}

/**
 * Validate export format against whitelist. Returns 'csv' or 'xls'.
 */
function validateExportFormat(string $format): string {
    $f = strtolower(trim($format));
    return in_array($f, VALID_EXPORT_FORMATS, true) ? $f : 'csv';
}

/**
 * Validate date as YYYY-MM-DD. Returns validated string or empty.
 */
function validateDate(string $date): string {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return '';
    $t = strtotime($date);
    if ($t === false) return '';
    $d = date('Y-m-d', $t);
    return $d === $date ? $date : '';
}

/**
 * Validate month as YYYY-MM. Returns validated string or empty.
 */
function validateMonth(string $month): string {
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) return '';
    $t = strtotime($month . '-01');
    if ($t === false) return '';
    return date('Y-m', $t) === $month ? $month : '';
}

/**
 * Sanitize text for DB: trim, limit length, strip control chars.
 */
function sanitizeText(string $value, int $maxLength = 255): string {
    $v = trim($value);
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v);
    return mb_substr($v, 0, $maxLength, 'UTF-8');
}

/**
 * Validate admin username: alphanumeric, underscore, dot, hyphen; 3–100 chars.
 */
function validateAdminUsername(string $username): string {
    $u = sanitizeText($username, MAX_USERNAME_LENGTH);
    return preg_match('/^[A-Za-z0-9_.\-]{3,100}$/', $u) ? $u : '';
}

/**
 * Validate role against whitelist.
 */
function validateAdminRole(string $role): string {
    $r = strtolower(trim($role));
    return in_array($r, ['developer', 'registrar', 'teller'], true) ? $r : '';
}
