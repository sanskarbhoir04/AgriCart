<?php
// =====================================================
// AgriCart — Address Book: create/update a saved address (AJAX, JSON)
//
// CONFIRMED live schema for `user_addresses` (via phpMyAdmin structure view):
//   id, user_id, address_type enum('delivery','farm','billing') default
//   'delivery', full_name, phone, address_line1 (NOT NULL), address_line2,
//   city, district, state (default 'Maharashtra'), pincode, is_default,
//   created_at.
// There is NO generic 'name' / 'mobile' / 'address' / free-text 'label'
// column — earlier versions of this file guessed those names and every
// insert/update failed with "Unknown column", which is why saving always
// showed "Could not save address, please try again."
//
// The checkout UI only has a single free-text "label" box (e.g. "Home")
// and a single free-text "address" textarea, so:
//  - label is matched (case-insensitive) against the address_type enum
//    ('farm'/'billing' if mentioned, else 'delivery' — shown back to the
//    user as "Home" since that's what most people type there).
//  - the address textarea is stored in address_line1 (first 255 chars)
//    and any overflow in address_line2, since address_line1 alone is
//    capped at varchar(255) and is NOT NULL.
// =====================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
header('Content-Type: application/json');
include __DIR__ . '/../includes/db.php';

function agi_json_fail(int $httpCode, string $message): void {
    http_response_code($httpCode);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    agi_json_fail(405, 'Method not allowed.');
}
if (empty($_SESSION['user_id'])) {
    agi_json_fail(401, 'Please log in first.');
}
$userId = (int)$_SESSION['user_id'];

// ---- CSRF ----
$csrfSent = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$csrfSent)) {
    agi_json_fail(403, 'Invalid session, please refresh the page and try again.');
}

// ---- Input ----
$addressId = (int)($_POST['address_id'] ?? 0); // 0 = new address, >0 = edit existing
$label     = trim((string)($_POST['label'] ?? 'Home'));
$name      = trim((string)($_POST['name'] ?? ''));
$mobile    = trim((string)($_POST['mobile'] ?? ''));
$pin       = trim((string)($_POST['pincode'] ?? ($_POST['pin'] ?? '')));
$addrText  = trim((string)($_POST['address'] ?? ''));
$isDefault = !empty($_POST['is_default']);

if (mb_strlen($name) < 2) agi_json_fail(400, 'Please enter a valid name.');
if (!preg_match('/^[6-9]\d{9}$/', $mobile)) agi_json_fail(400, 'Enter a valid 10-digit mobile number.');
if (!preg_match('/^\d{6}$/', $pin)) agi_json_fail(400, 'Enter a valid 6-digit PIN code.');
if (mb_strlen($addrText) < 10) agi_json_fail(400, 'Please enter your full delivery address.');

// address_line1 is capped at 255 chars and NOT NULL — split overflow into
// address_line2 instead of truncating (losing part of the address).
$addressLine1 = mb_substr($addrText, 0, 255);
$addressLine2 = mb_strlen($addrText) > 255 ? mb_substr($addrText, 255, 255) : null;

// Map the free-text label the person typed to the closest enum value.
$labelLower = strtolower($label);
if (strpos($labelLower, 'farm') !== false) {
    $addressType = 'farm';
} elseif (strpos($labelLower, 'bill') !== false || strpos($labelLower, 'office') !== false || strpos($labelLower, 'work') !== false) {
    $addressType = 'billing';
} else {
    $addressType = 'delivery';
}

try {
    // If this is being set (or is the only address) as default, clear the
    // flag on the user's other addresses first so there's only ever one.
    if ($isDefault) {
        $clr = $conn->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?");
        $clr->bind_param("i", $userId);
        $clr->execute();
    }

    if ($addressId > 0) {
        // ---- Edit an existing address (must belong to this user) ----
        $stmt = $conn->prepare(
            "UPDATE user_addresses
             SET full_name = ?, phone = ?, pincode = ?, address_line1 = ?, address_line2 = ?,
                 address_type = ?, is_default = ?
             WHERE id = ? AND user_id = ?"
        );
        $isDefaultInt = $isDefault ? 1 : 0;
        $stmt->bind_param(
            "ssssssiii",
            $name, $mobile, $pin, $addressLine1, $addressLine2, $addressType, $isDefaultInt, $addressId, $userId
        );
        $stmt->execute();
        echo json_encode(['success' => true, 'id' => $addressId]);
        exit;
    }

    // ---- Create a new address ----
    $cnt = $conn->prepare("SELECT COUNT(*) c FROM user_addresses WHERE user_id = ?");
    $cnt->bind_param("i", $userId);
    $cnt->execute();
    $isFirstAddress = ((int)$cnt->get_result()->fetch_assoc()['c'] === 0);
    $isDefaultInt = ($isDefault || $isFirstAddress) ? 1 : 0;

    $stmt = $conn->prepare(
        "INSERT INTO user_addresses (user_id, address_type, full_name, phone, address_line1, address_line2, pincode, is_default)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        "issssssi",
        $userId, $addressType, $name, $mobile, $addressLine1, $addressLine2, $pin, $isDefaultInt
    );
    $stmt->execute();
    echo json_encode(['success' => true, 'id' => $conn->insert_id]);
} catch (\Throwable $e) {
    error_log('save_address.php failed: ' . $e->getMessage());
    agi_json_fail(500, 'Could not save address, please try again.');
}
