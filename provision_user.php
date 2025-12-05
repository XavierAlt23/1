<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';
send_cors_headers();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// Improve error visibility server-side (log errors; don't display to client in production)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
// Ensure a writable error_log is set (can be adjusted to preferred path)
if (!ini_get('error_log')) ini_set('error_log', sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'php_error.log');

try {

$cfg = supabase_config();
$AUTH = 'Authorization: Bearer ' . $cfg['service_key'];
$APIK = 'apikey: ' . $cfg['service_key'];
$rest = $cfg['url'] . '/rest/v1';
$auth_admin = $cfg['url'] . '/auth/v1/admin/users';
$auth_invite = $cfg['url'] . '/auth/v1/invite';

// Require authenticated caller with Staff or Admin role
require_auth(['Staff','Admin']);

// Determine debug mode: enable if request includes ?debug=1 (temporary, per user request)
$debugMode = false;
$debugRequested = isset($_GET['debug']) && (string)$_GET['debug'] === '1';
if ($debugRequested) {
    // Temporarily enable error display and startup errors for debugging remote issue
    $debugMode = true;
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
}

$input = json_decode(file_get_contents('php://input'), true);
$type = $input['type'] ?? null; // 'employee' | 'guardian'
$id = (int)($input['id'] ?? 0);
$assignRole = $input['role'] ?? null; // 'Staff' | 'Guardian'
$preferPassword = isset($input['prefer_password']) ? (bool)$input['prefer_password'] : null;

if (!$type || !$id || !in_array($type, ['employee', 'guardian'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid request: type and id are required.']);
    exit;
}

$table = $type === 'employee' ? 'employee' : 'guardian';
$key = $type === 'employee' ? 'EmpID' : 'GuardianID';

// 1) Fetch person
[$code, $resp, $err] = http_json('GET', "$rest/$table?$key=eq.$id&select=$key,FirstName,LastName,Email,UserID", [$AUTH, $APIK]);
if ($err) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'HTTP error', 'detail' => $err]);
    exit;
}
$data = json_decode($resp, true);
if ($code >= 400 || !is_array($data) || count($data) === 0) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Record not found']);
    exit;
}
$person = $data[0];
if (!empty($person['UserID'])) {
    echo json_encode(['ok' => true, 'message' => 'Already provisioned']);
    exit;
}
$email = $person['Email'] ?? '';
$first = $person['FirstName'] ?? '';
$last = $person['LastName'] ?? '';
if (!$email) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Email is required to create an account']);
    exit;
}

// 2) Generate username via RPC
[$c2, $r2, $e2] = http_json('POST', "$rest/rpc/gen_unique_username", [$AUTH, $APIK, 'Prefer: params=single-object'], [
    'first_name' => $first,
    'last_name' => $last,
]);
if ($e2) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Username generation failed', 'detail' => $e2]);
    exit;
}
if ($c2 >= 400) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Username generation failed', 'detail' => $r2]);
    exit;
}
$username = trim($r2, "\"\n\r");

