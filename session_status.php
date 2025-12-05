<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';
send_cors_headers();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
error_reporting(0);

// If token invalid, middleware will 401
$auth = require_auth([]);

echo json_encode([
	'ok' => true,
	'user' => [
		'auth_user_id' => $auth['auth_user_id'],
		'app_user_id' => $auth['app_user_id'],
		'roles' => $auth['roles'],
		'email' => $auth['raw']['email'] ?? null,
	]
]);
