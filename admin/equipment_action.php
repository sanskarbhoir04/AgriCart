<?php
// =====================================================================
// admin/equipment_action.php — Add / Update / Delete rental equipment.
// Every query uses prepared statements. Requires an active admin session.
// =====================================================================
include __DIR__ . '/../includes/db.php';
agri_session_start();
header('Content-Type: application/json');
include __DIR__ . '/../includes/agri_connect_functions.php';
require_once __DIR__ . '/includes/permissions.php';

$response = ['success' => false];

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    $response['error'] = 'Not authorized.';
    echo json_encode($response);
    exit;
}
csrf_require('json');

$action = $_POST['action'] ?? '';

if ($action === 'delete') {
    requirePermission('equipment.delete');
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { $response['error'] = 'Invalid equipment id.'; echo json_encode($response); exit; }
    $nStmt = $conn->prepare("SELECT name FROM equipment WHERE id = ?");
    $nStmt->bind_param("i", $id);
    $nStmt->execute();
    $eName = $nStmt->get_result()->fetch_assoc()['name'] ?? ('#' . $id);
    // Soft delete: hides from the Rental Hub (website) only. The row stays in
    // the admin panel, marked "Removed", so it can be restored or permanently
    // deleted later without losing the booking history tied to it.
    $stmt = $conn->prepare("UPDATE equipment SET availability = 0 WHERE id = ?");
    $stmt->bind_param("i", $id);
    $response['success'] = $stmt->execute();
    if (!$response['success']) {
        $response['error'] = 'Delete failed.';
    } else {
        logAdminActivity('equipment_deleted', 'equipment', $id, null, null, 'Removed equipment "' . $eName . '" from the website');
    }
    echo json_encode($response);
    exit;
}

if ($action === 'restore') {
    requirePermission('equipment.delete');
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { $response['error'] = 'Invalid equipment id.'; echo json_encode($response); exit; }
    $nStmt = $conn->prepare("SELECT name FROM equipment WHERE id = ?");
    $nStmt->bind_param("i", $id);
    $nStmt->execute();
    $eName = $nStmt->get_result()->fetch_assoc()['name'] ?? ('#' . $id);
    $stmt = $conn->prepare("UPDATE equipment SET availability = 1 WHERE id = ?");
    $stmt->bind_param("i", $id);
    $response['success'] = $stmt->execute();
    if (!$response['success']) {
        $response['error'] = 'Restore failed.';
    } else {
        logAdminActivity('equipment_restored', 'equipment', $id, null, null, 'Restored equipment "' . $eName . '"');
    }
    echo json_encode($response);
    exit;
}

if ($action === 'hard_delete') {
    requirePermission('equipment.delete');
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { $response['error'] = 'Invalid equipment id.'; echo json_encode($response); exit; }
    // Permanently removes the row — disappears from the admin panel too, not
    // just the website. Refuse if it has booking history so that history
    // doesn't end up pointing at a deleted equipment row; admin should use
    // the regular (soft) delete for equipment that's ever been booked.
    $bookingCount = 0;
    try {
        $bStmt = $conn->prepare("SELECT COUNT(*) AS c FROM equipment_bookings WHERE equipment_id = ?");
        $bStmt->bind_param("i", $id);
        $bStmt->execute();
        $bookingCount = (int)($bStmt->get_result()->fetch_assoc()['c'] ?? 0);
    } catch (\Throwable $e) {
        // equipment_bookings table/column missing — treat as no bookings.
    }
    if ($bookingCount > 0) {
        $response['error'] = "Can't permanently delete — this equipment has {$bookingCount} booking(s) on record. Use \"Remove from website\" instead so booking history stays intact.";
        echo json_encode($response);
        exit;
    }
    $nStmt = $conn->prepare("SELECT name FROM equipment WHERE id = ?");
    $nStmt->bind_param("i", $id);
    $nStmt->execute();
    $eName = $nStmt->get_result()->fetch_assoc()['name'] ?? ('#' . $id);
    $stmt = $conn->prepare("DELETE FROM equipment WHERE id = ?");
    $stmt->bind_param("i", $id);
    $response['success'] = $stmt->execute();
    if (!$response['success']) {
        error_log('admin/equipment_action.php: permanent delete failed: ' . $conn->error);
        $response['error'] = 'Delete failed. Please try again.';
    } else {
        logAdminActivity('equipment_permanently_deleted', 'equipment', $id, ['name' => $eName], null, 'Permanently deleted equipment "' . $eName . '"');
    }
    echo json_encode($response);
    exit;
}

