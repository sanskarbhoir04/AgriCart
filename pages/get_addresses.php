<?php
// =====================================================
// AgriCart — Address Book: list addresses (AJAX, JSON)
//
// CONFIRMED live schema for `user_addresses` (via phpMyAdmin structure
// view): id, user_id, address_type enum('delivery','farm','billing'),
// full_name, phone, address_line1 (NOT NULL), address_line2, city,
// district, state, pincode, is_default, created_at.
//
// The checkout JS (marketplace.php) expects each address as
// {id, label, name, mobile, pincode, address, is_default} — so this
// combines address_line1 + address_line2 into one `address` string and
// turns address_type into a friendly label ('delivery' -> "Home", since
// that's what most people actually mean by their default address).
// =====================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
include __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Please log in first.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$addresses = [];

$typeLabels = ['delivery' => 'Home', 'farm' => 'Farm', 'billing' => 'Billing'];

try {
    $stmt = $conn->prepare(
        "SELECT id, address_type, full_name, phone, address_line1, address_line2, pincode, is_default
         FROM user_addresses WHERE user_id = ?
         ORDER BY is_default DESC, id DESC"
    );
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $fullAddress = trim($row['address_line1'] . (!empty($row['address_line2']) ? ', ' . $row['address_line2'] : ''));
        $addresses[] = [
            'id'         => (int)$row['id'],
            'label'      => $typeLabels[$row['address_type']] ?? 'Address',
            'name'       => $row['full_name'],
            'mobile'     => $row['phone'],
            'pincode'    => $row['pincode'],
            'address'    => $fullAddress,
            'is_default' => (bool)$row['is_default'],
        ];
    }
} catch (\Throwable $e) {
    // Fail safe with an empty list rather than a fatal error, so checkout
    // still works (it falls back to the single saved_* address / blank
    // fields) even if something about the table changes again later.
    error_log('AgriCart get_addresses: ' . $e->getMessage());
    echo json_encode(['success' => true, 'addresses' => []]);
    exit;
}

echo json_encode(['success' => true, 'addresses' => $addresses]);
