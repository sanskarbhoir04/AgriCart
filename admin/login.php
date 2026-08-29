<?php
// =====================================================================
// admin/login.php — Standalone Admin Login (branded, separate from the
// farmer-facing marketplace). On success, redirects to index.php which
// is the full Admin Dashboard with access to every part of AgriCart.
// =====================================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
// Already logged in? Skip straight to the dashboard.
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    header('Location: index.php');
    exit;
}
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login — AgriCart</title>
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
    display:flex;
    align-items:center;
    justify-content:center;
    background:
        radial-gradient(circle at 12% 15%, rgba(76,175,80,0.18), transparent 42%),
        radial-gradient(circle at 88% 85%, rgba(169,139,74,0.12), transparent 45%),
        linear-gradient(135deg, rgba(27,47,41,0.32), rgba(47,79,68,0.22) 55%, rgba(54,94,81,0.28)),
        url('../assets/images/login-bg.png');
    background-size:180% 180%, 180% 180%, 180% 180%, cover;
    background-position:center, center, center, center;
    background-repeat:no-repeat;
    background-attachment:fixed;
    animation:bgDrift 14s ease-in-out infinite;
    padding:20px;
    overflow:hidden;
    position:relative;
}
@keyframes bgDrift{ 0%,100%{background-position:0% 50%} 50%{background-position:100% 50%} }