if ($action === 'approve' || $action === 'reject') {
    requirePermission('equipment.approve');
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { $response['error'] = 'Invalid equipment id.'; echo json_encode($response); exit; }
    $status = $action === 'approve' ? 'approved' : 'rejected';
    try {
        $stmt = $conn->prepare("UPDATE equipment SET approval_status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        $response['success'] = $stmt->execute();
        if (!$response['success']) $response['error'] = 'Update failed.';

        // Let the owner know their listing was reviewed.
        if ($response['success']) {
            $eStmt = $conn->prepare("SELECT name, owner_user_id FROM equipment WHERE id = ?");
            $eStmt->bind_param("i", $id);
            $eStmt->execute();
            $eq = $eStmt->get_result()->fetch_assoc();
            logAdminActivity('equipment_' . $status, 'equipment', $id, null, null, ucfirst($status) . ' equipment "' . ($eq['name'] ?? ('#' . $id)) . '".');
            if ($eq && !empty($eq['owner_user_id'])) {
                if ($status === 'approved') {
                    agri_notify_user(
                        $conn, (int)$eq['owner_user_id'],
                        'Equipment listing approved',
                        'Your equipment "' . $eq['name'] . '" has been approved and is now live on the Rental Hub.',
                        'rental.php', 'market'
                    );
                } else {
                    agri_notify_user(
                        $conn, (int)$eq['owner_user_id'],
                        'Equipment listing rejected',
                        'Your equipment "' . $eq['name'] . '" was not approved by our team. Please review and resubmit.',
                        'list_equipment.php', 'market'
                    );
                }
            }
        }
    } catch (\Throwable $e) {
        $response['error'] = "approval_status column missing — run setup/farmer_selling_upgrade.sql first.";
    }
    echo json_encode($response);
    exit;
}

if ($action === 'save') {
    $id           = (int)($_POST['id'] ?? 0);
    requirePermission($id > 0 ? 'equipment.edit' : 'equipment.create');
    $name         = trim($_POST['name'] ?? '');
    $name_mr      = trim($_POST['name_mr'] ?? '');
    $name_hi      = trim($_POST['name_hi'] ?? '');
    $type         = trim($_POST['type'] ?? 'other');
    $pn           = trim($_POST['pn'] ?? '');
    $serialNo     = trim($_POST['serial_no'] ?? '');
    $rentPerDay   = (float)($_POST['rent_per_day'] ?? 0);
    $hp           = trim($_POST['hp'] ?? '');
    $engine       = trim($_POST['engine'] ?? '');
    $gears        = trim($_POST['gears'] ?? '');
    $lift         = trim($_POST['lift_capacity'] ?? '');
    $ownerName    = trim($_POST['owner_name'] ?? '');
    $ownerPhone   = trim($_POST['owner_phone'] ?? '');
    $cityName     = trim($_POST['city'] ?? '');
    $availability = (int)($_POST['availability'] ?? 1);
    $image        = trim($_POST['image'] ?? '');
    $description  = trim($_POST['description'] ?? '');

    $brand        = trim($_POST['brand'] ?? '');
    $model        = trim($_POST['model'] ?? '');
    $condition    = in_array(($_POST['equipment_condition'] ?? 'good'), ['excellent','good','average'], true) ? $_POST['equipment_condition'] : 'good';
    $securityDeposit = (float)($_POST['security_deposit'] ?? 0);
    $operatorAvailable  = !empty($_POST['operator_available']) ? 1 : 0;
    $fuelIncluded       = !empty($_POST['fuel_included']) ? 1 : 0;
    $transportAvailable = !empty($_POST['transport_available']) ? 1 : 0;
    $transportCharge    = (float)($_POST['transport_charge'] ?? 0);
    $ownerEmail   = trim($_POST['owner_email'] ?? '');
    $ownerDistrict= trim($_POST['owner_district'] ?? '');
    $ownerAddress = trim($_POST['owner_address'] ?? '');
    $rentalRules  = trim($_POST['rental_rules'] ?? '');

    if ($name === '' || $rentPerDay <= 0) {
        $response['error'] = 'Equipment name and a valid rent/day are required.';
        echo json_encode($response);
        exit;
    }

    // Resolve city name to a cities.id, creating the city if it doesn't exist yet.
    $cityId = null;
    if ($cityName !== '') {
        $stmt = $conn->prepare("SELECT id FROM cities WHERE name = ? LIMIT 1");
        $stmt->bind_param("s", $cityName);
        $stmt->execute();
        $cityRow = $stmt->get_result()->fetch_assoc();
        if ($cityRow) {
            $cityId = (int)$cityRow['id'];
        } else {
            $stmt = $conn->prepare("INSERT INTO cities (name) VALUES (?)");
            $stmt->bind_param("s", $cityName);
            if ($stmt->execute()) { $cityId = $conn->insert_id; }
        }
    }

    // Try the richer column set first (translation + rental/owner details);
    // fall back to the original column set if the migration hasn't run yet
    // on this database — same defensive pattern used across AgriCart.
    $ok = false;
    $extendedColsMissing = false;
    try {
        if ($id > 0) {
            $stmt = $conn->prepare(
                "UPDATE equipment SET name=?, name_mr=?, name_hi=?, type=?, pn=?, serial_no=?, rent_per_day=?, hp=?, engine=?, gears=?, lift_capacity=?, description=?, owner_name=?, owner_phone=?, city_id=?, availability=?, image=?, brand=?, model=?, equipment_condition=?, security_deposit=?, operator_available=?, fuel_included=?, transport_available=?, transport_charge=?, owner_email=?, owner_district=?, owner_address=?, rental_rules=? WHERE id=?"
            );
            $stmt->bind_param(
                "ssssssdsssssssiissssdiiidssssi",
                $name, $name_mr, $name_hi, $type, $pn, $serialNo, $rentPerDay, $hp, $engine, $gears, $lift, $description, $ownerName, $ownerPhone, $cityId, $availability, $image,
                $brand, $model, $condition, $securityDeposit, $operatorAvailable, $fuelIncluded, $transportAvailable, $transportCharge, $ownerEmail, $ownerDistrict, $ownerAddress, $rentalRules, $id
            );
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO equipment (name, name_mr, name_hi, type, pn, serial_no, rent_per_day, hp, engine, gears, lift_capacity, description, owner_name, owner_phone, city_id, availability, image, brand, model, equipment_condition, security_deposit, operator_available, fuel_included, transport_available, transport_charge, owner_email, owner_district, owner_address, rental_rules, owner_verified) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 0)"
            );
            $stmt->bind_param(
                "ssssssdsssssssiissssdiiidssss",
                $name, $name_mr, $name_hi, $type, $pn, $serialNo, $rentPerDay, $hp, $engine, $gears, $lift, $description, $ownerName, $ownerPhone, $cityId, $availability, $image,
                $brand, $model, $condition, $securityDeposit, $operatorAvailable, $fuelIncluded, $transportAvailable, $transportCharge, $ownerEmail, $ownerDistrict, $ownerAddress, $rentalRules
            );
        }
        $ok = $stmt->execute();
        if (!$ok) { $extendedColsMissing = true; }
    } catch (\Throwable $eCol) {
        $extendedColsMissing = true;
    }

    if ($extendedColsMissing) {
        // Fall back to the original (pre-upgrade) column set.
        if ($id > 0) {
            $stmt = $conn->prepare(
                "UPDATE equipment SET name=?, name_mr=?, type=?, pn=?, serial_no=?, rent_per_day=?, hp=?, engine=?, gears=?, lift_capacity=?, description=?, owner_name=?, owner_phone=?, city_id=?, availability=?, image=? WHERE id=?"
            );
            $stmt->bind_param(
                "sssssdsssssssiisi",
                $name, $name_mr, $type, $pn, $serialNo, $rentPerDay, $hp, $engine, $gears, $lift, $description, $ownerName, $ownerPhone, $cityId, $availability, $image, $id
            );
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO equipment (name, name_mr, type, pn, serial_no, rent_per_day, hp, engine, gears, lift_capacity, description, owner_name, owner_phone, city_id, availability, image, owner_verified) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 0)"
            );
            $stmt->bind_param(
                "sssssdsssssssiis",
                $name, $name_mr, $type, $pn, $serialNo, $rentPerDay, $hp, $engine, $gears, $lift, $description, $ownerName, $ownerPhone, $cityId, $availability, $image
            );
        }
        $ok = $stmt->execute();
    }

    if ($ok) {
        $response['success'] = true;
        $newId = $id > 0 ? $id : $conn->insert_id;
        $response['id'] = $newId;
        $summary = ['name' => $name, 'rent_per_day' => $rentPerDay, 'availability' => (bool)$availability];
        if ($id > 0) {
            logAdminActivity('equipment_updated', 'equipment', $newId, null, $summary, 'Updated equipment "' . $name . '"');
        } else {
            logAdminActivity('equipment_created', 'equipment', $newId, null, $summary, 'Added equipment "' . $name . '"');
        }
        if ($extendedColsMissing) {
            $response['note'] = "Some fields (Hindi name, brand, model, condition, deposit, owner details) weren't saved — run setup/list_equipment_upgrade.sql on this database, then save again.";
        }
    } else {
        error_log('admin/equipment_action.php: save failed: ' . $conn->error);
        $response['error'] = 'Save failed. Please try again.';
    }
    echo json_encode($response);
    exit;
}

