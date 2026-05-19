<?php
require_once __DIR__ . '/session_init.php';
session_start();
session_destroy();
header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Logged out']);
