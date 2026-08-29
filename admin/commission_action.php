<?php
// =====================================================================
// admin/commission_action.php — CRUD for Commission Management
// (admin/commission.php). Requires an active admin session + the
// 'finance.commission' permission, and a valid CSRF token on every POST,
// same pattern as admin/company_action.php.
// =====================================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
header('Content-Type: application/json');
include __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/../includes/commission_schema.php';
commission_bootstrap_schema($conn);

$response = ['success' => false];

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    $response['error'] = 'Not authorized.';
    echo json_encode($response);
    exit;
}

requirePermission('finance.commission');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && function_exists('csrf_require')) {
    csrf_require('json');
}

$action = $_POST['action'] ?? '';

function comm_num($v, $default = null) {
    if ($v === null || $v === '') return $default;
    return is_numeric($v) ? (float)$v : $default;
}

switch ($action) {

    // ---------------- Global default ----------------
    case 'save_global': {
        $type = $_POST['commission_type'] ?? 'percentage';
        if (!in_array($type, ['percentage', 'fixed', 'percentage_plus_fixed'], true)) { $type = 'percentage'; }
        $percent = (float) comm_num($_POST['default_percent'] ?? null, 0);
        $fixed   = (float) comm_num($_POST['default_fixed_amount'] ?? null, 0);
        $min     = (float) comm_num($_POST['min_commission'] ?? null, 0);
        $maxRaw  = comm_num($_POST['max_commission'] ?? null, null);
        $status  = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
        $effFrom = $_POST['effective_from'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $effFrom)) { $effFrom = date('Y-m-d'); }

        if ($percent < 0 || $percent > 100) {
            $response['error'] = 'Commission percent must be between 0 and 100.';
            echo json_encode($response); exit;
        }
        if ($maxRaw !== null && $maxRaw < $min) {
            $response['error'] = 'Maximum commission cannot be less than minimum.';
            echo json_encode($response); exit;
        }

        // Single-row "current settings" model: update the existing row rather
        // than accumulating a history table, since the spec asks for one
        // editable global default, not a version log.
        $existing = $conn->query("SELECT id FROM commission_settings ORDER BY effective_from DESC, id DESC LIMIT 1");
        $row = $existing ? $existing->fetch_assoc() : null;
        $updatedBy = (int)($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0);

        if ($row) {
            $stmt = $conn->prepare("UPDATE commission_settings SET commission_type=?, default_percent=?, default_fixed_amount=?, min_commission=?, max_commission=?, status=?, effective_from=?, updated_by=? WHERE id=?");
            $stmt->bind_param('sdddsssii', $type, $percent, $fixed, $min, $maxRaw, $status, $effFrom, $updatedBy, $row['id']);
            $ok = $stmt->execute();
        } else {
            $stmt = $conn->prepare("INSERT INTO commission_settings (commission_type, default_percent, default_fixed_amount, min_commission, max_commission, status, effective_from, updated_by) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->bind_param('sdddsssi', $type, $percent, $fixed, $min, $maxRaw, $status, $effFrom, $updatedBy);
            $ok = $stmt->execute();
        }
        $response['success'] = (bool)$ok;
        if (!$ok) { $response['error'] = 'Could not save global commission.'; }
        echo json_encode($response);
        exit;
    }

    // ---------------- Category overrides ----------------
    case 'save_category': {
        $category = trim($_POST['category'] ?? '');
        $percent  = (float) comm_num($_POST['commission_percent'] ?? null, null);
        $status   = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

        if ($category === '' || $percent < 0 || $percent > 100) {
            $response['error'] = 'Please select a category and a valid commission percent (0-100).';
            echo json_encode($response); exit;
        }

        $stmt = $conn->prepare("
            INSERT INTO category_commission (category, commission_percent, status)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE commission_percent = VALUES(commission_percent), status = VALUES(status)
        ");
        $stmt->bind_param('sds', $category, $percent, $status);
        $ok = $stmt->execute();
        $response['success'] = (bool)$ok;
        if (!$ok) { $response['error'] = 'Could not save category commission.'; }
        echo json_encode($response);
        exit;
    }

    case 'delete_category': {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { $response['error'] = 'Invalid id.'; echo json_encode($response); exit; }
        $stmt = $conn->prepare("DELETE FROM category_commission WHERE id = ?");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $response['success'] = (bool)$ok;
        if (!$ok) { $response['error'] = 'Could not remove override.'; }
        echo json_encode($response);
        exit;
    }

    // ---------------- Seller overrides ----------------
    case 'save_seller': {
        $userId  = (int)($_POST['user_id'] ?? 0);
        $percent = (float) comm_num($_POST['commission_percent'] ?? null, null);
        $status  = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

        if ($userId <= 0 || $percent < 0 || $percent > 100) {
            $response['error'] = 'Please select a seller and a valid commission percent (0-100).';
            echo json_encode($response); exit;
        }

        // Confirm this really is a known seller before attaching a
        // commission override to it (defense against a tampered id).
        // NOTE: AgriCart's real seller directory is the `sellers` table
        // (see admin/seller_action.php / the Sellers tab) — sellers here
        // are name/mobile records, not necessarily `users` accounts with
        // role='seller', so we validate against `sellers`, not `users`.
        $chk = $conn->prepare("SELECT id FROM sellers WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        $chk->bind_param('i', $userId);
        $chk->execute();
        if ($chk->get_result()->num_rows === 0) {
            $response['error'] = 'Selected seller was not found.';
            echo json_encode($response); exit;
        }

        $stmt = $conn->prepare("
            INSERT INTO seller_commission (user_id, commission_percent, status)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE commission_percent = VALUES(commission_percent), status = VALUES(status)
        ");
        $stmt->bind_param('ids', $userId, $percent, $status);
        $ok = $stmt->execute();
        $response['success'] = (bool)$ok;
        if (!$ok) { $response['error'] = 'Could not save seller commission.'; }
        echo json_encode($response);
        exit;
    }

    case 'delete_seller': {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { $response['error'] = 'Invalid id.'; echo json_encode($response); exit; }
        $stmt = $conn->prepare("DELETE FROM seller_commission WHERE id = ?");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $response['success'] = (bool)$ok;
        if (!$ok) { $response['error'] = 'Could not remove override.'; }
        echo json_encode($response);
        exit;
    }

    default:
        http_response_code(400);
        $response['error'] = 'Unknown action.';
        echo json_encode($response);
        exit;
}
