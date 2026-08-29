<?php
// =====================================================================
// admin/account_details.php — Full account detail page for Accounts
// Management. Tabs are dynamic per account type (spec section 8):
//   Buyer    -> Overview | Personal Details | Orders | Reviews | Activity | Verification
//   Seller   -> Overview | Seller Details | Products | Orders | Earnings | Reviews | Activity | Verification
//   Company  -> Overview | Business Details | Products | Orders | Reviews | Verification | Documents
//   Employee -> Overview | Role & Permissions | Activity | Login History
// =====================================================================
require_once __DIR__ . '/includes/admin_guard.php';
require_once __DIR__ . '/../includes/accounts_schema.php';
require_once __DIR__ . '/includes/companies_schema.php';
accounts_bootstrap_schema($conn);
companies_bootstrap_schema($conn);
requirePermission('accounts.view');

$canManage = hasPermission('accounts.manage');
$canVerify = hasPermission('accounts.verify');

$type = $_GET['type'] ?? '';
$id   = (int)($_GET['id'] ?? 0);
$validTypes = ['buyer', 'seller', 'company', 'employee'];
if (!in_array($type, $validTypes, true) || $id <= 0) {
    http_response_code(404);
    die('Account not found.');
}

$account = null;
$orders = [];
$products = [];
$reviews = [];
$activity = [];
$permissions = [];
$documents = [];
$stats = [];

