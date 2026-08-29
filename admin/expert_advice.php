<?php
// =====================================================================
// admin/expert_advice.php — Manage "Today's Expert Advice" shown on the
// Agri-Connect page's Expert Advice Corner. Standalone page (not wired
// into the main admin/index.php tab dashboard yet) — open it directly
// as admin/expert_advice.php while logged in as admin.
// =====================================================================
include __DIR__ . '/includes/admin_guard.php';
include __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/agri_connect_schema.php';
agri_connect_bootstrap_schema($conn);
requirePermission('advisory.approve');

$errors = [];
$success = '';

// ── Handle form submit (add new advice) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $expertUserId = (int)($_POST['expert_user_id'] ?? 0);
    $crop         = trim($_POST['crop'] ?? '');
    $advice       = trim($_POST['advice'] ?? '');

    if ($expertUserId <= 0) { $errors[] = 'कृपया तज्ज्ञ निवडा.'; }
    if ($advice === '')     { $errors[] = 'कृपया सल्ला मजकूर लिहा.'; }

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO expert_advice (expert_user_id, crop, advice, is_active) VALUES (?, ?, ?, 1)");
        $stmt->bind_param("iss", $expertUserId, $crop, $advice);
        if ($stmt->execute()) {
            $success = 'सल्ला यशस्वीरित्या जोडला गेला.';
            logAdminActivity('expert_advice_added', 'advisory', $conn->insert_id, null, ['crop' => $crop], 'Added expert advice for crop "' . $crop . '"');
        } else {
            $errors[] = 'सेव्ह करताना अडचण आली.';
        }
    }
}

// ── Handle toggle active / delete ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $conn->query("UPDATE expert_advice SET is_active = NOT is_active WHERE id = " . $id);
        logAdminActivity('expert_advice_toggled', 'advisory', $id, null, null, 'Toggled active status on expert advice #' . $id);
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        agri_soft_delete($conn, 'expert_advice', $id);
        logAdminActivity('expert_advice_deleted', 'advisory', $id, null, null, 'Deleted expert advice #' . $id);
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'restore') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) { agri_restore($conn, 'expert_advice', $id); }
}

// ── Data for the page ──
$experts = [];
$expRes = $conn->query("SELECT id, full_name, qualification FROM users WHERE role IN ('expert','admin') ORDER BY full_name ASC");
if ($expRes) { while ($row = $expRes->fetch_assoc()) { $experts[] = $row; } }

