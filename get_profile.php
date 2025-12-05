<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';
send_cors_headers();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
error_reporting(0);

// Require any authenticated user
$auth = require_auth([]);

$cfg = supabase_config();
$rest = rtrim($cfg['url'], '/') . '/rest/v1';
$AUTH_SR = 'Authorization: Bearer ' . $cfg['service_key'];
$APIK    = 'apikey: ' . $cfg['service_key'];

$appUser = null;
if (!empty($auth['app_user_id'])) {
    [$c, $r] = http_json('GET', $rest . '/user?UserID=eq.' . $auth['app_user_id'] . '&select=UserID,UserName,Email,FirstName,LastName,IsActive&limit=1', [$AUTH_SR, $APIK]);
    $rows = json_decode($r, true);
    if (is_array($rows) && count($rows)) $appUser = $rows[0];
}

echo json_encode([
    'ok' => true,
    'user' => [
        'auth_user_id' => $auth['auth_user_id'],
        'app_user_id'  => $auth['app_user_id'],
        'roles'        => $auth['roles'],
        'profile'      => $appUser,
        'auth'         => [
            'email' => $auth['raw']['email'] ?? null,
            'confirmed_at' => $auth['raw']['confirmed_at'] ?? null,
        ],
    ],
]);
