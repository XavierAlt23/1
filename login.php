<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/supabase.php';
send_cors_headers();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
http_response_code(405);
echo json_encode([ 'ok' => false, 'error' => 'Use Supabase client-side auth (signInWithPassword) from the frontend.' ]);