$adviceList = [];
$advRes = $conn->query(
    "SELECT ea.*, u.full_name FROM expert_advice ea JOIN users u ON u.id = ea.expert_user_id ORDER BY ea.id DESC LIMIT 50"
);
if ($advRes) { while ($row = $advRes->fetch_assoc()) { $adviceList[] = $row; } }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Expert Advice Corner — Admin | AgriCart</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
    body { font-family: 'Segoe UI', sans-serif; background: #f0f4f1; margin: 0; padding: 30px; color: #222; }
    .wrap { max-width: 900px; margin: 0 auto; }
    h1 { font-size: 22px; color: #0b1c14; margin-bottom: 4px; }
    .sub { color: #666; font-size: 14px; margin-bottom: 25px; }
    .card { background: #fff; border-radius: 10px; padding: 24px; box-shadow: 0 4px 18px rgba(0,0,0,0.06); margin-bottom: 25px; }
    label { display: block; font-size: 13px; font-weight: 600; color: #333; margin-bottom: 6px; margin-top: 14px; }
    select, textarea, input[type=text] { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; box-sizing: border-box; font-family: inherit; }
    textarea { height: 90px; resize: vertical; }
    button.primary { margin-top: 18px; background: #2e8b57; color: #fff; border: none; padding: 10px 24px; border-radius: 20px; font-weight: 600; cursor: pointer; }
    button.primary:hover { background: #246b43; }
    .msg-ok { background: #e8f5e9; color: #1b5e20; padding: 10px 14px; border-radius: 6px; margin-bottom: 14px; font-size: 14px; }
    .msg-err { background: #ffebee; color: #b71c1c; padding: 10px 14px; border-radius: 6px; margin-bottom: 14px; font-size: 14px; }
    table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
    th, td { text-align: left; padding: 10px; border-bottom: 1px solid #eee; vertical-align: top; }
    th { color: #666; font-weight: 600; }
    .tag-active { background: #e8f5e9; color: #1b5e20; padding: 2px 9px; border-radius: 10px; font-size: 11.5px; font-weight: 600; }
    .tag-deleted { background: #ffebee; color: #b71c1c; padding: 2px 9px; border-radius: 10px; font-size: 11.5px; font-weight: 600; }
    .row-actions .restore-btn { color: #2e7d32; }
    .tag-inactive { background: #f5f5f5; color: #888; padding: 2px 9px; border-radius: 10px; font-size: 11.5px; font-weight: 600; }
    .row-actions button { border: none; background: none; cursor: pointer; font-size: 13px; padding: 4px 8px; border-radius: 4px; }
    .row-actions .toggle-btn { color: #1565c0; }
    .row-actions .delete-btn { color: #c62828; }
    a.back-link { color: #2e8b57; text-decoration: none; font-size: 13px; }
    .tbl-wrap { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
    @media (max-width: 768px) {
        body { padding: 16px; }
        .wrap { max-width: 100%; }
        h1 { font-size: 19px; }
        .card { padding: 16px; border-radius: 10px; }
        table { min-width: 560px; }
        th, td { padding: 8px; font-size: 12.5px; }
        button.primary, .row-actions button { min-height: 40px; }
        select, textarea, input[type=text] { font-size: 16px; }
    }
    @media (max-width: 380px) {
        body { padding: 12px; }
        h1 { font-size: 17px; }
    }
</style>
</head>
<body>
<div class="wrap">
    <a href="index.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Admin Dashboard</a>
    <h1>Expert Advice Corner</h1>
    <p class="sub">Agri-Connect पेजवर दिसणारा "Today's Expert Advice" इथून व्यवस्थापित करा. फक्त role = expert/admin असलेले users इथे निवडता येतात.</p>

    <?php if ($success): ?><div class="msg-ok"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php foreach ($errors as $e): ?><div class="msg-err"><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?>

    <div class="card">
        <h3 style="margin-top:0;">नवीन सल्ला जोडा</h3>
        <?php if (empty($experts)): ?>
            <p style="color:#b71c1c;">अजून कोणताही expert/admin user नाही. आधी Users tab मधून एखाद्या user ला role = 'expert' द्या.</p>
        <?php else: ?>
        <form method="post">
            <input type="hidden" name="action" value="add">
            <label>तज्ज्ञ (Expert)</label>
            <select name="expert_user_id" required>
                <option value="">-- Select Expert --</option>
                <?php foreach ($experts as $ex): ?>
                    <option value="<?php echo (int)$ex['id']; ?>">
                        <?php echo htmlspecialchars($ex['full_name']) . ($ex['qualification'] ? ' — ' . htmlspecialchars($ex['qualification']) : ''); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label>पीक (Crop) — ऐच्छिक</label>
            <input type="text" name="crop" placeholder="उदा. द्राक्ष, कापूस, सोयाबीन">
            <label>सल्ला मजकूर (Advice)</label>
            <textarea name="advice" required placeholder="उदा. सध्याच्या ढगाळ वातावरणामुळे द्राक्ष बागेवर भुरी रोगाचा प्रादुर्भाव वाढू शकतो..."></textarea>
            <button type="submit" class="primary"><i class="fa-solid fa-plus"></i> सल्ला जोडा</button>
        </form>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3 style="margin-top:0;">सर्व सल्ले (नवीनतम सर्वात वर दिसतो)</h3>
        <?php if (empty($adviceList)): ?>
            <p style="color:#999;">अजून कोणताही सल्ला जोडलेला नाही.</p>
        <?php else: ?>
        <div class="tbl-wrap">
        <table>
            <thead><tr><th>तज्ज्ञ</th><th>पीक</th><th>सल्ला</th><th>दिनांक</th><th>स्थिती</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($adviceList as $a): $isDeleted = !empty($a['deleted_at']); ?>
                <tr<?php echo $isDeleted ? ' style="opacity:.55;"' : ''; ?>>
                    <td><?php echo htmlspecialchars($a['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($a['crop'] ?: '—'); ?></td>
                    <td style="max-width:280px;"><?php echo nl2br(htmlspecialchars(mb_strimwidth($a['advice'], 0, 160, '...'))); ?></td>
                    <td><?php echo date('d M Y', strtotime($a['created_at'])); ?></td>
                    <td>
                        <?php if ($isDeleted): ?>
                            <span class="tag-deleted">Deleted</span>
                        <?php else: ?>
                            <span class="<?php echo $a['is_active'] ? 'tag-active' : 'tag-inactive'; ?>"><?php echo $a['is_active'] ? 'Active' : 'Inactive'; ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="row-actions">
                        <?php if ($isDeleted): ?>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="action" value="restore">
                            <input type="hidden" name="id" value="<?php echo (int)$a['id']; ?>">
                            <button type="submit" class="restore-btn"><i class="fa-solid fa-rotate-left"></i> Restore</button>
                        </form>
                        <?php else: ?>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?php echo (int)$a['id']; ?>">
                            <button type="submit" class="toggle-btn"><i class="fa-solid fa-toggle-on"></i></button>
                        </form>
                        <form method="post" style="display:inline;" onsubmit="return confirm('हा सल्ला डिलीट करायचा?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo (int)$a['id']; ?>">
                            <button type="submit" class="delete-btn"><i class="fa-solid fa-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<script src="../assets/js/form-scroll-validate.js?v=<?php echo @filemtime(dirname(__DIR__) . '/assets/js/form-scroll-validate.js') ?: time(); ?>"></script>
</body>
</html>
