<?php
// Basic CORS helper for cross-origin requests during development
function send_cors_headers() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, apikey, Prefer');
}

// Reads Supabase config from environment variables.
// Ensure these are set server-side (never expose service role key client-side).
function supabase_config() {
    $url = getenv('SUPABASE_URL');
    $service_key = getenv('SUPABASE_SERVICE_ROLE_KEY');
    if (!$url || !$service_key) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Missing SUPABASE_URL or SUPABASE_SERVICE_ROLE_KEY in server environment.'
        ]);
        exit;
    }
    return [
        'url' => rtrim($url, '/'),
        'service_key' => $service_key,
    ];
}

function http_json($method, $url, $headers = [], $body = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $headers = array_merge(['Content-Type: application/json'], $headers);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
    }
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return [$httpcode, $response, $err];
}

function random_password($length = 14) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@$%^*';
    $pass = '';
    for ($i=0; $i<$length; $i++) {
        $pass .= $chars[random_int(0, strlen($chars)-1)];
    }
    return $pass;
}
