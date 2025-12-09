<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

header('Content-Type: application/json');

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// Read JSON payload
$input = file_get_contents('php://input');
$payload = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

// Extract values with safe defaults
$fingerprint = $payload['fingerprint'] ?? '';
$label = strip_tags(trim($payload['label'] ?? 'Default device'));
$token = $payload['csrf_token'] ?? '';

// CSRF check
if (!validate_csrf($token)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Security token mismatch.']);
    exit;
}

// Fingerprint validation
if (strlen($fingerprint) < 10) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid fingerprint payload.']);
    exit;
}

// Limit label length
$label = substr($label, 0, 120);

try {
    $user_id = (int)($_SESSION['user']['id'] ?? 0);
    
    if ($user_id === 0) {
        throw new Exception('User session invalid.');
    }

    // Prevent duplicate fingerprints
    $stmt = mysqli_prepare($conn, "SELECT id FROM user_fingerprints WHERE user_id=? AND fingerprint_hash=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'is', $user_id, $fingerprint);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if (mysqli_num_rows($res) > 0) {
        echo json_encode(['success' => true, 'message' => 'This device is already registered.']);
        exit;
    }
    mysqli_stmt_close($stmt);

    // Insert fingerprint
    $stmt = mysqli_prepare($conn, "INSERT INTO user_fingerprints (user_id, fingerprint_hash, device_label, is_active, created_at) VALUES (?, ?, ?, 1, NOW())");
    mysqli_stmt_bind_param($stmt, 'iss', $user_id, $fingerprint, $label);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    echo json_encode(['success' => true, 'message' => 'Fingerprint registered for this device.']);

} catch (Throwable $e) {
    http_response_code(500);
    log_message('Fingerprint register failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Unable to save fingerprint.']);
}
