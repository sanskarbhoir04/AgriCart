<?php
// =====================================================================
// admin/access_denied.php — Professional 403 page shown whenever an
// admin is logged in but lacks the permission for a page/action they
// tried to reach directly (e.g. typed a restricted URL by hand).
// The attempt itself is logged by requirePermission() before this page
// is ever reached, so no logging needs to happen here.
// =====================================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
$loggedIn = !empty($_SESSION['is_admin']);
$roleName = $_SESSION['admin_role_name'] ?? '';
$perm = $_GET['perm'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Access Denied — AgriCart Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
    --primary:#2F4F44;
    --primary-dark:#1B2F29;
    --accent:#FFC107;
    --bg-soft:#EEF1EC;
    --text:#26292B;
    --muted:#68706B;
    --danger:#9B3B37;
    --danger-bg:#F5E8E7;
}
*{box-sizing:border-box;margin:0;padding:0}
body{
    font-family:'Poppins',sans-serif;
    min-height:100vh;
    display:flex;align-items:center;justify-content:center;
    background:
        radial-gradient(circle at 12% 15%, rgba(155,59,55,0.14), transparent 42%),
        radial-gradient(circle at 88% 85%, rgba(169,139,74,0.10), transparent 45%),
        linear-gradient(135deg, rgba(27,47,41,0.9), rgba(47,79,68,0.85));
    padding:20px;
}
.card{
    background:#fff;
    border-radius:20px;
    max-width:460px;
    width:100%;
    padding:44px 38px 36px;
    text-align:center;
    box-shadow:0 30px 60px rgba(0,0,0,0.25);
}
.icon{
    width:84px;height:84px;border-radius:50%;
    background:var(--danger-bg);color:var(--danger);
    display:flex;align-items:center;justify-content:center;
    font-size:36px;margin:0 auto 20px;
}
h1{font-size:22px;color:var(--text);margin-bottom:10px}
p{color:var(--muted);font-size:14px;line-height:1.7;margin-bottom:6px}
.meta{
    background:var(--bg-soft);border-radius:12px;padding:12px 16px;
    font-size:12.5px;color:var(--muted);margin:18px 0 26px;text-align:left;
}
.meta b{color:var(--text)}
.btn{
    display:inline-flex;align-items:center;gap:8px;
    background:var(--primary);color:#fff;text-decoration:none;
    padding:12px 26px;border-radius:10px;font-weight:600;font-size:14px;
    transition:background .2s ease;
}
.btn:hover{background:var(--primary-dark)}
.btn.secondary{background:transparent;color:var(--primary);border:1.5px solid var(--primary);margin-left:10px}
</style>
</head>
<body>
<div class="card">
    <div class="icon"><i class="fa-solid fa-lock"></i></div>
    <h1>Access Denied</h1>
    <p>You do not have permission to view this page or perform this action.</p>
    <?php if ($roleName): ?>
    <p>Your current role: <strong><?php echo htmlspecialchars($roleName); ?></strong></p>
    <?php endif; ?>
    <div class="meta">
        This attempt has been recorded in the Admin Activity Log.<br>
        If you believe you should have access to this, please contact your Super Admin.
    </div>
    <?php if ($loggedIn): ?>
        <a href="index.php" class="btn"><i class="fa-solid fa-gauge-high"></i> Back to Dashboard</a>
    <?php else: ?>
        <a href="login.php" class="btn"><i class="fa-solid fa-right-to-bracket"></i> Go to Login</a>
    <?php endif; ?>
</div>
</body>
</html>
