<?php
// =====================================================================
// pages/login.php — Farmer Login. Glassmorphism card style.
// Background unchanged (original dark forest gradient + image).
// All UI text in English.
// =====================================================================
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/accounts_schema.php';
agri_session_start();
include __DIR__ . '/../includes/db.php';

// Already logged in? Skip straight to the store.
if (isset($_SESSION['user_name']) || isset($_SESSION['user'])) {
    header('Location: ../index.php');
    exit;
}

$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'csrf';
    } else {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'empty';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'invalid';
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            agri_session_regenerate();
            $_SESSION['user_id']          = $user['id'];
            $_SESSION['user_name']        = $user['full_name'];
            $_SESSION['user']             = $user['mobile'];
            $_SESSION['user_role']        = $user['role'] ?? 'farmer';
            acc_stamp_login($conn, (int)$user['id'], 'password');
            // Save the rest of the profile too, so "My Profile" shows everything
            // right after login instead of blank fields.
            $_SESSION['user_email']       = $user['email'] ?? '';
            $_SESSION['user_farmer_type'] = $user['farmer_type'] ?? '';
            $_SESSION['user_district']    = $user['district'] ?? '';
            $_SESSION['user_taluka']      = $user['taluka'] ?? '';
            $_SESSION['user_village']     = $user['village'] ?? '';
            $_SESSION['user_crop']        = $user['primary_crop'] ?? '';
            header('Location: ../index.php');
            exit;
        } else {
            $error = 'invalid';
        }
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="dark">
<title>Login — AgriCart</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
    --primary:#2F4F44;
    --primary-dark:#1B2F29;
    --accent:#A98B4A;
    --bg-soft:#EEF1EC;
    --text:#26292B;
    --muted:#68706B;
    --danger:#9B3B37;
    --danger-bg:rgba(245,232,231,0.9);
    color-scheme: dark;
}
*{box-sizing:border-box;margin:0;padding:0}
html{ color-scheme: dark; }
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