// 3) Create/Invite Supabase Auth user (prefer invite)
$useInviteEnv = getenv('PROVISION_USE_INVITE');
$useInvite = ($useInviteEnv === false || $useInviteEnv === '') ? true : !in_array(strtolower($useInviteEnv), ['0','false','off'], true);
if ($preferPassword === true) { $useInvite = false; }
if ($preferPassword === false) { $useInvite = true; }
$password = null;
$authId = null;
if ($useInvite) {
    [$ci3, $ri3, $ei3] = http_json('POST', $auth_invite, [$AUTH, $APIK], [
        'email' => $email,
        'data' => [
            'first_name' => $first,
            'last_name' => $last,
            'provisioned_by' => $type,
        ],
    ]);
    if ($ei3) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Auth invite error', 'detail' => $ei3]);
        exit;
    }
    $inviteRes = json_decode($ri3, true);
    if ($ci3 >= 400 && $ci3 !== 422) { // 422 si ya existe
        http_response_code(500);
        echo json_encode(['error' => 'Auth invite failed', 'detail' => $ri3]);
        exit;
    }
    if (isset($inviteRes['user']) && isset($inviteRes['user']['id'])) {
        $authId = $inviteRes['user']['id'];
    }
    if (!$authId) {
        // Buscar usuario existente por email
        [$cg3, $rg3] = http_json('GET', $auth_admin . '?email=' . rawurlencode($email), [$AUTH, $APIK]);
        $found = json_decode($rg3, true);
        if (is_array($found) && count($found) && isset($found[0]['id'])) {
            $authId = $found[0]['id'];
        }
        if (!$authId) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Auth user not created or found']);
            exit;
        }
    }
} else {
    $password = random_password(14);
    [$c3, $r3, $e3] = http_json('POST', $auth_admin, [$AUTH, $APIK], [
        'email' => $email,
        'password' => $password,
        'email_confirm' => true,
        'user_metadata' => [
            'first_name' => $first,
            'last_name' => $last,
            'provisioned_by' => $type,
            'must_change_password' => true,
        ],
    ]);
    if ($e3) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Auth admin error', 'detail' => $e3]);
        exit;
    }
    $authUser = json_decode($r3, true);
    if ($c3 >= 400 || !isset($authUser['id'])) {
        // Si ya existe el usuario en Auth (p.ej., 422), intentar recuperarlo por email
        [$cg3, $rg3] = http_json('GET', $auth_admin . '?email=' . rawurlencode($email), [$AUTH, $APIK]);
        $found = json_decode($rg3, true);
        if (is_array($found) && count($found) && isset($found[0]['id'])) {
            $authId = $found[0]['id'];
        } else {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Auth user creation failed', 'detail' => $r3]);
            exit;
        }
    } else {
        $authId = $authUser['id'];
    }
}

// 4) Upsert en tabla de aplicación `user` por Email
// Intentar encontrar usuario existente por Email
[$cs0, $rs0] = http_json('GET', "$rest/user?Email=eq." . rawurlencode($email) . '&select=UserID,AuthUserID,UserName', [$AUTH, $APIK]);
$existing = json_decode($rs0, true);
$appUserId = null;
if (is_array($existing) && count($existing) > 0) {
    $appUserId = $existing[0]['UserID'];
    $currentAuth = $existing[0]['AuthUserID'] ?? null;
    if (!$currentAuth) {
        // actualizar AuthUserID y datos básicos si faltan
        [$cu, $ru, $eu] = http_json('PATCH', "$rest/user?UserID=eq.$appUserId", [$AUTH, $APIK, 'Prefer: return=representation'], [
            [ 'AuthUserID' => $authId, 'FirstName' => $first, 'LastName' => $last, 'IsActive' => 1 ]
        ]);
        if ($eu || $cu >= 400) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'App user update failed', 'detail' => $ru ?: $eu]);
            exit;
        }
    }
    // mantener UserName existente para evitar conflictos
    $username = $existing[0]['UserName'] ?? $username;
} else {
    // Insertar nuevo usuario de aplicación
    [$c4, $r4, $e4] = http_json('POST', "$rest/user", [$AUTH, $APIK, 'Prefer: return=representation'], [[
        'UserName' => $username,
        'Email' => $email,
        'FirstName' => $first,
        'LastName' => $last,
        'IsActive' => 1,
        'AuthUserID' => $authId,
    ]]);
    if ($e4) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'App user insert error', 'detail' => $e4]);
        exit;
    }
    $appUsers = json_decode($r4, true);
    if ($c4 >= 400 || !is_array($appUsers) || count($appUsers) === 0) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'App user insert failed', 'detail' => $r4]);
        exit;
    }
    $appUserId = $appUsers[0]['UserID'];
}

// 5) Link to employee/guardian
[$c5, $r5, $e5] = http_json('PATCH', "$rest/$table?$key=eq.$id", [$AUTH, $APIK], [ 'UserID' => $appUserId ]);
if ($e5 || $c5 >= 400) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Linking failed', 'detail' => $r5 ?: $e5]);
    exit;
}

