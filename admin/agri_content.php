<?php
// =====================================================================
// admin/agri_content.php — Manage the Agri-Connect static content:
// News/Market Updates, Government Schemes, Upcoming Events, Success
// Stories. Standalone page (not yet wired into admin/index.php's tab
// dashboard) — open it directly while logged in as admin.
// =====================================================================
include __DIR__ . '/includes/admin_guard.php';
include __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/agri_connect_schema.php';
agri_connect_bootstrap_schema($conn);
requirePermission('community.approve');

$tab = $_GET['tab'] ?? 'news';
if (!in_array($tab, ['news', 'schemes', 'events', 'stories'], true)) { $tab = 'news'; }

$tableMap = [
    'news'    => ['table' => 'agri_news', 'order' => 'published_at DESC'],
    'schemes' => ['table' => 'government_schemes', 'order' => 'id DESC'],
    'events'  => ['table' => 'agri_events', 'order' => 'event_start ASC'],
    'stories' => ['table' => 'success_stories', 'order' => 'id DESC'],
];
$success = ''; $errors = [];

// ── Handle add ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add' && ($_POST['tab'] ?? '') === $tab) {
    if ($tab === 'news') {
        $category = trim($_POST['category'] ?? 'news');
        $title = trim($_POST['title'] ?? ''); $description = trim($_POST['description'] ?? '');
        $source = trim($_POST['source'] ?? ''); $link = trim($_POST['link'] ?? '');
        if ($title === '') { $errors[] = 'शीर्षक आवश्यक आहे.'; }
        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO agri_news (category, title, description, source, link, is_active) VALUES (?,?,?,?,?,1)");
            $stmt->bind_param("sssss", $category, $title, $description, $source, $link);
            if ($stmt->execute()) { $success = 'बातमी जोडली गेली.'; logAdminActivity('content_news_added', 'content', $conn->insert_id, null, ['title' => $title], 'Added news item "' . $title . '"'); }
            else { $errors[] = 'सेव्ह करताना अडचण आली.'; }
        }
    } elseif ($tab === 'schemes') {
        $name = trim($_POST['name'] ?? ''); $eligibility = trim($_POST['eligibility'] ?? '');
        $benefits = trim($_POST['benefits'] ?? ''); $lastDate = trim($_POST['last_date'] ?? '');
        $link = trim($_POST['official_link'] ?? '');
        if ($name === '') { $errors[] = 'योजनेचे नाव आवश्यक आहे.'; }
        if (empty($errors)) {
            $lastDateVal = $lastDate !== '' ? $lastDate : null;
            $stmt = $conn->prepare("INSERT INTO government_schemes (name, eligibility, benefits, last_date, official_link, is_active) VALUES (?,?,?,?,?,1)");
            $stmt->bind_param("sssss", $name, $eligibility, $benefits, $lastDateVal, $link);
            if ($stmt->execute()) { $success = 'योजना जोडली गेली.'; logAdminActivity('content_scheme_added', 'content', $conn->insert_id, null, ['name' => $name], 'Added government scheme "' . $name . '"'); }
            else { $errors[] = 'सेव्ह करताना अडचण आली.'; }
        }
    } elseif ($tab === 'events') {
        $title = trim($_POST['title'] ?? ''); $location = trim($_POST['location'] ?? '');
        $start = trim($_POST['event_start'] ?? ''); $end = trim($_POST['event_end'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if ($title === '' || $start === '') { $errors[] = 'शीर्षक आणि सुरुवात तारीख आवश्यक आहे.'; }
        if (empty($errors)) {
            $endVal = $end !== '' ? $end : null;
            $stmt = $conn->prepare("INSERT INTO agri_events (title, location, event_start, event_end, description, is_active) VALUES (?,?,?,?,?,1)");
            $stmt->bind_param("sssss", $title, $location, $start, $endVal, $description);
            if ($stmt->execute()) { $success = 'कार्यक्रम जोडला गेला.'; logAdminActivity('content_event_added', 'content', $conn->insert_id, null, ['title' => $title], 'Added event "' . $title . '"'); }
            else { $errors[] = 'सेव्ह करताना अडचण आली.'; }
        }
    } elseif ($tab === 'stories') {
        $farmerName = trim($_POST['farmer_name'] ?? ''); $district = trim($_POST['district'] ?? '');
        $crop = trim($_POST['crop'] ?? ''); $headline = trim($_POST['headline'] ?? '');
        $description = trim($_POST['description'] ?? ''); $incomeChange = trim($_POST['income_change'] ?? '');
        if ($farmerName === '' || $headline === '') { $errors[] = 'शेतकऱ्याचे नाव आणि headline आवश्यक आहे.'; }
        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO success_stories (farmer_name, district, crop, headline, description, income_change, is_active) VALUES (?,?,?,?,?,?,1)");
            $stmt->bind_param("ssssss", $farmerName, $district, $crop, $headline, $description, $incomeChange);
            if ($stmt->execute()) { $success = 'यशोगाथा जोडली गेली.'; logAdminActivity('content_story_added', 'content', $conn->insert_id, null, ['farmer' => $farmerName], 'Added success story for "' . $farmerName . '"'); }
            else { $errors[] = 'सेव्ह करताना अडचण आली.'; }
        }
    }
}