.bg-orb{ position:fixed; border-radius:50%; filter:blur(2px); pointer-events:none; opacity:.5; }
.bg-orb.o1{ width:14px; height:14px; background:#4CAF50; top:18%; left:12%; animation:orbFloat 7s ease-in-out infinite; }
.bg-orb.o2{ width:9px; height:9px; background:#A98B4A; top:70%; left:20%; animation:orbFloat 9s ease-in-out infinite 1.2s; }
.bg-orb.o3{ width:11px; height:11px; background:#4CAF50; top:25%; right:15%; animation:orbFloat 8s ease-in-out infinite .6s; }
.bg-orb.o4{ width:7px; height:7px; background:#fff; top:78%; right:22%; animation:orbFloat 10s ease-in-out infinite 2s; }
@keyframes orbFloat{ 0%,100%{transform:translateY(0) scale(1)} 50%{transform:translateY(-22px) scale(1.15)} }

/* ─── GLASS CARD ─── */
.login-card{
    background:rgba(255,255,255,0.10);
    backdrop-filter:blur(14px) saturate(140%);
    -webkit-backdrop-filter:blur(14px) saturate(140%);
    width:100%;
    max-width:400px;
    border-radius:28px;
    border:1.5px solid rgba(255,255,255,0.5);
    padding:38px 34px 32px;
    box-shadow:0 20px 60px rgba(0,0,0,0.25), inset 0 1px 0 rgba(255,255,255,0.3);
    text-align:left;
    animation:riseIn .55s cubic-bezier(.22,.8,.36,1) both;
    position:relative;
    z-index:1;
    transition:box-shadow .3s ease;
}
.login-card:hover{ box-shadow:0 26px 70px rgba(0,0,0,0.32), inset 0 1px 0 rgba(255,255,255,0.3); }
@keyframes riseIn{ from{opacity:0; transform:translateY(22px) scale(.97)} to{opacity:1; transform:translateY(0) scale(1)} }
@keyframes fadeUp{ from{opacity:0; transform:translateY(10px)} to{opacity:1; transform:translateY(0)} }

.brand{ display:flex; align-items:center; gap:10px; margin-bottom:20px; animation:fadeUp .5s ease .05s both; }
.brand img{ width:56px; height:56px; object-fit:contain; }
.brand .brand-name{ font-size:25px; font-weight:800; letter-spacing:-0.3px; }
.brand .brand-name .agri{ color:#fff; }
.brand .brand-name .cart{ color:#5A9802; margin-left:1px; }
.login-title{ font-size:30px; font-weight:800; color:#fff; margin-bottom:8px; animation:fadeUp .5s ease .08s both; }
.tagline{ color:rgba(255,255,255,0.8); font-size:14px; margin-bottom:28px; animation:fadeUp .5s ease .12s both; }

.field{ margin-bottom:16px; animation:fadeUp .5s ease both; }
.field:nth-of-type(1){ animation-delay:.18s; }
.field:nth-of-type(2){ animation-delay:.24s; }
.input-wrap{ position:relative; }
.input-wrap > i{ position:absolute; right:16px; top:50%; transform:translateY(-50%); color:rgba(255,255,255,0.75); font-size:16px; pointer-events:none; transition:color .15s ease; }
.input-wrap .eye-toggle{
    position:absolute; right:10px; top:50%; transform:translateY(-50%);
    width:30px; height:30px; display:flex; align-items:center; justify-content:center;
    pointer-events:auto; cursor:pointer; background:none; border:none; padding:0; margin:0;
    color:rgba(255,255,255,0.75); border-radius:50%; transition:color .15s ease, background .15s ease;
    z-index:2;
}
.input-wrap .eye-toggle:hover{ background:rgba(255,255,255,0.12); color:#fff; }
.input-wrap .eye-toggle i{ position:static; transform:none; font-size:15px; pointer-events:none; }
.field input{
    width:100%; padding:15px 46px 15px 18px; border:1.5px solid rgba(255,255,255,0.5); border-radius:14px;
    font-size:14.5px; font-family:inherit; background:rgba(255,255,255,0.02); color:#fff;
    transition:border-color .18s ease, box-shadow .18s ease, background .18s ease;
    color-scheme: dark;
}
.field input::placeholder{ color:rgba(255,255,255,0.7); }
.field input:hover{ border-color:rgba(255,255,255,0.65); }
.field input:focus{ outline:none; border-color:#4CAF50; box-shadow:0 0 0 4px rgba(76,175,80,0.18); background:rgba(255,255,255,0.09); }
.field input:focus ~ i{ color:#4CAF50; }
/* Stop mobile browsers (Chrome/Safari autofill, Android auto-dark) from forcing a light box on the field */
.field input:-webkit-autofill,
.field input:-webkit-autofill:hover,
.field input:-webkit-autofill:focus{
    -webkit-text-fill-color:#fff;
    -webkit-box-shadow:0 0 0 1000px rgba(47,79,68,0.35) inset;
    transition:background-color 9999s ease-in-out 0s;
}

.remember-row{ display:flex; align-items:center; gap:9px; margin-bottom:22px; animation:fadeUp .5s ease .28s both; }
.remember-row input[type="checkbox"]{ appearance:none; width:20px; height:20px; border-radius:6px; border:1.5px solid rgba(255,255,255,0.5); background:rgba(255,255,255,0.08); cursor:pointer; position:relative; flex-shrink:0; transition:.2s; }
.remember-row input[type="checkbox"]:checked{ background:linear-gradient(135deg,#8BC34A,#2ECC71); border-color:#2ECC71; }
.remember-row input[type="checkbox"]:checked::after{ content:'✓'; position:absolute; inset:0; display:flex; align-items:center; justify-content:center; color:#fff; font-size:13px; font-weight:800; }
.remember-row label{ font-size:13.5px; color:rgba(255,255,255,0.85); cursor:pointer; user-select:none; }

.login-btn{
    width:100%; padding:15px; border:none; border-radius:14px;
    background:linear-gradient(100deg,#AEE24B 0%, #22B573 100%); color:#fff; font-size:16px; font-weight:700;
    cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px;
    transition:transform .15s cubic-bezier(.34,1.56,.64,1), box-shadow .2s ease, filter .2s ease;
    animation:fadeUp .5s ease .32s both;
    box-shadow:0 10px 24px rgba(34,181,115,0.35);
}
.login-btn:hover{ transform:translateY(-2px); filter:brightness(1.06); box-shadow:0 14px 30px rgba(34,181,115,0.45); }
.login-btn:active{ transform:scale(0.97) translateY(0); }

.error-box{
    background:var(--danger-bg); color:var(--danger); font-size:12.5px; font-weight:600;
    padding:10px 12px; border-radius:10px; margin-bottom:18px; text-align:left;
    display:flex; align-items:center; gap:8px;
    animation:shakeIn .4s ease both;
}
@keyframes shakeIn{ 0%{opacity:0; transform:translateX(-6px)} 30%{transform:translateX(4px)} 60%{transform:translateX(-2px)} 100%{opacity:1; transform:translateX(0)} }
.register-note{ margin-top:18px; font-size:13.5px; color:rgba(255,255,255,0.85); text-align:center; animation:fadeUp .5s ease .38s both; }
.register-note a{ color:#fff; font-weight:700; text-decoration:none; }
.register-note a:hover{ text-decoration:underline; }
.back-link{ display:block; margin-top:16px; font-size:12.5px; color:rgba(255,255,255,0.7); text-decoration:none; text-align:center; transition:color .15s ease, transform .15s ease; animation:fadeUp .5s ease .42s both; }
.back-link:hover{ color:#fff; text-decoration:underline; }
.footnote{ margin-top:20px; font-size:11px; color:rgba(255,255,255,0.55); text-align:center; animation:fadeUp .5s ease .46s both; }
</style>
<link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body>
<div class="bg-orb o1"></div>
<div class="bg-orb o2"></div>
<div class="bg-orb o3"></div>
<div class="bg-orb o4"></div>
<div class="login-card">
    <div class="brand">
        <img src="../assets/images/agricart-logo.png?v=<?php echo @filemtime(dirname(__DIR__) . '/assets/images/agricart-logo.png') ?: time(); ?>" alt="AgriCart" style="width:60px;height:60px;object-fit:contain;border-radius:50%">
        <span class="brand-name"><span class="agri">Agri</span><span class="cart">Cart</span></span>
    </div>
    <div class="login-title">Login</div>
    <div class="tagline">Welcome back, please login to your account</div>

    <?php if ($error === 'invalid'): ?>
        <div class="error-box"><i class="fa-solid fa-circle-exclamation"></i> Incorrect email or password.</div>
    <?php elseif ($error === 'empty'): ?>
        <div class="error-box"><i class="fa-solid fa-circle-exclamation"></i> Please enter both email and password.</div>
    <?php elseif ($error === 'csrf'): ?>
        <div class="error-box"><i class="fa-solid fa-circle-exclamation"></i> Your session expired. Please try logging in again.</div>
    <?php endif; ?>

    <form method="POST" id="loginForm">
        <?php echo csrf_field(); ?>
        <div class="field">
            <div class="input-wrap">
                <input type="email" name="email" placeholder="Email Address"
                    required autofocus>
                <i class="fa-solid fa-envelope"></i>
            </div>
        </div>
        <div class="field">
            <div class="input-wrap">
                <input type="password" name="password" id="pwd" placeholder="Password" required>
                <button type="button" class="eye-toggle" onclick="const p=document.getElementById('pwd'); p.type = p.type==='password' ? 'text' : 'password'; this.firstElementChild.className = p.type==='password' ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';">
                    <i class="fa-solid fa-eye-slash"></i>
                </button>
            </div>
        </div>

        <div class="remember-row">
            <input type="checkbox" id="remember" name="remember">
            <label for="remember">Remember me</label>
        </div>

        <button type="submit" class="login-btn">Login</button>
    </form>

    <div class="register-note">Don't have an account? <a href="register.php">Signup</a></div>
    <a href="../index.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to AgriCart Store</a>
    <div class="footnote">Trusted by 50,000+ Farmers · AgriCart</div>
</div>
</body>
</html>