if ($type === 'buyer' || $type === 'seller') {
    $s = $conn->prepare("SELECT * FROM users WHERE id = ? AND role <> 'admin' LIMIT 1");
    $s->bind_param('i', $id); $s->execute();
    $account = $s->get_result()->fetch_assoc();
    if (!$account) { http_response_code(404); die('Account not found.'); }

    if ($type === 'buyer') {
        $stats = acc_buyer_stats($conn, $id);
        $s = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 30");
        $s->bind_param('i', $id); $s->execute();
        $orders = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        $stats = acc_seller_stats($conn, $id);
        $s = $conn->prepare("SELECT * FROM products WHERE added_by_user_id = ? ORDER BY created_at DESC LIMIT 30");
        $s->bind_param('i', $id); $s->execute();
        $products = $s->get_result()->fetch_all(MYSQLI_ASSOC);
        $s = $conn->prepare(
            "SELECT oi.*, o.order_number, o.ordered_at FROM order_items oi
             JOIN orders o ON o.id = oi.order_id WHERE oi.seller_id = ? ORDER BY o.ordered_at DESC LIMIT 30"
        );
        $s->bind_param('i', $id); $s->execute();
        $orders = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    $s = $conn->prepare("SELECT r.*, p.name AS product_name FROM reviews r LEFT JOIN products p ON p.id = r.item_id AND r.item_type='product' WHERE r.user_id = ? ORDER BY r.created_at DESC LIMIT 20");
    $s->bind_param('i', $id); $s->execute();
    $reviews = $s->get_result()->fetch_all(MYSQLI_ASSOC);

    $s = $conn->prepare("SELECT * FROM activity_logs WHERE actor_type='user' AND actor_id = ? ORDER BY created_at DESC LIMIT 20");
    $s->bind_param('i', $id); $s->execute();
    $activity = $s->get_result()->fetch_all(MYSQLI_ASSOC);

    [$statusLabel, $statusClass] = acc_status_label($account['status'], $account['deleted_at']);
    $displayName = $account['full_name'] ?: ($account['name'] ?: ('User #' . $id));

} elseif ($type === 'company') {
    $s = $conn->prepare("SELECT * FROM sellers WHERE id = ? LIMIT 1");
    $s->bind_param('i', $id); $s->execute();
    $account = $s->get_result()->fetch_assoc();
    if (!$account) { http_response_code(404); die('Account not found.'); }

    $stats = acc_company_stats($conn, $id, $account['name']);
    $match = acc_company_match_where($conn, 'p', $id, $account['name']);
    $res = $conn->query("SELECT * FROM products p WHERE $match ORDER BY p.created_at DESC LIMIT 30");
    if ($res) { $products = $res->fetch_all(MYSQLI_ASSOC); }
    $res = $conn->query("SELECT oi.*, o.order_number, o.ordered_at FROM order_items oi JOIN orders o ON o.id=oi.order_id JOIN products p ON p.id=oi.product_id WHERE $match ORDER BY o.ordered_at DESC LIMIT 30");
    if ($res) { $orders = $res->fetch_all(MYSQLI_ASSOC); }
    $res = $conn->query("SELECT r.*, p.name AS product_name FROM reviews r JOIN products p ON p.id=r.item_id AND r.item_type='product' WHERE $match ORDER BY r.created_at DESC LIMIT 20");
    if ($res) { $reviews = $res->fetch_all(MYSQLI_ASSOC); }

    if (acc_table_exists($conn, 'account_documents')) {
        $s = $conn->prepare("SELECT * FROM account_documents WHERE account_type='company' AND account_id = ? ORDER BY uploaded_at DESC");
        $s->bind_param('i', $id); $s->execute();
        $documents = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    [$statusLabel, $statusClass] = acc_status_label($account['account_status'] ?? 'active', $account['deleted_at'] ?? null);
    $displayName = $account['name'];

} else { // employee
    $s = $conn->prepare(
        "SELECT tm.*, u.full_name, u.email, u.mobile, u.profile_photo, u.id AS user_id, r.role_name, r.id AS role_id
           FROM admin_team_members tm JOIN users u ON u.id = tm.user_id LEFT JOIN admin_roles r ON r.id = tm.role_id
          WHERE tm.id = ? LIMIT 1"
    );
    $s->bind_param('i', $id); $s->execute();
    $account = $s->get_result()->fetch_assoc();
    if (!$account) { http_response_code(404); die('Account not found.'); }

    if (function_exists('team_roles_get_for_member')) {
        $roleIds = team_roles_get_for_member($conn, $id);
        if ($roleIds) {
            $in = implode(',', array_map('intval', $roleIds));
            $res = $conn->query("SELECT DISTINCT p.permission_key, p.module_name, p.action_name FROM admin_role_permissions rp JOIN admin_permissions p ON p.id=rp.permission_id WHERE rp.role_id IN ($in) AND rp.allowed=1 ORDER BY p.module_name, p.action_name");
            if ($res) { $permissions = $res->fetch_all(MYSQLI_ASSOC); }
        }
    }

    $s = $conn->prepare("SELECT * FROM admin_activity_logs WHERE admin_user_id = ? ORDER BY created_at DESC LIMIT 30");
    $s->bind_param('i', $account['user_id']); $s->execute();
    $activity = $s->get_result()->fetch_all(MYSQLI_ASSOC);

    [$statusLabel, $statusClass] = acc_status_label($account['status'], null);
    $displayName = $account['full_name'];
}

$pageTitle     = $displayName . ' — ' . acc_type_label($type);
$activeTeamTab = 'accounts';
include __DIR__ . '/includes/team_layout_top.php';
?>
<style>
.acc-detail-head{display:flex;align-items:center;gap:18px;margin-bottom:8px;flex-wrap:wrap}
.acc-detail-avatar{width:64px;height:64px;border-radius:16px;background:var(--bg-soft);color:var(--primary);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:24px;flex-shrink:0;overflow:hidden}
.acc-detail-avatar img{width:100%;height:100%;object-fit:cover}
.acc-detail-meta{font-size:12.5px;color:var(--muted);display:flex;gap:14px;flex-wrap:wrap;margin-top:6px}
.acc-tabbar{display:flex;gap:4px;flex-wrap:wrap;border-bottom:1.5px solid var(--border);margin:20px 0 18px}
.acc-tabbar button{background:none;border:none;padding:10px 16px;font-size:13px;font-weight:600;color:var(--muted);border-bottom:2.5px solid transparent;margin-bottom:-1.5px;cursor:pointer;font-family:inherit}
.acc-tabbar button.active{color:var(--primary);border-color:var(--primary)}
.acc-pane{display:none}
.acc-pane.active{display:block}
/* Same left-accent + icon-circle stat pattern as company_profile.php's .cp-stat,
   so the account detail page matches every other detail/profile page. */
.acc-stat-grid{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:18px}
.acc-stat{position:relative;flex:1 1 170px;background:#fff;border:1px solid var(--border);border-left:4px solid var(--primary);border-radius:14px;padding:15px 18px;display:flex;align-items:center;gap:14px}
.acc-stat .icn{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0;background:rgba(47,79,68,.12);color:var(--primary)}
.acc-stat .n{font-size:18px;font-weight:800;color:var(--text);line-height:1.2}
.acc-stat .l{font-size:11px;color:var(--muted);font-weight:600;margin-top:2px}
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px 22px;font-size:12.5px}
.info-grid div b{display:block;color:var(--muted);font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.03em;margin-bottom:3px}
.verify-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border)}
.verify-row:last-child{border-bottom:none}
.note-box{background:var(--bg-soft);border-left:4px solid var(--primary);padding:12px 16px;border-radius:8px;font-size:12.5px;color:var(--muted);margin-bottom:16px}
</style>