if ($action === 'get_images') {
    requirePermission('equipment.view');
    $id = (int)($_POST['id'] ?? 0);
    $images = [];
    if ($id > 0) {
        try {
            $imgStmt = $conn->prepare("SELECT image_path FROM equipment_images WHERE equipment_id = ? ORDER BY sort_order ASC, id ASC");
            $imgStmt->bind_param("i", $id);
            $imgStmt->execute();
            $res = $imgStmt->get_result();
            while ($row = $res->fetch_assoc()) { $images[] = $row['image_path']; }
        } catch (\Throwable $e) {
            // equipment_images table not created yet — return empty list.
        }
    }
    $response['success'] = true;
    $response['images'] = $images;
    echo json_encode($response);
    exit;
}

if ($action === 'get_documents') {
    requirePermission('equipment.view');
    $id = (int)($_POST['id'] ?? 0);
    $documents = [];
    if ($id > 0) {
        try {
            $docStmt = $conn->prepare("SELECT doc_path, doc_name FROM equipment_documents WHERE equipment_id = ? ORDER BY id ASC");
            $docStmt->bind_param("i", $id);
            $docStmt->execute();
            $res = $docStmt->get_result();
            while ($row = $res->fetch_assoc()) { $documents[] = $row; }
        } catch (\Throwable $e) {
            // equipment_documents table not created yet — return empty list.
        }
    }
    $response['success'] = true;
    $response['documents'] = $documents;
    echo json_encode($response);
    exit;
}

$response['error'] = 'Unknown action.';
echo json_encode($response);
