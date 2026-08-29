<?php
// =====================================================================
// admin/account_action.php — Accounts Management actions:
//   - set_status : Activate / Suspend / Block / Deactivate an account
//                  (reason required for anything except Activate; always
//                  written to the audit log, per spec section 9 & 11).
//   - verify     : Mark an account's core verification as Verified.
//
// Works across all four account types by delegating to the underlying
// table each one is really backed by:
//   buyer/seller -> users.status / users.deleted_at
//   company      -> sellers.account_status / sellers.deleted_at
//   employee     -> admin_team_members.status
// =====================================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
header('Content-Type: application/json');
include __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/accounts_schema.php';
require_once __DIR__ . '/../includes/gstin_schema.php';
require_once __DIR__ . '/../includes/gst_sync.php';
require_once __DIR__ . '/includes/permissions.php';
accounts_bootstrap_schema($conn);
gstin_bootstrap_schema($conn);
gst_sync_bootstrap_schema($conn);

$response = ['success' => false];

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    $response['error'] = 'Not authorized.';
    echo json_encode($response);
    exit;
}
csrf_require('json');

$action = $_POST['action'] ?? '';
$type   = $_POST['type'] ?? '';
$id     = (int)($_POST['id'] ?? 0);

$validTypes = ['buyer', 'seller', 'company', 'employee'];
if ($id <= 0 || !in_array($type, $validTypes, true)) {
    $response['error'] = 'Invalid account.';
    echo json_encode($response);
    exit;
}

/** Fetches a display name + current status for the activity-log line. */
function acc_lookup(mysqli $conn, string $type, int $id): array
{
    if ($type === 'buyer' || $type === 'seller') {
        $s = $conn->prepare("SELECT full_name AS name, status, deleted_at FROM users WHERE id = ? LIMIT 1");
    } elseif ($type === 'company') {
        $s = $conn->prepare("SELECT name, account_status AS status, deleted_at FROM sellers WHERE id = ? LIMIT 1");
    } else {
        $s = $conn->prepare("SELECT u.full_name AS name, tm.status, NULL AS deleted_at FROM admin_team_members tm JOIN users u ON u.id = tm.user_id WHERE tm.id = ? LIMIT 1");
    }
    $s->bind_param('i', $id);
    $s->execute();
    return $s->get_result()->fetch_assoc() ?: ['name' => '#' . $id, 'status' => null, 'deleted_at' => null];
}

