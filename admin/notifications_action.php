<?php
// =====================================================================
// admin/notifications_action.php — backend for the Notification Center
// bell (admin/includes/team_layout_top.php). Read-only list + mark-read,
// no destructive actions, so no elevated permission is required beyond
// being a logged-in admin (same as the bell being visible to every admin
// regardless of role — everyone should see "New Order", "New Seller" etc).
// =====================================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
header('Content-Type: application/json');
include __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/admin_notifications_schema.php';
admin_notif_bootstrap_schema($conn);

$response = ['success' => false];

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    $response['error'] = 'Not authorized.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && function_exists('csrf_require')) {
    csrf_require('json');
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

switch ($action) {
    case 'list': {
        $limit = 20;
        $rows = [];
        $res = $conn->query("SELECT id, type, title, message, link, is_read, created_at FROM admin_notifications ORDER BY created_at DESC LIMIT $limit");
        if ($res) { while ($r = $res->fetch_assoc()) { $rows[] = $r; } }
        $unreadRes = $conn->query("SELECT COUNT(*) c FROM admin_notifications WHERE is_read = 0");
        $unread = $unreadRes ? (int)($unreadRes->fetch_assoc()['c'] ?? 0) : 0;
        echo json_encode(['success' => true, 'notifications' => $rows, 'unread_count' => $unread]);
        exit;
    }

    case 'mark_read': {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['success' => false, 'error' => 'Invalid id.']); exit; }
        $stmt = $conn->prepare("UPDATE admin_notifications SET is_read = 1, read_at = NOW() WHERE id = ?");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        echo json_encode(['success' => (bool)$ok]);
        exit;
    }

    case 'mark_all_read': {
        $ok = $conn->query("UPDATE admin_notifications SET is_read = 1, read_at = NOW() WHERE is_read = 0");
        echo json_encode(['success' => (bool)$ok]);
        exit;
    }

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Unknown action.']);
        exit;
}