/* floating soft particles behind the card */
.bg-orb{ position:fixed; border-radius:50%; filter:blur(2px); pointer-events:none; opacity:.5; }
.bg-orb.o1{ width:14px; height:14px; background:#4CAF50; top:18%; left:12%; animation:orbFloat 7s ease-in-out infinite; }
.bg-orb.o2{ width:9px; height:9px; background:#FFC107; top:70%; left:20%; animation:orbFloat 9s ease-in-out infinite 1.2s; }
.bg-orb.o3{ width:11px; height:11px; background:#4CAF50; top:25%; right:15%; animation:orbFloat 8s ease-in-out infinite .6s; }
.bg-orb.o4{ width:7px; height:7px; background:#fff; top:78%; right:22%; animation:orbFloat 10s ease-in-out infinite 2s; }
@keyframes orbFloat{ 0%,100%{transform:translateY(0) scale(1)} 50%{transform:translateY(-22px) scale(1.15)} }

.login-card{
    background:rgba(255,255,255,0.10);
    backdrop-filter:blur(14px) saturate(140%);
    -webkit-backdrop-filter:blur(14px) saturate(140%);
    width:100%;
    max-width:400px;
    border-radius:18px;
    border:1.5px solid rgba(255,255,255,0.5);
    padding:38px 34px 32px;
    box-shadow:0 20px 60px rgba(0,0,0,0.25), inset 0 1px 0 rgba(255,255,255,0.3);
    text-align:center;
    animation:riseIn .55s cubic-bezier(.22,.8,.36,1) both;
    position:relative;
    z-index:1;
    transition:box-shadow .3s ease;
}
.login-card:hover{ box-shadow:0 26px 70px rgba(0,0,0,0.32), inset 0 1px 0 rgba(255,255,255,0.3); }
@keyframes riseIn{ from{opacity:0; transform:translateY(22px) scale(.97)} to{opacity:1; transform:translateY(0) scale(1)} }
@keyframes fadeUp{ from{opacity:0; transform:translateY(10px)} to{opacity:1; transform:translateY(0)} }
.brand{ display:flex; align-items:center; justify-content:center; margin-bottom:6px; animation:fadeUp .5s ease .05s both; }
.brand-badge{
    display:inline-flex; align-items:center; gap:9px;
    transition:transform .25s cubic-bezier(.34,1.56,.64,1);
}
.brand-badge:hover{ transform:scale(1.04); }
.brand-badge .fern{ flex-shrink:0; transition:transform .3s ease; }
.brand-badge:hover .fern{ transform:rotate(-12deg) scale(1.1); }
.brand-badge .txt{ font-size:23px; font-weight:800; letter-spacing:-0.3px; }
.brand-badge .txt .agri{ color:#FFFFFF; }
.brand-badge .txt .cart{ color:#5A9802; margin-left:1px; }
.tagline{ color:rgba(255,255,255,0.75); font-size:12.5px; margin-bottom:26px; animation:fadeUp .5s ease .1s both; }
.login-type-toggle{ display:flex; background:var(--bg-soft); border-radius:10px; padding:4px; margin-bottom:22px; animation:fadeUp .5s ease .15s both; }
.login-type-toggle button{
    flex:1; border:none; background:transparent; padding:9px 10px; border-radius:8px;
    font-family:inherit; font-size:12.5px; font-weight:700; color:var(--muted); cursor:pointer;
    display:flex; align-items:center; justify-content:center; gap:6px;
    transition:background .2s ease, color .2s ease, transform .15s ease;
}
.login-type-toggle button.active{ background:#fff; color:var(--primary-dark); box-shadow:0 2px 8px rgba(0,0,0,0.08); }
.login-type-toggle button:hover:not(.active){ color:var(--primary); }
.login-type-toggle button:active{ transform:scale(.96); }
.badge-admin{
    display:none; align-items:center; gap:6px;
    background:var(--bg-soft); color:var(--primary-dark);
    font-size:11.5px; font-weight:600; padding:5px 12px; border-radius:20px;
    margin-bottom:16px; letter-spacing:0.3px; text-transform:uppercase;
    animation:fadeUp .5s ease .18s both, badgePulse 2.6s ease-in-out 1s infinite;
}
.badge-admin.show{ display:inline-flex; }
@keyframes badgePulse{ 0%,100%{box-shadow:0 0 0 0 rgba(47,79,68,0.15)} 50%{box-shadow:0 0 0 6px rgba(47,79,68,0)} }
.field{ text-align:left; margin-bottom:16px; animation:fadeUp .5s ease both; }
.field:nth-of-type(1){ animation-delay:.2s; }
.field:nth-of-type(2){ animation-delay:.25s; }
.field label{ display:block; font-size:12.5px; font-weight:600; color:rgba(255,255,255,0.88); margin-bottom:6px; transition:color .15s ease; }
.input-wrap{ position:relative; }
.input-wrap i{ position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--muted); font-size:14px; transition:color .15s ease, transform .15s ease; }
.field input{
    width:100%; padding:12px 14px 12px 40px; border:1.5px solid #e2e5e0; border-radius:10px;
    font-size:14px; font-family:inherit; background:#fff; color:var(--text); transition:border-color .18s ease, box-shadow .18s ease, transform .12s ease;
}
.field input:hover{ border-color:#c8cec4; }
.field input:focus{ outline:none; border-color:var(--primary); box-shadow:0 0 0 4px rgba(47,79,68,0.1); transform:translateY(-1px); }
.field input:focus + i, .input-wrap:focus-within i{ color:var(--primary); transform:translateY(-50%) scale(1.1); }
.login-btn{
    width:100%; padding:13px; border:none; border-radius:10px; margin-top:6px;
    background:var(--primary); color:#fff; font-size:14.5px; font-weight:600;
    cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px;
    transition:background .2s ease, transform .15s cubic-bezier(.34,1.56,.64,1), box-shadow .2s ease;
    animation:fadeUp .5s ease .3s both;
    position:relative; overflow:hidden;
}
.login-btn::after{
    content:''; position:absolute; inset:0; background:rgba(255,255,255,0.15);
    transform:scaleX(0); transform-origin:left; transition:transform .35s ease;
}
.login-btn:hover{ background:var(--primary-dark); transform:translateY(-2px); box-shadow:0 8px 18px rgba(27,47,41,0.35); }
.login-btn:hover::after{ transform:scaleX(1); }
.login-btn:active{ transform:scale(0.97) translateY(0); }
.login-btn i{ transition:transform .2s ease; }
.login-btn:hover i{ transform:translateX(3px); }
.error-box{
    background:var(--danger-bg); color:var(--danger); font-size:12.5px; font-weight:600;
    padding:10px 12px; border-radius:8px; margin-bottom:18px; text-align:left;
    display:flex; align-items:center; gap:8px;
    animation:shakeIn .4s ease both;
}
@keyframes shakeIn{ 0%{opacity:0; transform:translateX(-6px)} 30%{transform:translateX(4px)} 60%{transform:translateX(-2px)} 100%{opacity:1; transform:translateX(0)} }
.back-link{ display:block; margin-top:22px; font-size:12.5px; color:rgba(255,255,255,0.7); text-decoration:none; transition:color .15s ease, transform .15s ease; animation:fadeUp .5s ease .35s both; }
.back-link:hover{ color:#fff; text-decoration:underline; transform:translateX(-3px); }
.footnote{ margin-top:18px; font-size:11px; color:rgba(255,255,255,0.45); animation:fadeUp .5s ease .4s both; }
</style>
</head>
<body>
<div class="bg-orb o1"></div>
<div class="bg-orb o2"></div>
<div class="bg-orb o3"></div>
<div class="bg-orb o4"></div>
<div class="login-card">
    <div class="brand">
        <div class="brand-badge">
            <img src="../assets/images/agricart-logo.png?v=<?php echo @filemtime(dirname(__DIR__) . '/assets/images/agricart-logo.png') ?: time(); ?>" alt="AgriCart" class="fern" style="width:60px;height:60px;object-fit:contain;border-radius:50%;flex-shrink:0">
            <span class="txt"><span class="agri">Agri</span><span class="cart">Cart</span></span>
        </div>
    </div>
    <div class="tagline">Admin Panel Login</div>

    <?php if ($error === 'invalid'): ?>
        <div class="error-box"><i class="fa-solid fa-circle-exclamation"></i> Incorrect username or password.</div>
    <?php elseif ($error === 'empty'): ?>
        <div class="error-box"><i class="fa-solid fa-circle-exclamation"></i> Please enter both username and password.</div>
    <?php elseif ($error === 'notadmin'): ?>
        <div class="error-box"><i class="fa-solid fa-circle-exclamation"></i> This account doesn't have admin access.</div>
    <?php endif; ?>

    <form method="POST" action="auth.php" id="loginForm">
        <input type="hidden" name="login_type" value="admin">
        <div class="field">
            <label>Username</label>
            <div class="input-wrap">
                <i class="fa-solid fa-user"></i>
                <input type="text" name="username" placeholder="Enter your username" required autofocus>
            </div>
        </div>
        <div class="field">
            <label>Password</label>
            <div class="input-wrap">
                <i class="fa-solid fa-lock"></i>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
        </div>
        <button type="submit" class="login-btn"><i class="fa-solid fa-right-to-bracket"></i> Login to Dashboard</button>
    </form>

    <a href="../pages/marketplace.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to AgriCart Store</a>
    <div class="footnote">Authorized personnel only · AgriCart Admin Panel</div>
</div>
<script src="../assets/js/form-scroll-validate.js?v=<?php echo @filemtime(dirname(__DIR__) . '/assets/js/form-scroll-validate.js') ?: time(); ?>"></script>
</body>
</html>
