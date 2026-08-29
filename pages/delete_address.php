<?php
// =====================================================
// AgriCart — Address Book: delete an address (AJAX, JSON)
// =====================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
include __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Please log in first.']);
    exit;
}
if (!csrf_verify()) {
    echo json_encode(['success' => false, 'error' => 'Session expired, please refresh and try again.']);
    exit;
}

$userId    = (int)$_SESSION['user_id'];
$addressId = (int)($_POST['address_id'] ?? 0);
if ($addressId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid address.']);
    exit;
}

try {
    $stmt = $conn->prepare("DELETE FROM user_addresses WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $addressId, $userId);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        // If the deleted address was the default, promote the next most
        // recent remaining address to default so checkout always has one
        // pre-selected (if any addresses are left).
        $chk = $conn->prepare("SELECT COUNT(*) AS c FROM user_addresses WHERE user_id = ? AND is_default = 1");
        $chk->bind_param("i", $userId);
        $chk->execute();
        $hasDefault = (int)($chk->get_result()->fetch_assoc()['c'] ?? 0) > 0;
        if (!$hasDefault) {
            $promote = $conn->prepare("UPDATE user_addresses SET is_default = 1 WHERE user_id = ? ORDER BY id DESC LIMIT 1");
            $promote->bind_param("i", $userId);
            $promote->execute();
        }
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Address not found.']);
    }
} catch (\mysqli_sql_exception $e) {
    error_log('AgriCart delete_address: ' . $e->getMessage());
    if ((int)$e->getCode() === 1451) {
        // FK constraint still RESTRICT (setup/db_migration_fix_address_delete.sql
        // not run yet on this DB) — this address is used by an existing order.
        echo json_encode(['success' => false, 'error' => 'This address is linked to a past order and can\'t be deleted yet. Please run the latest database update and try again.']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Could not delete address, please try again.']);
    }
} catch (\Throwable $e) {
    error_log('AgriCart delete_address: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Could not delete address, please try again.']);
}