// ── Handle toggle / delete / restore (generic, works for any of the 4 tables) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['toggle', 'delete', 'restore'], true)) {
    $id = (int)($_POST['id'] ?? 0);
    $reqTab = $_POST['tab'] ?? '';
    if ($id > 0 && isset($tableMap[$reqTab])) {
        $table = $tableMap[$reqTab]['table'];
        if ($_POST['action'] === 'toggle') {
            $conn->query("UPDATE `$table` SET is_active = NOT is_active WHERE id = " . $id);
            logAdminActivity('content_' . $reqTab . '_toggled', 'content', $id, null, null, 'Toggled active status on ' . $reqTab . ' #' . $id);
        }
        elseif ($_POST['action'] === 'restore') {
            agri_restore($conn, $table, $id);
            logAdminActivity('content_' . $reqTab . '_restored', 'content', $id, null, null, 'Restored ' . $reqTab . ' #' . $id);
        }
        else {
            agri_soft_delete($conn, $table, $id);
            logAdminActivity('content_' . $reqTab . '_deleted', 'content', $id, null, null, 'Deleted ' . $reqTab . ' #' . $id);
        }
    }
}

$table = $tableMap[$tab]['table'];
$order = $tableMap[$tab]['order'];
$rows = [];
$res = @$conn->query("SELECT * FROM `$table` ORDER BY $order LIMIT 100");
if ($res) { while ($r = $res->fetch_assoc()) { $rows[] = $r; } }

