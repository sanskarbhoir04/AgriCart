<?php
// =====================================================================
// admin/community_action.php — Moderate Agri-Connect posts & comments.
// Actions:
//   toggle_post   (id, field='approve'|'pin')  — flips is_approved / is_pinned
//   delete_post   (id)                         — deletes a post + its comments
//   toggle_comment(id)                         — flips a comment's is_approved
//   delete_comment(id)                         — deletes a comment
//   add_comment   (post_id, author_name, body) — admin posts an official comment
// =====================================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
header('Content-Type: application/json');
include __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/agri_connect_schema.php';
agri_connect_bootstrap_schema($conn);
require_once __DIR__ . '/includes/permissions.php';

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

$action = trim($_POST['action'] ?? '');

// ---- Toggle a post's approve/pin flag ----
if ($action === 'toggle_post') {
    requirePermission('community.approve');
    $id    = (int)($_POST['id'] ?? 0);
    $field = trim($_POST['field'] ?? '');
    $columnMap = ['approve' => 'is_approved', 'pin' => 'is_pinned'];

    if ($id <= 0 || !isset($columnMap[$field])) {
        $response['error'] = 'Invalid id or field.';
        echo json_encode($response);
        exit;
    }
    $col = $columnMap[$field];
    $stmt = $conn->prepare("UPDATE community_posts SET {$col} = NOT {$col} WHERE id = ?");
    $stmt->bind_param("i", $id);
    $response['success'] = $stmt->execute();
    if (!$response['success']) {
        $response['error'] = 'Update failed.';
    } else {
        logAdminActivity('community_post_' . $field . '_toggled', 'community', $id, null, null, 'Toggled "' . $field . '" on community post #' . $id);
    }
    echo json_encode($response);
    exit;
}

// ---- Delete a post (and its comments) ----
if ($action === 'delete_post') {
    requirePermission('community.delete');
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        $response['error'] = 'Invalid id.';
        echo json_encode($response);
        exit;
    }
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE comments SET deleted_at = NOW() WHERE post_id = ? AND deleted_at IS NULL");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        $stmt = $conn->prepare("UPDATE community_posts SET deleted_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        $conn->commit();
        $response['success'] = true;
        logAdminActivity('community_post_deleted', 'community', $id, null, null, 'Deleted community post #' . $id . ' and its comments');
    } catch (\Throwable $e) {
        $conn->rollback();
        $response['error'] = 'Delete failed.';
    }
    echo json_encode($response);
    exit;
}

// ---- Restore a post (its comments stay as they were before delete) ----
if ($action === 'restore_post') {
    requirePermission('community.approve');
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        $response['error'] = 'Invalid id.';
        echo json_encode($response);
        exit;
    }
    $response['success'] = agri_restore($conn, 'community_posts', $id);
    if (!$response['success']) {
        $response['error'] = 'Restore failed.';
    } else {
        logAdminActivity('community_post_restored', 'community', $id, null, null, 'Restored community post #' . $id);
    }
    echo json_encode($response);
    exit;
}

// ---- Toggle a comment's approve flag ----
if ($action === 'toggle_comment') {
    requirePermission('community.moderate');
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        $response['error'] = 'Invalid id.';
        echo json_encode($response);
        exit;
    }
    $stmt = $conn->prepare("UPDATE comments SET is_approved = NOT is_approved WHERE id = ?");
    $stmt->bind_param("i", $id);
    $response['success'] = $stmt->execute();
    if (!$response['success']) {
        $response['error'] = 'Update failed.';
    } else {
        logAdminActivity('community_comment_moderated', 'community', $id, null, null, 'Toggled approval on comment #' . $id);
    }
    echo json_encode($response);
    exit;
}

// ---- Delete a comment ----
if ($action === 'delete_comment') {
    requirePermission('community.delete');
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        $response['error'] = 'Invalid id.';
        echo json_encode($response);
        exit;
    }
    $response['success'] = agri_soft_delete($conn, 'comments', $id);
    if (!$response['success']) {
        $response['error'] = 'Delete failed.';
    } else {
        logAdminActivity('community_comment_deleted', 'community', $id, null, null, 'Deleted comment #' . $id);
    }
    echo json_encode($response);
    exit;
}