<div class="card">
    <div class="acc-detail-head">
        <div class="acc-detail-avatar"><?php
            $photo = $account['profile_photo'] ?? $account['logo'] ?? null;
            $initial = strtoupper(substr($displayName ?: '?', 0, 1));
            echo !empty($photo) ? '<img src="' . htmlspecialchars(acc_img_url($photo)) . '" onerror="this.parentElement.textContent=' . json_encode($initial) . '">' : $initial;
        ?></div>
        <div style="flex:1;min-width:200px">
            <div style="font-size:19px;font-weight:700;display:flex;align-items:center;gap:10px"><?php echo htmlspecialchars($displayName); ?>
                <span class="acc-type-pill" style="background:var(--bg-soft);color:var(--primary);font-size:10.5px;font-weight:700;padding:3px 9px;border-radius:20px;text-transform:uppercase"><?php echo acc_type_label($type); ?></span>
                <span class="tag <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusLabel); ?></span>
            </div>
            <div class="acc-detail-meta">
                <span><i class="fa-solid fa-envelope"></i> <?php echo htmlspecialchars($account['email'] ?? '—'); ?></span>
                <span><i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($account['mobile'] ?? $account['phone'] ?? '—'); ?></span>
                <span><i class="fa-solid fa-hashtag"></i> <?php echo strtoupper($type); ?>-<?php echo str_pad($id, 4, '0', STR_PAD_LEFT); ?></span>
            </div>
        </div>
        <a href="accounts.php?tab=<?php echo $type === 'company' ? 'companies' : $type . 's'; ?>" class="btn outline sm"><i class="fa-solid fa-arrow-left"></i> Back to List</a>
    </div>

    <div class="acc-tabbar" id="accTabbar">
        <button class="active" data-pane="overview">Overview</button>
        <button data-pane="details"><?php echo $type === 'company' ? 'Business Details' : 'Personal Details'; ?></button>
        <?php if ($type === 'buyer'): ?><button data-pane="orders">Orders</button><?php endif; ?>
        <?php if ($type === 'seller' || $type === 'company'): ?><button data-pane="products">Products</button><button data-pane="orders">Orders</button><?php endif; ?>
        <?php if ($type === 'seller'): ?><button data-pane="earnings">Earnings</button><?php endif; ?>
        <?php if ($type !== 'employee'): ?><button data-pane="reviews">Reviews</button><?php endif; ?>
        <?php if ($type === 'employee'): ?><button data-pane="permissions">Role & Permissions</button><?php endif; ?>
        <button data-pane="activity">Activity</button>
        <?php if ($type !== 'employee'): ?><button data-pane="verification">Verification</button><?php endif; ?>
        <?php if ($type === 'company'): ?><button data-pane="documents">Documents</button><?php endif; ?>
    </div>

    <!-- Overview -->
    <div class="acc-pane active" data-pane="overview">
        <div class="acc-stat-grid">
        <?php if ($type === 'buyer'): ?>
            <div class="acc-stat"><div class="icn"><i class="fa-solid fa-truck-fast"></i></div><div><div class="n"><?php echo $stats['total_orders']; ?></div><div class="l">Total Orders</div></div></div>
            <div class="acc-stat"><div class="icn"><i class="fa-solid fa-circle-check"></i></div><div><div class="n"><?php echo $stats['completed_orders']; ?></div><div class="l">Completed</div></div></div>
            <div class="acc-stat"><div class="icn"><i class="fa-solid fa-hourglass-half"></i></div><div><div class="n"><?php echo $stats['pending_orders']; ?></div><div class="l">Pending</div></div></div>
            <div class="acc-stat"><div class="icn"><i class="fa-solid fa-indian-rupee-sign"></i></div><div><div class="n">₹<?php echo number_format($stats['total_value'], 0); ?></div><div class="l">Total Purchase Value</div></div></div>
            <div class="acc-stat"><div class="icn"><i class="fa-solid fa-heart"></i></div><div><div class="n"><?php echo $stats['wishlist']; ?></div><div class="l">Wishlist Items</div></div></div>
            <div class="acc-stat"><div class="icn"><i class="fa-solid fa-star"></i></div><div><div class="n"><?php echo $stats['reviews']; ?></div><div class="l">Reviews Submitted</div></div></div>
        <?php elseif ($type === 'seller'): ?>
            <div class="acc-stat"><div class="icn"><i class="fa-solid fa-cart-shopping"></i></div><div><div class="n"><?php echo $stats['total_products']; ?></div><div class="l">Total Products</div></div></div>
            <div class="acc-stat"><div class="icn"><i class="fa-solid fa-circle-check"></i></div><div><div class="n"><?php echo $stats['active_products']; ?></div><div class="l">Active Products</div></div></div>
            <div class="acc-stat"><div class="icn"><i class="fa-solid fa-truck-fast"></i></div><div><div class="n"><?php echo $stats['total_orders']; ?></div><div class="l">Total Orders</div></div></div>
            <div class="acc-stat"><div class="icn"><i class="fa-solid fa-indian-rupee-sign"></i></div><div><div class="n">₹<?php echo number_format($stats['total_sales'], 0); ?></div><div class="l">Total Sales</div></div></div>
            <div class="acc-stat"><div class="icn"><i class="fa-solid fa-sack-dollar"></i></div><div><div class="n">₹<?php echo number_format($stats['total_earnings'], 0); ?></div><div class="l">Total Earnings</div></div></div>
            <div class="acc-stat"><div class="icn"><i class="fa-solid fa-star"></i></div><div><div class="n"><?php echo $stats['rating'] ?: '—'; ?></div><div class="l">Seller Rating</div></div></div>
        <?php elseif ($type === 'company'): ?>
            <div class="acc-stat"><div class="icn"><i class="fa-solid fa-cart-shopping"></i></div><div><div class="n"><?php echo $stats['total_products']; ?></div><div class="l">Total Products</div></div></div>
            <div class="acc-stat"><div class="icn"><i class="fa-solid fa-circle-check"></i></div><div><div class="n"><?php echo $stats['active_products']; ?></div><div class="l">Active Products</div></div></div>
            <div class="acc-stat"><div class="icn"><i class="fa-solid fa-truck-fast"></i></div><div><div class="n"><?php echo $stats['total_orders']; ?></div><div class="l">Total Orders</div></div></div>
            <div class="acc-stat"><div class="icn"><i class="fa-solid fa-indian-rupee-sign"></i></div><div><div class="n">₹<?php echo number_format($stats['total_sales'], 0); ?></div><div class="l">Total Sales</div></div></div>
            <div class="acc-stat"><div class="icn"><i class="fa-solid fa-star"></i></div><div><div class="n"><?php echo $stats['rating'] ?: '—'; ?></div><div class="l">Rating</div></div></div>
        <?php else: // employee ?>
            <div class="acc-stat"><div class="icn"><i class="fa-solid fa-user-shield"></i></div><div><div class="n"><?php echo htmlspecialchars($account['role_name'] ?: '—'); ?></div><div class="l">Role</div></div></div>
            <div class="acc-stat"><div class="icn"><i class="fa-solid fa-sitemap"></i></div><div><div class="n"><?php echo htmlspecialchars($account['department'] ?: '—'); ?></div><div class="l">Department</div></div></div>
            <div class="acc-stat"><div class="icn"><i class="fa-solid fa-clock-rotate-left"></i></div><div><div class="n"><?php echo acc_employee_activity_count($conn, (int)$account['user_id']); ?></div><div class="l">Actions Logged</div></div></div>
            <div class="acc-stat"><div class="icn"><i class="fa-solid fa-right-to-bracket"></i></div><div><div class="n"><?php echo $account['last_login'] ? acc_fmt_date($account['last_login']) : '—'; ?></div><div class="l">Last Login</div></div></div>
        <?php endif; ?>
        </div>
        <?php if (!empty($account['status_reason'])): ?>
        <div class="note-box" style="background:var(--danger-bg);border-left-color:var(--danger);color:var(--danger)"><b>Status reason:</b> <?php echo htmlspecialchars($account['status_reason']); ?></div>
        <?php endif; ?>
    </div>

    <!-- Details -->
    <div class="acc-pane" data-pane="details">
        <div class="info-grid">
        <?php if ($type === 'buyer' || $type === 'seller'): ?>
            <div><b>Full Name</b><?php echo htmlspecialchars($account['full_name'] ?: '—'); ?></div>
            <div><b>Email</b><?php echo htmlspecialchars($account['email'] ?: '—'); ?></div>
            <div><b>Mobile</b><?php echo htmlspecialchars($account['mobile'] ?: $account['phone'] ?: '—'); ?></div>
            <div><b>Village</b><?php echo htmlspecialchars($account['village'] ?: '—'); ?></div>
            <div><b>District</b><?php echo htmlspecialchars($account['district'] ?: '—'); ?></div>
            <div><b>Taluka</b><?php echo htmlspecialchars($account['taluka'] ?: '—'); ?></div>
            <div><b>Pincode</b><?php echo htmlspecialchars($account['saved_pincode'] ?: '—'); ?></div>
            <div><b>Address</b><?php echo htmlspecialchars($account['saved_address'] ?: '—'); ?></div>
            <div><b>Registered</b><?php echo acc_fmt_date($account['created_at']); ?></div>
            <div><b>Last Login</b><?php echo $account['last_login_at'] ? acc_fmt_datetime($account['last_login_at']) : '—'; ?></div>
            <div><b>Login Method</b><?php echo htmlspecialchars($account['login_method'] ?: '—'); ?></div>
            <?php if ($type === 'seller'): ?><div><b>Farmer/Seller Type</b><?php echo htmlspecialchars($account['farmer_type'] ?: '—'); ?></div><?php endif; ?>
        <?php elseif ($type === 'company'): ?>
            <div><b>Company Name</b><?php echo htmlspecialchars($account['name']); ?></div>
            <div><b>Business Type / Category</b><?php echo htmlspecialchars($account['category'] ?: $account['business_type'] ?: '—'); ?></div>
            <div><b>Email</b><?php echo htmlspecialchars($account['email'] ?: '—'); ?></div>
            <div><b>Mobile</b><?php echo htmlspecialchars($account['mobile'] ?: '—'); ?></div>
            <div><b>GSTIN</b><?php echo htmlspecialchars($account['gstin'] ?: 'Not provided'); ?></div>
            <div><b>Village / City</b><?php echo htmlspecialchars(trim(implode(', ', array_filter([$account['village'] ?? '', $account['city'] ?? '']))) ?: '—'); ?></div>
            <div><b>District</b><?php echo htmlspecialchars($account['district'] ?: '—'); ?></div>
            <div><b>State</b><?php echo htmlspecialchars($account['state'] ?: '—'); ?></div>
            <div><b>Pincode</b><?php echo htmlspecialchars($account['pincode'] ?: '—'); ?></div>
            <div><b>Registered</b><?php echo acc_fmt_date($account['created_at']); ?></div>
            <div class="form-group full"><b>Description</b><?php echo htmlspecialchars($account['description'] ?: '—'); ?></div>
            <div class="form-group full"><b>Internal Notes</b><?php echo htmlspecialchars($account['notes'] ?: '—'); ?></div>
        <?php else: ?>
            <div><b>Full Name</b><?php echo htmlspecialchars($account['full_name']); ?></div>
            <div><b>Email</b><?php echo htmlspecialchars($account['email'] ?: '—'); ?></div>
            <div><b>Mobile</b><?php echo htmlspecialchars($account['mobile'] ?: '—'); ?></div>
            <div><b>Department</b><?php echo htmlspecialchars($account['department'] ?: '—'); ?></div>
            <div><b>Joining Date</b><?php echo acc_fmt_date($account['assigned_at']); ?></div>
            <div><b>Access Start</b><?php echo acc_fmt_date($account['access_start_date']); ?></div>
            <div><b>Access Expiry</b><?php echo $account['access_expiry_date'] ? acc_fmt_date($account['access_expiry_date']) : 'No expiry'; ?></div>
            <div><b>Scope</b><?php echo htmlspecialchars(ucfirst($account['scope_type'] ?: 'all')) . ($account['scope_value'] ? ' — ' . htmlspecialchars($account['scope_value']) : ''); ?></div>
        <?php endif; ?>
        </div>
    </div>

    <?php if ($type === 'seller' || $type === 'company'): ?>
    <!-- Products -->
    <div class="acc-pane" data-pane="products">
        <?php if (empty($products)): ?><div class="empty-state"><i class="fa-solid fa-box-open"></i>No products yet.</div><?php else: ?>
        <table><thead><tr><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($products as $p): ?>
            <tr><td><?php echo htmlspecialchars($p['name']); ?></td><td><?php echo htmlspecialchars($p['category'] ?: '—'); ?></td>
            <td>₹<?php echo number_format($p['price'], 2); ?></td><td><?php echo (int)$p['stock']; ?></td>
            <td><span class="tag <?php echo $p['is_active'] ? 'active' : 'inactive'; ?>"><?php echo $p['is_active'] ? 'Active' : 'Inactive'; ?></span></td></tr>
        <?php endforeach; ?>
        </tbody></table>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($type === 'buyer' || $type === 'seller' || $type === 'company'): ?>
    <!-- Orders -->
    <div class="acc-pane" data-pane="orders">
        <?php if (empty($orders)): ?><div class="empty-state"><i class="fa-solid fa-truck-fast"></i>No orders yet.</div><?php else: ?>
        <table><thead><tr><th>Order</th><th>Item / Amount</th><th>Status</th><th>Date</th></tr></thead><tbody>
        <?php foreach ($orders as $o): ?>
            <tr>
                <td><?php echo htmlspecialchars($o['order_number'] ?? ('#' . $o['order_id'])); ?></td>
                <td><?php echo isset($o['final_amount']) ? '₹' . number_format($o['final_amount'], 2) : htmlspecialchars(($o['product_name'] ?? '') . ' — ₹' . number_format($o['subtotal'] ?? 0, 2)); ?></td>
                <td><span class="tag active"><?php echo htmlspecialchars($o['status'] ?? $o['item_status'] ?? '—'); ?></span></td>
                <td><?php echo acc_fmt_date($o['ordered_at'] ?? $o['created_at'] ?? null); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($type === 'seller'): ?>
    <!-- Earnings -->
    <div class="acc-pane" data-pane="earnings">
        <div class="acc-stat-grid">
            <div class="acc-stat"><div class="icn"><i class="fa-solid fa-sack-dollar"></i></div><div><div class="n">₹<?php echo number_format($stats['total_earnings'], 0); ?></div><div class="l">Total Earnings</div></div></div>
            <div class="acc-stat"><div class="icn"><i class="fa-solid fa-hourglass-half"></i></div><div><div class="n">₹<?php echo number_format($stats['pending_earnings'], 0); ?></div><div class="l">Pending Earnings</div></div></div>
            <div class="acc-stat"><div class="icn"><i class="fa-solid fa-wallet"></i></div><div><div class="n">₹<?php echo number_format($stats['pending_withdrawal'], 0); ?></div><div class="l">Available for Withdrawal</div></div></div>
            <div class="acc-stat"><div class="icn"><i class="fa-solid fa-hand-holding-dollar"></i></div><div><div class="n">₹<?php echo number_format($stats['withdrawn'], 0); ?></div><div class="l">Total Withdrawn</div></div></div>
        </div>
        <a href="seller_payouts.php" class="btn outline sm"><i class="fa-solid fa-hand-holding-dollar"></i> View Payout Requests</a>
    </div>
    <?php endif; ?>

    <?php if ($type !== 'employee'): ?>
    <!-- Reviews -->
    <div class="acc-pane" data-pane="reviews">
        <?php if (empty($reviews)): ?><div class="empty-state"><i class="fa-solid fa-star-half-stroke"></i>No reviews yet.</div><?php else: ?>
        <table><thead><tr><th>Product</th><th>Rating</th><th>Comment</th><th>Date</th></tr></thead><tbody>
        <?php foreach ($reviews as $r): ?>
            <tr><td><?php echo htmlspecialchars($r['product_name'] ?? '—'); ?></td><td><?php echo str_repeat('★', (int)$r['rating']); ?></td>
            <td><?php echo htmlspecialchars($r['comment'] ?: '—'); ?></td><td><?php echo acc_fmt_date($r['created_at']); ?></td></tr>
        <?php endforeach; ?>
        </tbody></table>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($type === 'employee'): ?>
    <!-- Permissions -->
    <div class="acc-pane" data-pane="permissions">
        <?php if (empty($permissions)): ?><div class="empty-state"><i class="fa-solid fa-key"></i>No permissions assigned yet.</div>
        <?php else: $grouped = []; foreach ($permissions as $p) { $grouped[$p['module_name']][] = $p['action_name']; } ?>
            <?php foreach ($grouped as $mod => $acts): ?>
            <div class="perm-group"><h4><?php echo htmlspecialchars(ucwords(str_replace('_',' ',$mod))); ?></h4>
            <div class="perm-checks"><?php foreach ($acts as $act): ?><span class="tag active"><?php echo htmlspecialchars($act); ?></span><?php endforeach; ?></div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <a href="edit_role.php?id=<?php echo (int)$account['role_id']; ?>" class="btn outline sm"><i class="fa-solid fa-user-shield"></i> Edit Role Permissions</a>
    </div>
    <?php endif; ?>

    <!-- Activity -->
    <div class="acc-pane" data-pane="activity">
        <?php if (empty($activity)): ?><div class="empty-state"><i class="fa-solid fa-clock-rotate-left"></i>No activity recorded yet.</div><?php else: ?>
        <div class="acc-activity">
        <?php foreach ($activity as $r): ?>
            <div class="acc-activity-row"><i class="fa-solid fa-circle-info"></i>
                <div><div><?php echo htmlspecialchars($r['description'] ?? $r['action']); ?></div>
                <div class="when"><?php echo acc_fmt_datetime($r['created_at']); ?><?php echo !empty($r['ip_address']) ? ' · IP ' . htmlspecialchars($r['ip_address']) : ''; ?></div></div>
            </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($type !== 'employee'): ?>
    <!-- Verification -->
    <div class="acc-pane" data-pane="verification">
        <?php if ($type === 'buyer' || $type === 'seller'): ?>
            <div class="verify-row"><span>Email Verification</span><span class="tag <?php echo !empty($account['email_verified']) ? 'active':'pending'; ?>"><?php echo !empty($account['email_verified']) ? 'Verified':'Pending'; ?></span></div>
            <div class="verify-row"><span>Mobile Verification</span><span class="tag <?php echo !empty($account['mobile_verified']) ? 'active':'pending'; ?>"><?php echo !empty($account['mobile_verified']) ? 'Verified':'Pending'; ?></span></div>
            <div class="verify-row"><span>KYC / Identity Verification</span><span class="tag <?php echo !empty($account['kyc_verified']) ? 'active':'pending'; ?>"><?php echo !empty($account['kyc_verified']) ? 'Verified':'Pending'; ?></span></div>
        <?php else: ?>
            <div class="verify-row"><span>GST Verification</span><span class="tag <?php echo !empty($account['gst_verified']) ? 'active':'pending'; ?>"><?php echo !empty($account['gst_verified']) ? 'Verified':'Pending'; ?></span></div>
            <div class="verify-row"><span>Business Verification</span><span class="tag <?php echo !empty($account['business_verified']) ? 'active':'pending'; ?>"><?php echo !empty($account['business_verified']) ? 'Verified':'Pending'; ?></span></div>
            <div class="verify-row"><span>Bank Details Verification</span><span class="tag <?php echo !empty($account['bank_verified']) ? 'active':'pending'; ?>"><?php echo !empty($account['bank_verified']) ? 'Verified':'Pending'; ?></span></div>
            <div class="verify-row"><span>KYC / Identity Verification</span><span class="tag <?php echo !empty($account['kyc_verified']) ? 'active':'pending'; ?>"><?php echo !empty($account['kyc_verified']) ? 'Verified':'Pending'; ?></span></div>
        <?php endif; ?>
        <?php if ($canVerify): ?>
        <div style="margin-top:16px"><button class="btn" onclick="accVerify('<?php echo $type; ?>',<?php echo $id; ?>)"><i class="fa-solid fa-shield-halved"></i> Mark Fully Verified</button></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($type === 'company'): ?>
    <!-- Documents -->
    <div class="acc-pane" data-pane="documents">
        <?php if (empty($documents)): ?><div class="empty-state"><i class="fa-solid fa-file-lines"></i>No business documents uploaded yet.</div>
        <?php else: ?>
        <table><thead><tr><th>Document Type</th><th>Uploaded</th><th></th></tr></thead><tbody>
        <?php foreach ($documents as $d): ?>
            <tr><td><?php echo htmlspecialchars($d['doc_type']); ?></td><td><?php echo acc_fmt_date($d['uploaded_at']); ?></td>
            <td><a href="<?php echo htmlspecialchars(acc_img_url($d['file_path'])); ?>" target="_blank" class="btn sm outline">View</a></td></tr>
        <?php endforeach; ?>
        </tbody></table>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
document.getElementById('accTabbar').addEventListener('click', function (e) {
    const btn = e.target.closest('button[data-pane]');
    if (!btn) return;
    document.querySelectorAll('#accTabbar button').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.acc-pane').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.querySelector('.acc-pane[data-pane="' + btn.dataset.pane + '"]').classList.add('active');
});
function accVerify(type, id) {
    if (!confirm('Mark this account as fully verified?')) return;
    fetch('account_action.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ action: 'verify', type, id }) })
    .then(r => r.json()).then(d => { if (d.success) { showToast('Account verified.'); setTimeout(() => location.reload(), 500); } else { showToast(d.error || 'Update failed.', true); } })
    .catch(() => showToast('Network error — please try again.', true));
}
</script>

<?php include __DIR__ . '/includes/team_layout_bottom.php'; ?>
