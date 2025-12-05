<?php
// Middleware to validate Supabase access token and optional role checks
// Requires supabase_config() and http_json() from ../config/supabase.php

function get_authorization_header_value() {
    // Try common server vars for Authorization header
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) return $headers['Authorization'];
        if (isset($headers['authorization'])) return $headers['authorization'];
    }
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) return $_SERVER['HTTP_AUTHORIZATION'];
    if (isset($_SERVER['Authorization'])) return $_SERVER['Authorization'];
    return null;
}

function extract_bearer_token() {
    $auth = get_authorization_header_value();
    if (!$auth) return null;
    if (stripos($auth, 'Bearer ') === 0) {
        return trim(substr($auth, 7));
    }
    return null;
}

function auth_deny($code, $message) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

function validate_supabase_user() {
    static $cached = null;
    if ($cached !== null) return $cached;

    $token = extract_bearer_token();
    if (!$token) auth_deny(401, 'Missing Authorization Bearer token');

    $cfg = supabase_config();
    $AUTH = 'Authorization: Bearer ' . $token;
    $APIK = 'apikey: ' . $cfg['service_key'];
    $auth_me = rtrim($cfg['url'], '/') . '/auth/v1/user';

    // Validate token with Supabase Auth
    [$c, $r, $e] = http_json('GET', $auth_me, [$AUTH, $APIK]);
    if ($e || $c >= 400) auth_deny(401, 'Invalid or expired token');

    $user = json_decode($r, true);
    if (!is_array($user) || empty($user['id'])) auth_deny(401, 'Invalid user payload');

    $authUserId = $user['id'];

    // Map to application user and roles
    $rest = rtrim($cfg['url'], '/') . '/rest/v1';
    [$c1, $r1] = http_json('GET', $rest . '/user?AuthUserID=eq.' . rawurlencode($authUserId) . '&select=UserID&limit=1', [ 'apikey: ' . $cfg['service_key'], 'Authorization: Bearer ' . $cfg['service_key'] ]);
    $rows = json_decode($r1, true);
    $appUserId = (is_array($rows) && count($rows) && isset($rows[0]['UserID'])) ? $rows[0]['UserID'] : null;

    $roles = [];
    if ($appUserId) {
        // Fetch role names for this user via join
        $sel = $rest . '/user_role?UserID=eq.' . $appUserId . '&select=role:RoleID(RoleName)';
        [$cr, $rr] = http_json('GET', $sel, [ 'apikey: ' . $cfg['service_key'], 'Authorization: Bearer ' . $cfg['service_key'] ]);
        $items = json_decode($rr, true);
        if (is_array($items)) {
            foreach ($items as $it) {
                if (isset($it['role']['RoleName'])) $roles[] = $it['role']['RoleName'];
            }
        }
    }

    $cached = [
        'auth_user_id' => $authUserId,
        'app_user_id'  => $appUserId,
        'roles'        => $roles,
        'raw'          => $user,
    ];
    return $cached;
}

function require_auth($requiredRoles = []) {
    $info = validate_supabase_user();
    if (empty($requiredRoles)) return $info;

    // If user has at least one of required roles
    $userRoles = array_map('strval', $info['roles'] ?? []);
    foreach ($requiredRoles as $rr) {
        if (in_array($rr, $userRoles, true)) return $info;
    }
    auth_deny(403, 'Forbidden: insufficient role');
}