// ---- Restore a comment ----
if ($action === 'restore_comment') {
    requirePermission('community.moderate');
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        $response['error'] = 'Invalid id.';
        echo json_encode($response);
        exit;
    }
    $response['success'] = agri_restore($conn, 'comments', $id);
    if (!$response['success']) {
        $response['error'] = 'Restore failed.';
    } else {
        logAdminActivity('community_comment_restored', 'community', $id, null, null, 'Restored comment #' . $id);
    }
    echo json_encode($response);
    exit;
}

// ---- Admin adds an official comment on a post ----
if ($action === 'add_comment') {
    requirePermission('community.moderate');
    $postId     = (int)($_POST['post_id'] ?? 0);
    $authorName = trim($_POST['author_name'] ?? '');
    $body       = trim($_POST['body'] ?? '');

    if ($postId <= 0 || $authorName === '' || $body === '') {
        $response['error'] = 'Post, author name and comment text are required.';
        echo json_encode($response);
        exit;
    }
    if (mb_strlen($body) > 2000) { $body = mb_substr($body, 0, 2000); }

    // verify post exists
    $stmt = $conn->prepare("SELECT id FROM community_posts WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $postId);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        $response['error'] = 'That post no longer exists.';
        echo json_encode($response);
        exit;
    }

    // The comments table normally only carries a user_id (FK to `users`).
    // Admin-authored comments aren't tied to a site user, so we add a
    // nullable author_name column the first time it's needed (one-time,
    // safe to run repeatedly) and use that to display the typed name.
    $hasAuthorNameCol = false;
    $colChk = $conn->query("SHOW COLUMNS FROM comments LIKE 'author_name'");
    if ($colChk && $colChk->num_rows > 0) {
        $hasAuthorNameCol = true;
    } else {
        if ($conn->query("ALTER TABLE comments ADD COLUMN author_name VARCHAR(150) NULL DEFAULT NULL AFTER user_id")) {
            $hasAuthorNameCol = true;
        }
    }

    // comments.user_id may be NOT NULL in some installs — if so, fall back
    // to a system "AgriCart Team" user instead of NULL.
    $userIdForComment = null;
    $colInfo = $conn->query("SHOW COLUMNS FROM comments LIKE 'user_id'");
    $userIdIsNullable = true;
    if ($colInfo && ($row = $colInfo->fetch_assoc())) {
        $userIdIsNullable = (strtoupper($row['Null']) === 'YES');
    }
    if (!$userIdIsNullable) {
        $teamStmt = $conn->prepare("SELECT id FROM users WHERE full_name = 'AgriCart Team' LIMIT 1");
        $teamStmt->execute();
        $teamRow = $teamStmt->get_result()->fetch_assoc();
        if ($teamRow) {
            $userIdForComment = (int)$teamRow['id'];
        } else {
            $sysEmail  = 'team@agricart.local';
            $sysMobile = '0000000000';
            $sysPass   = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            $insTeam   = $conn->prepare("INSERT INTO users (full_name, mobile, email, password, role) VALUES ('AgriCart Team', ?, ?, ?, 'admin')");
            $insTeam->bind_param("sss", $sysMobile, $sysEmail, $sysPass);
            if ($insTeam->execute()) {
                $userIdForComment = $conn->insert_id;
            } else {
                // Insert failed (e.g. unique mobile clash on re-run) — reuse existing row if present.
                $retry = $conn->query("SELECT id FROM users WHERE email = 'team@agricart.local' LIMIT 1");
                if ($retry && ($r = $retry->fetch_assoc())) { $userIdForComment = (int)$r['id']; }
            }
        }
    }

    if ($hasAuthorNameCol) {
        $stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, author_name, body, is_approved) VALUES (?, ?, ?, ?, 1)");
        $stmt->bind_param("iiss", $postId, $userIdForComment, $authorName, $body);
    } else {
        // Fallback if ALTER TABLE wasn't possible (e.g. restricted DB permissions):
        // still save the comment, just without the custom display name.
        $stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, body, is_approved) VALUES (?, ?, ?, 1)");
        $stmt->bind_param("iis", $postId, $userIdForComment, $body);
    }
    $response['success'] = $stmt->execute();
    if (!$response['success']) {
        $response['error'] = 'Add failed.';
    } else {
        logAdminActivity('community_comment_added', 'community', $postId, null, ['author' => $authorName], 'Posted an official comment as "' . $authorName . '" on post #' . $postId);
    }
    echo json_encode($response);
    exit;
}

$response['error'] = 'Unknown action.';
echo json_encode($response);