$tabLabels = ['news' => 'बातम्या / Market Updates', 'schemes' => 'सरकारी योजना', 'events' => 'कार्यक्रम', 'stories' => 'यशोगाथा'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Agri-Connect Content — Admin | AgriCart</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
    body { font-family: 'Segoe UI', sans-serif; background: #f0f4f1; margin: 0; padding: 30px; color: #222; }
    .wrap { max-width: 1000px; margin: 0 auto; }
    h1 { font-size: 22px; color: #0b1c14; margin-bottom: 4px; }
    .tabs { display: flex; gap: 8px; margin: 18px 0 22px; flex-wrap: wrap; }
    .tabs a { padding: 8px 18px; border-radius: 20px; background: #fff; color: #333; text-decoration: none; font-size: 13.5px; font-weight: 600; border: 1px solid #ddd; }
    .tabs a.active, .tabs a:hover { background: #2e8b57; color: #fff; border-color: #2e8b57; }
    .card { background: #fff; border-radius: 10px; padding: 24px; box-shadow: 0 4px 18px rgba(0,0,0,0.06); margin-bottom: 25px; }
    label { display: block; font-size: 13px; font-weight: 600; color: #333; margin-bottom: 6px; margin-top: 14px; }
    select, textarea, input[type=text], input[type=date], input[type=url] { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; box-sizing: border-box; font-family: inherit; }
    textarea { height: 70px; resize: vertical; }
    .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    button.primary { margin-top: 18px; background: #2e8b57; color: #fff; border: none; padding: 10px 24px; border-radius: 20px; font-weight: 600; cursor: pointer; }
    button.primary:hover { background: #246b43; }
    .msg-ok { background: #e8f5e9; color: #1b5e20; padding: 10px 14px; border-radius: 6px; margin-bottom: 14px; font-size: 14px; }
    .msg-err { background: #ffebee; color: #b71c1c; padding: 10px 14px; border-radius: 6px; margin-bottom: 14px; font-size: 14px; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th, td { text-align: left; padding: 9px; border-bottom: 1px solid #eee; vertical-align: top; }
    th { color: #666; font-weight: 600; }
    .tag-active { background: #e8f5e9; color: #1b5e20; padding: 2px 9px; border-radius: 10px; font-size: 11px; font-weight: 600; }
    .tag-inactive { background: #f5f5f5; color: #888; padding: 2px 9px; border-radius: 10px; font-size: 11px; font-weight: 600; }
    .tag-deleted { background: #ffebee; color: #b71c1c; padding: 2px 9px; border-radius: 10px; font-size: 11px; font-weight: 600; }
    .row-actions .restore-btn { color: #2e7d32; }
    .row-actions button { border: none; background: none; cursor: pointer; font-size: 13px; padding: 4px 8px; }
    .row-actions .toggle-btn { color: #1565c0; }
    .row-actions .delete-btn { color: #c62828; }
    a.back-link { color: #2e8b57; text-decoration: none; font-size: 13px; }
    .tbl-wrap { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
    @media (max-width: 768px) {
        body { padding: 16px; }
        .wrap { max-width: 100%; }
        h1 { font-size: 19px; }
        .card { padding: 16px; border-radius: 10px; }
        .field-row { grid-template-columns: 1fr; gap: 0; }
        .tabs a { padding: 8px 14px; font-size: 13px; }
        table { min-width: 560px; }
        th, td { padding: 8px; font-size: 12.5px; }
        button.primary, .row-actions button { min-height: 40px; }
        input[type=text], input[type=date], input[type=url], select, textarea { font-size: 16px; }
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
    <h1>Agri-Connect Content Manager</h1>
    <div class="tabs">
        <?php foreach ($tabLabels as $tk => $tl): ?>
            <a href="?tab=<?php echo $tk; ?>" class="<?php echo $tab === $tk ? 'active' : ''; ?>"><?php echo htmlspecialchars($tl); ?></a>
        <?php endforeach; ?>
    </div>

    <?php if ($success): ?><div class="msg-ok"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php foreach ($errors as $e): ?><div class="msg-err"><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?>

    <div class="card">
        <h3 style="margin-top:0;">नवीन <?php echo htmlspecialchars($tabLabels[$tab]); ?> जोडा</h3>

        <?php if ($tab === 'news'): ?>
        <form method="post">
            <input type="hidden" name="action" value="add"><input type="hidden" name="tab" value="news">
            <label>Category</label>
            <select name="category">
                <option value="market">Market Update</option><option value="weather">Weather Alert</option>
                <option value="scheme">Government Scheme</option><option value="crop_advisory">Crop Advisory</option>
                <option value="news">Agriculture News</option>
            </select>
            <label>Title</label><input type="text" name="title" required>
            <label>Description</label><textarea name="description"></textarea>
            <div class="field-row">
                <div><label>Source</label><input type="text" name="source" placeholder="उदा. Krishi Jagran"></div>
                <div><label>Link (ऐच्छिक)</label><input type="url" name="link" placeholder="https://..."></div>
            </div>
            <button type="submit" class="primary"><i class="fa-solid fa-plus"></i> जोडा</button>
        </form>

        <?php elseif ($tab === 'schemes'): ?>
        <form method="post">
            <input type="hidden" name="action" value="add"><input type="hidden" name="tab" value="schemes">
            <label>Scheme Name</label><input type="text" name="name" required placeholder="उदा. PM-KISAN">
            <label>Eligibility</label><textarea name="eligibility"></textarea>
            <label>Benefits</label><textarea name="benefits"></textarea>
            <div class="field-row">
                <div><label>Last Date (ऐच्छिक)</label><input type="date" name="last_date"></div>
                <div><label>Official Link</label><input type="url" name="official_link" placeholder="https://..."></div>
            </div>
            <button type="submit" class="primary"><i class="fa-solid fa-plus"></i> जोडा</button>
        </form>

        <?php elseif ($tab === 'events'): ?>
        <form method="post">
            <input type="hidden" name="action" value="add"><input type="hidden" name="tab" value="events">
            <label>Event Title</label><input type="text" name="title" required>
            <label>Location</label><input type="text" name="location" placeholder="उदा. Pune">
            <div class="field-row">
                <div><label>Start Date</label><input type="date" name="event_start" required></div>
                <div><label>End Date (ऐच्छिक)</label><input type="date" name="event_end"></div>
            </div>
            <label>Description</label><textarea name="description"></textarea>
            <button type="submit" class="primary"><i class="fa-solid fa-plus"></i> जोडा</button>
        </form>

        <?php elseif ($tab === 'stories'): ?>
        <form method="post">
            <input type="hidden" name="action" value="add"><input type="hidden" name="tab" value="stories">
            <div class="field-row">
                <div><label>Farmer Name</label><input type="text" name="farmer_name" required></div>
                <div><label>District</label><input type="text" name="district"></div>
            </div>
            <div class="field-row">
                <div><label>Crop</label><input type="text" name="crop" placeholder="उदा. Strawberry"></div>
                <div><label>Income Change (ऐच्छिक)</label><input type="text" name="income_change" placeholder="उदा. +200%"></div>
            </div>
            <label>Headline</label><input type="text" name="headline" required placeholder="उदा. Suhas earns lakhs from Strawberry Farming">
            <label>Full Story</label><textarea name="description"></textarea>
            <button type="submit" class="primary"><i class="fa-solid fa-plus"></i> जोडा</button>
        </form>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3 style="margin-top:0;">सर्व नोंदी</h3>
        <?php if (empty($rows)): ?>
            <p style="color:#999;">अजून काही जोडलेले नाही.</p>
        <?php else: ?>
        <div class="tbl-wrap">
        <table>
            <thead><tr>
                <th><?php echo $tab === 'stories' ? 'शेतकरी' : ($tab === 'events' ? 'शीर्षक' : ($tab === 'schemes' ? 'योजना' : 'शीर्षक')); ?></th>
                <th>तपशील</th><th>दिनांक</th><th>स्थिती</th><th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $row): $isDeleted = !empty($row['deleted_at']); ?>
                <tr<?php echo $isDeleted ? ' style="opacity:.55;"' : ''; ?>>
                    <td>
                        <?php if ($tab === 'stories') { echo htmlspecialchars($row['farmer_name']); }
                              elseif ($tab === 'schemes') { echo htmlspecialchars($row['name']); }
                              else { echo htmlspecialchars($row['title']); } ?>
                    </td>
                    <td style="max-width:280px;">
                        <?php
                        $detail = $tab === 'stories' ? $row['headline'] : ($tab === 'schemes' ? $row['benefits'] : ($tab === 'events' ? $row['location'] : $row['description']));
                        echo htmlspecialchars(mb_strimwidth((string)$detail, 0, 140, '...'));
                        ?>
                    </td>
                    <td>
                        <?php
                        $dateVal = $row['created_at'] ?? $row['published_at'] ?? $row['event_start'] ?? null;
                        echo $dateVal ? date('d M Y', strtotime($dateVal)) : '—';
                        ?>
                    </td>
                    <td>
                        <?php if ($isDeleted): ?>
                            <span class="tag-deleted">Deleted</span>
                        <?php else: ?>
                            <span class="<?php echo $row['is_active'] ? 'tag-active' : 'tag-inactive'; ?>"><?php echo $row['is_active'] ? 'Active' : 'Inactive'; ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="row-actions">
                        <?php if ($isDeleted): ?>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="action" value="restore"><input type="hidden" name="tab" value="<?php echo $tab; ?>">
                            <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                            <button type="submit" class="restore-btn"><i class="fa-solid fa-rotate-left"></i> Restore</button>
                        </form>
                        <?php else: ?>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="action" value="toggle"><input type="hidden" name="tab" value="<?php echo $tab; ?>">
                            <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                            <button type="submit" class="toggle-btn"><i class="fa-solid fa-toggle-on"></i></button>
                        </form>
                        <form method="post" style="display:inline;" onsubmit="return confirm('डिलीट करायचे?');">
                            <input type="hidden" name="action" value="delete"><input type="hidden" name="tab" value="<?php echo $tab; ?>">
                            <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
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