// 6) Optional: assign role
$assignedRoleInfo = null;
if ($assignRole) {
    // Determine role by name or id
    $roleId = null;
    $roleName = null;
    if (is_numeric($assignRole)) {
        $roleId = (int)$assignRole;
        [$cr, $rr, $er] = http_json('GET', "$rest/role?RoleID=eq.$roleId&select=RoleID,RoleName", [$AUTH, $APIK]);
    } else {
        [$cr, $rr, $er] = http_json('GET', "$rest/role?RoleName=eq." . rawurlencode($assignRole) . '&select=RoleID,RoleName', [$AUTH, $APIK]);
    }
    if ($er || $cr >= 400) {
        // Role lookup failed
        // Do not abort provisioning entirely; return info about missing role
        $assignedRoleInfo = ['ok' => false, 'error' => 'Role lookup failed', 'detail' => $rr ?: $er];
    } else {
        $roles = json_decode($rr, true);
        if (!is_array($roles) || count($roles) === 0) {
            $assignedRoleInfo = ['ok' => false, 'error' => 'Role not found'];
        } else {
            $role = $roles[0];
            $roleId = (int)$role['RoleID'];
            $roleName = $role['RoleName'] ?? null;

            // Check if user_role already exists
            [$cex, $rex, $eex] = http_json('GET', "$rest/user_role?UserID=eq.$appUserId&RoleID=eq.$roleId&select=UserRoleID", [$AUTH, $APIK]);
            $exists = false;
            if (!$eex && $cex < 400) {
                $items = json_decode($rex, true);
                if (is_array($items) && count($items) > 0) $exists = true;
            }

            if ($exists) {
                $assignedRoleInfo = ['ok' => true, 'message' => 'Role already assigned', 'RoleID' => $roleId, 'RoleName' => $roleName];
            } else {
                // AssignedBy => current caller app user id if available
                $assignedBy = null;
                try { $caller = validate_supabase_user(); $assignedBy = $caller['app_user_id'] ?? null; } catch (\Throwable $_) { $assignedBy = null; }
                [$crp, $rrp, $erp] = http_json('POST', "$rest/user_role", [$AUTH, $APIK, 'Prefer: return=representation'], [[ 'UserID' => $appUserId, 'RoleID' => $roleId, 'AssignedBy' => $assignedBy ]]);
                if ($erp || $crp >= 400) {
                    // If insert failed because of conflict, try to ignore, otherwise return informative note
                    $assignedRoleInfo = ['ok' => false, 'error' => 'Assign role failed', 'detail' => $rrp ?: $erp];
                } else {
                    $rp = json_decode($rrp, true);
                    $assignedRoleInfo = ['ok' => true, 'RoleID' => $roleId, 'RoleName' => $roleName, 'UserRole' => $rp[0] ?? null];
                }
            }
        }
    }
}

echo json_encode([
    'ok' => true,
    'user' => [
        'UserID' => $appUserId,
        'UserName' => $username,
        'Email' => $email,
    ],
    'invited' => $useInvite,
    'temp_password' => $useInvite ? null : $password,
    'assigned_role' => $assignedRoleInfo,
]);

// Fire-and-forget audit log
try {
    $caller = validate_supabase_user();
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $action = 'Provision User';
    $newData = [
        'UserID' => $appUserId,
        'Email' => $email,
        'AssignedRole' => $assignRole,
        'LinkedTable' => $table,
        'LinkedId' => $id
    ];
    http_json('POST', "$rest/audit_log", [ $AUTH, $APIK ], [[
        'UserID' => $caller['app_user_id'] ?? null,
        'Action' => $action,
        'TableName' => 'user',
        'RecordID' => $appUserId,
        'OldData' => null,
        'NewData' => $newData,
        'IPAddress' => $ip,
        'UserAgent' => $ua
    ]]);
} catch (\Throwable $e) { /* ignore */ }

} catch (\Throwable $ex) {
    // Log full exception server-side for debugging
    error_log("provision_user.php exception: " . $ex->getMessage() . "\n" . $ex->getTraceAsString());
    http_response_code(500);
    $payload = [ 'ok' => false, 'error' => 'Internal server error' ];
    // Include safe detail; if debugMode is active (admin requested), include full trace
    if (!empty($debugMode)) {
        $payload['detail'] = $ex->getMessage();
        $payload['file'] = $ex->getFile();
        $payload['line'] = $ex->getLine();
        $payload['trace'] = $ex->getTraceAsString();
    } else {
        // minimal detail to help frontend
        $payload['detail'] = $ex->getMessage();
    }
    echo json_encode($payload);
    exit;
}
