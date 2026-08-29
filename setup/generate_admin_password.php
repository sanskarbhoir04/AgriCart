<?php
// =====================================================================
// generate_admin_password.php — ONE-TIME SETUP TOOL
// =====================================================================
// Open this file in your browser (e.g. http://localhost/AgriCart/setup/
// generate_admin_password.php), type the admin username + password you
// want, and it prints the exact SQL INSERT to run in phpMyAdmin.
//
// IMPORTANT: Delete or rename this file once you've created your admin
// account. It should never stay live on a real/public server, since
// anyone who can reach it could generate password hashes.
//
// This is additionally guarded by setup/_guard.php: once
// setup/.installed exists, this script refuses to run at all.
// =====================================================================
require_once __DIR__ . '/_guard.php';

$sql = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?: 'admin');
    $password = $_POST['password'] ?? '';
    if ($password !== '') {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO admin_users (username, password, role) VALUES ("
             . "'" . addslashes($username) . "', '" . $hash . "', 'admin');";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Admin Password Generator</title>
<style>
body{font-family:Arial,sans-serif;max-width:600px;margin:40px auto;padding:0 16px;color:#26292B}
input{width:100%;padding:10px;margin:6px 0 14px;border:1px solid #ccc;border-radius:6px;box-sizing:border-box}
button{background:#2F4F44;color:#fff;border:none;padding:10px 18px;border-radius:6px;cursor:pointer}
pre{background:#EEF1EC;padding:14px;border-radius:8px;white-space:pre-wrap;word-break:break-all;font-size:13px}
.warn{background:#F5E8E7;color:#9B3B37;padding:10px;border-radius:8px;font-size:13px;margin-top:20px}
</style>
</head>
<body>
<h2>Admin Password Generator (one-time setup)</h2>
<p>Run <code>admin_setup.sql</code> first if you haven't. Then create your admin login here:</p>
<form method="post">
    <label>Admin username</label>
    <input type="text" name="username" value="admin" required>
    <label>Admin password</label>
    <input type="password" name="password" required>
    <button type="submit">Generate SQL</button>
</form>
<?php if ($sql): ?>
<h3>Copy this into phpMyAdmin's SQL tab and run it:</h3>
<pre><?php echo htmlspecialchars($sql); ?></pre>
<?php endif; ?>
<div class="warn">
    <strong>Security note:</strong> delete or rename this file after creating your
    admin account. It should not remain accessible once setup is done.
</div>
<script src="../assets/js/form-scroll-validate.js?v=<?php echo @filemtime(dirname(__DIR__) . '/assets/js/form-scroll-validate.js') ?: time(); ?>"></script>
</body>
</html>