if ($action === 'set_status') {
    requirePermission('accounts.manage');

    $status = trim($_POST['status'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $validStatuses = ['active', 'suspended', 'blocked', 'deactivated', 'pending_verification'];
    if (!in_array($status, $validStatuses, true)) {
        $response['error'] = 'Invalid status.';
        echo json_encode($response);
        exit;
    }
    if ($status !== 'active' && $reason === '') {
        $response['error'] = 'A reason is required for this action.';
        echo json_encode($response);
        exit;
    }
    if ($type === 'employee' && $status === 'deactivated') {
        $response['error'] = 'Use Team Management to remove an employee account.';
        echo json_encode($response);
        exit;
    }

    $old = acc_lookup($conn, $type, $id);
    $oldLabel = !empty($old['deleted_at']) ? 'Deactivated' : ucfirst((string)($old['status'] ?: 'active'));
    $newLabel = ucfirst($status);
    $adminName = $_SESSION['admin_name'] ?? 'Admin';

    $ok = false;
    if ($type === 'buyer' || $type === 'seller') {
        if ($status === 'deactivated') {
            $stmt = $conn->prepare("UPDATE users SET deleted_at = NOW(), status_reason = ?, status_changed_at = NOW() WHERE id = ?");
            $stmt->bind_param('si', $reason, $id);
        } else {
            $dbStatus = $status; // active/suspended/blocked/pending_verification all map 1:1 onto users.status
            $stmt = $conn->prepare("UPDATE users SET status = ?, status_reason = ?, status_changed_at = NOW(), deleted_at = NULL WHERE id = ?");
            $stmt->bind_param('ssi', $dbStatus, $reason, $id);
        }
        $ok = $stmt->execute();
    } elseif ($type === 'company') {
        if ($status === 'deactivated') {
            $stmt = $conn->prepare("UPDATE sellers SET deleted_at = NOW(), status_reason = ? WHERE id = ?");
            $stmt->bind_param('si', $reason, $id);
        } else {
            $dbStatus = $status;
            $stmt = $conn->prepare("UPDATE sellers SET account_status = ?, status_reason = ?, deleted_at = NULL WHERE id = ?");
            $stmt->bind_param('ssi', $dbStatus, $reason, $id);
        }
        $ok = $stmt->execute();
    } else { // employee
        $dbStatus = $status === 'pending_verification' ? 'active' : $status; // employees don't use a pending-verification state
        $stmt = $conn->prepare("UPDATE admin_team_members SET status = ?, status_reason = ? WHERE id = ?");
        $stmt->bind_param('ssi', $dbStatus, $reason, $id);
        $ok = $stmt->execute();
    }

    $response['success'] = $ok;
    if (!$ok) {
        error_log('admin/account_action.php: status update failed: ' . $conn->error);
        $response['error'] = 'Update failed. Please try again.';
    } else {
        $desc = 'Admin ' . $adminName . ' → ' . acc_type_label($type) . ' "' . $old['name'] . '" status changed → ' . $oldLabel . ' → ' . $newLabel . ($reason ? (' — Reason: ' . $reason) : '');
        logAdminActivity(
            $type . '_status_changed',
            $type === 'employee' ? 'team' : ($type . 's'),
            $id,
            $oldLabel,
            $newLabel,
            $desc
        );
    }
    echo json_encode($response);
    exit;
}

if ($action === 'verify') {
    requirePermission('accounts.verify');

    $old = acc_lookup($conn, $type, $id);
    $ok = false;
    if ($type === 'buyer' || $type === 'seller') {
        $stmt = $conn->prepare("UPDATE users SET email_verified = 1, mobile_verified = 1, is_verified = 1 WHERE id = ?");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
    } elseif ($type === 'company') {
        $adminUserId = $_SESSION['admin_user_id'] ?? $_SESSION['admin_id'] ?? null;
        $sets = ["verified = 1", "gst_verified = 1", "business_verified = 1"];
        if (gstsync_col_exists($conn, 'sellers', 'gst_verified_status')) { $sets[] = "gst_verified_status = 'verified'"; }
        if (gstsync_col_exists($conn, 'sellers', 'gst_verified_at')) { $sets[] = "gst_verified_at = NOW()"; }
        if (gstsync_col_exists($conn, 'sellers', 'gst_verified_by')) { $sets[] = "gst_verified_by = " . (int)$adminUserId; }
        $stmt = $conn->prepare("UPDATE sellers SET " . implode(', ', $sets) . " WHERE id = ?");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        if ($ok) {
            // Push the same "Verified" status onto the linked seller
            // login account's own GST profile — this is the record the
            // Seller Dashboard actually reads (seller_payout_profiles),
            // which is a different table from `sellers` (Companies
            // directory). Without this push, Admin shows Verified while
            // the Seller Dashboard keeps showing "Not Verified".
            gst_sync_push_company_verified_to_seller($conn, $id, true, $adminUserId);
        }
    } else {
        $response['error'] = 'Employees are verified through Team Management role assignment.';
        echo json_encode($response);
        exit;
    }

    $response['success'] = $ok;
    if (!$ok) {
        error_log('admin/account_action.php: verification update failed: ' . $conn->error);
        $response['error'] = 'Update failed. Please try again.';
    } else {
        logAdminActivity($type . '_verified', $type . 's', $id, 'Pending', 'Verified', 'Verified ' . acc_type_label($type) . ' "' . $old['name'] . '"');
    }
    echo json_encode($response);
    exit;
}

$response['error'] = 'Unknown action.';
echo json_encode($response);
