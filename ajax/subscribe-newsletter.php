<?php
// =====================================================================
// ajax/subscribe-newsletter.php — Stores an email from the footer
// newsletter signup (see includes/footer.php). Public endpoint.
// Re-subscribing an existing (unsubscribed) email reactivates it
// instead of erroring out, thanks to the UNIQUE key on email.
// =====================================================================
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');
include __DIR__ . '/../includes/db.php';

$response = ['success' => false];

$email = trim($_POST['email'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    $response['error'] = 'A valid email is required.';
    echo json_encode($response);
    exit;
}
if (strlen($email) > 255) {
    $email = substr($email, 0, 255);
}

$stmt = $conn->prepare("
    INSERT INTO newsletter_subscribers (email, status, created_at)
    VALUES (?, 'active', NOW())
    ON DUPLICATE KEY UPDATE status = 'active'
");
$stmt->bind_param("s", $email);
$response['success'] = $stmt->execute();
if (!$response['success']) {
    $response['error'] = 'Could not save subscription.';
}

echo json_encode($response);
