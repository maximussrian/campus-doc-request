<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/validation_helper.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$student_number = sanitizeText($input['student_number'] ?? '', 50);
$names = sanitizeText($input['names'] ?? '', MAX_NAMES_LENGTH);
$surnames = sanitizeText($input['surnames'] ?? '', MAX_NAMES_LENGTH);
$email = strtolower(sanitizeText($input['email'] ?? '', MAX_EMAIL_LENGTH));
$password = $input['password'] ?? '';

define('ALLOWED_EMAIL_DOMAIN', 'evsu.edu.ph');
define('STUDENT_NUMBER_PATTERN', '/^\d{4}-\d{5}$/');

$errors = [];
if (empty($student_number)) {
    $errors[] = 'Student number is required';
} elseif (!preg_match(STUDENT_NUMBER_PATTERN, $student_number)) {
    $errors[] = 'Student number must follow the format YYYY-NNNNN (e.g. 2022-32222)';
}
if (empty($names))    $errors[] = 'First name is required';
if (empty($surnames)) $errors[] = 'Last name is required';
if (empty($email)) {
    $errors[] = 'Email is required';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email format';
} elseif (!str_ends_with(strtolower($email), '@' . ALLOWED_EMAIL_DOMAIN)) {
    $errors[] = 'Only university email addresses are allowed (@' . ALLOWED_EMAIL_DOMAIN . ')';
}
if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters';
if (strlen($password) > MAX_PASSWORD_INPUT) $errors[] = 'Invalid input';

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode('. ', $errors)]);
    exit;
}

try {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE student_number = ? OR email = ?');
    $stmt->execute([$student_number, $email]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Student number or email already registered']);
        exit;
    }

    $stmt = $pdo->prepare('INSERT INTO users (student_number, names, surnames, email, password_hash) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$student_number, $names, $surnames, $email, password_hash($password, PASSWORD_DEFAULT)]);

    echo json_encode(['success' => true, 'message' => 'Account created successfully']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Registration failed']);
}