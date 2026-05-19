<?php
/**
 * Returns developer support contact info (public, no auth required).
 */
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, max-age=0');
require_once __DIR__ . '/../config/config.php';
$email = defined('DEV_SUPPORT_EMAIL') ? DEV_SUPPORT_EMAIL : '';
$phone = defined('DEV_SUPPORT_PHONE') ? DEV_SUPPORT_PHONE : '';
echo json_encode(['success' => true, 'email' => $email, 'phone' => $phone]);
