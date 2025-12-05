<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';
send_cors_headers();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
error_reporting(0);

$cfg = supabase_config();
$AUTH = 'Authorization: Bearer ' . $cfg['service_key'];
$APIK = 'apikey: ' . $cfg['service_key'];
$rest = $cfg['url'] . '/rest/v1';

// Only Staff/Admin can set roles
require_auth(['Staff','Admin']);

$input = json_decode(file_get_contents('php://input'), true);
$type = $input['type'] ?? null; // 'employee' | 'guardian' | 'user'
$id = (int)($input['id'] ?? 0);
$roleName = $input['role'] ?? null;

if (!$type || !$id || !$roleName) {
  http_response_code(400);
  echo json_encode(['error' => 'type, id and role are required']);
  exit;
}

// Resolve UserID based on type
$userId = null;
if ($type === 'user') {
  $userId = $id;
} elseif ($type === 'employee') {
  [$ce, $re] = http_json('GET', "$rest/employee?EmpID=eq.$id&select=UserID", [$AUTH, $APIK]);
  $rows = json_decode($re, true);
  $userId = is_array($rows) && count($rows) ? ($rows[0]['UserID'] ?? null) : null;
} elseif ($type === 'guardian') {
  [$cg, $rg] = http_json('GET', "$rest/guardian?GuardianID=eq.$id&select=UserID", [$AUTH, $APIK]);
  $rows = json_decode($rg, true);
  $userId = is_array($rows) && count($rows) ? ($rows[0]['UserID'] ?? null) : null;
}
if (!$userId) {
  http_response_code(404);
  echo json_encode(['error' => 'User not linked']);
  exit;
}

// Lookup role id
[$cr, $rr] = http_json('GET', "$rest/role?RoleName=eq." . rawurlencode($roleName) . '&select=RoleID', [$AUTH, $APIK]);
$roles = json_decode($rr, true);
if (!is_array($roles) || !count($roles)) {
  http_response_code(404);
  echo json_encode(['error' => 'Role not found']);
  exit;
}
$roleId = $roles[0]['RoleID'];

// Delete previous roles and insert new
http_json('DELETE', "$rest/user_role?UserID=eq.$userId", [$AUTH, $APIK]);
[$ci, $ri, $ei] = http_json('POST', "$rest/user_role", [$AUTH, $APIK], [[ 'UserID' => $userId, 'RoleID' => $roleId ]]);
if ($ei || $ci >= 400) {
  http_response_code(500);
  echo json_encode(['error' => 'Failed to assign role', 'detail' => $ri ?: $ei]);
  exit;
}

echo json_encode(['ok' => true]);

// Fire-and-forget audit log
try {
  $caller = require_auth([]);
  $ip = $_SERVER['REMOTE_ADDR'] ?? null;
  $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
  $newData = [ 'UserID' => $userId, 'RoleName' => $roleName ];
  http_json('POST', "$rest/audit_log", [$AUTH, $APIK], [[
    'UserID' => $caller['app_user_id'] ?? null,
    'Action' => 'Set Role',
    'TableName' => 'user_role',
    'RecordID' => $userId,
    'OldData' => null,
    'NewData' => $newData,
    'IPAddress' => $ip,
    'UserAgent' => $ua
  ]]);
} catch (\Throwable $e) { /* ignore */ }
