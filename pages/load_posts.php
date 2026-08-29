<?php
// =====================================================
// AgriCart — Agri-Connect: paginated/filtered/sorted post feed (AJAX)
// GET only (read-only, no CSRF needed). Used for "Load More" and for
// server-side search/filter/sort across ALL posts, not just the first page.
// =====================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
include __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/agri_connect_schema.php';
include __DIR__ . '/../includes/agri_connect_functions.php';
agri_connect_bootstrap_schema($conn);

header('Content-Type: application/json');

$_doc_root = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']));
$_this_dir = str_replace('\\', '/', realpath(dirname(dirname(__FILE__))));
$base_path  = rtrim(str_replace($_doc_root, '', $_this_dir), '/');

$currentUserId   = $_SESSION['user_id'] ?? null;
$isLoggedIn      = $currentUserId !== null;
$currentUserRole = null;
if ($isLoggedIn) {
    $r = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
    $r->bind_param("i", $currentUserId);
    $r->execute();
    $row = $r->get_result()->fetch_assoc();
    $currentUserRole = $row['role'] ?? null;
}

$opts = [
    'limit'  => (int)($_GET['limit'] ?? 6),
    'offset' => (int)($_GET['offset'] ?? 0),
    'filter' => $_GET['filter'] ?? 'all',
    'search' => $_GET['search'] ?? '',
    'sort'   => $_GET['sort'] ?? 'latest',
];

$result = agri_fetch_posts($conn, $opts, $currentUserId);

$ctx = [
    'isLoggedIn' => $isLoggedIn, 'currentUserId' => $currentUserId,
    'currentUserRole' => $currentUserRole, 'basePath' => $base_path,
];

$html = '';
foreach ($result['posts'] as $post) { $html .= agri_render_post_card($post, $ctx); }
if (empty($result['posts'])) { $html = ''; }

echo json_encode(['success' => true, 'html' => $html, 'has_more' => $result['has_more'], 'count' => count($result['posts'])]);
