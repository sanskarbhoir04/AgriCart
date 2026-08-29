<?php
// =====================================================================
// includes/agri_connect_functions.php
// Shared helpers for Agri-Connect: CSRF, batched (no-N+1) post fetching,
// and the single post-card HTML renderer used by BOTH the initial page
// load and the load_posts.php "Load More" AJAX endpoint, so the two
// never drift out of sync.
// =====================================================================

// ── CSRF ──────────────────────────────────────────────────────────────
if (!function_exists('agri_csrf_token')) {
    function agri_csrf_token() {
        if (empty($_SESSION['agri_csrf'])) {
            $_SESSION['agri_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['agri_csrf'];
    }
}
if (!function_exists('agri_csrf_check')) {
    // Call at the top of every state-changing (POST) endpoint. Exits with
    // a JSON error itself if the token is missing/invalid.
    function agri_csrf_check() {
        $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $expected = $_SESSION['agri_csrf'] ?? '';
        if ($expected === '' || !hash_equals($expected, (string)$sent)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Session expired, कृपया पेज पुन्हा लोड करा.']);
            exit;
        }
    }
}

// ── Category taxonomy shared everywhere ─────────────────────────────
if (!function_exists('agri_category_filter_map')) {
    function agri_category_filter_map() {
        // legacy 'tip'/'news' values map onto the new filter buckets
        return ['question' => 'question', 'crop' => 'crop', 'pest' => 'pest', 'tip' => 'pest',
                'market' => 'market', 'schemes' => 'schemes', 'news' => 'schemes', 'general' => 'general'];
    }
}
if (!function_exists('agri_category_labels')) {
    function agri_category_labels() {
        return ['question' => 'Questions', 'crop' => 'Crop', 'pest' => 'Pest', 'market' => 'Market',
                 'schemes' => 'Schemes', 'tip' => 'Pest', 'news' => 'Schemes', 'general' => 'General'];
    }
}

// ── Batched post fetch (no N+1) ─────────────────────────────────────
// $opts: limit, offset, filter ('all'|'question'|'crop'|'pest'|'market'|'schemes'|'unanswered'|'solved'),
//        search (string), sort ('latest'|'most_liked'|'most_discussed'|'unanswered')
if (!function_exists('agri_fetch_posts')) {
    function agri_fetch_posts($conn, $opts, $currentUserId = null) {
        $limit  = max(1, min(50, (int)($opts['limit'] ?? 10)));
        $offset = max(0, (int)($opts['offset'] ?? 0));
        $filter = $opts['filter'] ?? 'all';
        $search = trim($opts['search'] ?? '');
        $sort   = $opts['sort'] ?? 'latest';

        $where  = ["p.is_approved = 1", "p.deleted_at IS NULL"];
        $params = []; $types = '';

        $catGroups = [
            'question' => ['question'], 'crop' => ['crop'], 'pest' => ['pest', 'tip'],
            'market' => ['market'], 'schemes' => ['schemes', 'news'], 'general' => ['general'],
        ];
        if (isset($catGroups[$filter])) {
            $placeholders = implode(',', array_fill(0, count($catGroups[$filter]), '?'));
            $where[] = "p.category IN ($placeholders)";
            foreach ($catGroups[$filter] as $c) { $params[] = $c; $types .= 's'; }
        } elseif ($filter === 'solved') {
            $where[] = "p.is_solved = 1";
        }

        if ($search !== '') {
            $where[] = "(p.title LIKE ? OR p.body LIKE ? OR p.crop LIKE ? OR p.district LIKE ? OR u.district LIKE ?)";
            $like = '%' . $search . '%';
            for ($i = 0; $i < 5; $i++) { $params[] = $like; $types .= 's'; }
        }

        $having = '';
        if ($filter === 'unanswered') { $having = "HAVING comments_count = 0"; }

        $orderBy = "p.is_pinned DESC, p.id DESC";
        if ($sort === 'most_liked')     { $orderBy = "p.is_pinned DESC, p.likes_count DESC, p.id DESC"; }
        elseif ($sort === 'most_discussed') { $orderBy = "p.is_pinned DESC, comments_count DESC, p.id DESC"; }
        elseif ($sort === 'unanswered')     { $orderBy = "p.is_pinned DESC, comments_count ASC, p.id DESC"; }

        $sql = "SELECT p.id, p.user_id, p.title, p.body, p.category, p.crop, p.district AS post_district,
                       p.images, p.is_solved, p.likes_count, p.created_at,
                       u.full_name, u.district AS user_district, u.role AS author_role, u.qualification AS author_qualification,
                       COUNT(DISTINCT c.id) AS comments_count
                FROM community_posts p
                JOIN users u ON u.id = p.user_id
                LEFT JOIN comments c ON c.post_id = p.id AND c.is_approved = 1 AND c.deleted_at IS NULL
                WHERE " . implode(' AND ', $where) . "
                GROUP BY p.id
                $having
                ORDER BY $orderBy
                LIMIT ? OFFSET ?";

        // fetch one extra row so we can tell the caller whether more pages remain
        $params[] = $limit + 1; $types .= 'i';
        $params[] = $offset;    $types .= 'i';

        $stmt = $conn->prepare($sql);
        if ($types !== '') { $stmt->bind_param($types, ...$params); }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $hasMore = count($rows) > $limit;
        $rows = array_slice($rows, 0, $limit);

        if (empty($rows)) { return ['posts' => [], 'has_more' => false]; }

        $ids = array_map(fn($r) => (int)$r['id'], $rows);
        $idPlaceholders = implode(',', array_fill(0, count($ids), '?'));
        $idTypes = str_repeat('i', count($ids));

        // batch: liked / saved by current user
        $likedSet = []; $savedSet = [];
        if ($currentUserId) {
            $lStmt = $conn->prepare("SELECT post_id FROM post_likes WHERE user_id = ? AND post_id IN ($idPlaceholders)");
            $lStmt->bind_param('i' . $idTypes, $currentUserId, ...$ids);
            $lStmt->execute();
            foreach ($lStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) { $likedSet[(int)$r['post_id']] = true; }

            $sStmt = $conn->prepare("SELECT post_id FROM post_saves WHERE user_id = ? AND post_id IN ($idPlaceholders)");
            $sStmt->bind_param('i' . $idTypes, $currentUserId, ...$ids);
            $sStmt->execute();
            foreach ($sStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) { $savedSet[(int)$r['post_id']] = true; }
        }

        // batch: comments for these posts
        $commentsByPost = array_fill_keys($ids, []);
        $cStmt = $conn->prepare(
            "SELECT c.post_id, c.body, u.full_name, u.role, u.qualification
             FROM comments c JOIN users u ON u.id = c.user_id
             WHERE c.post_id IN ($idPlaceholders) AND c.is_approved = 1 AND c.deleted_at IS NULL ORDER BY c.id ASC"
        );
        $cStmt->bind_param($idTypes, ...$ids);
        $cStmt->execute();
        foreach ($cStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $c) {
            $commentsByPost[(int)$c['post_id']][] = $c;
        }

        $filterMap = agri_category_filter_map();
        foreach ($rows as &$post) {
            $pid = (int)$post['id'];
            $post['district']   = $post['post_district'] ?: $post['user_district'];
            $post['is_expert']  = in_array($post['author_role'], ['expert', 'admin'], true);
            $post['filter_key'] = $filterMap[$post['category']] ?? 'general';
            $post['comments']   = $commentsByPost[$pid] ?? [];
            $post['liked_by_me'] = isset($likedSet[$pid]);
            $post['saved_by_me'] = isset($savedSet[$pid]);
            $post['image'] = null;
            if (!empty($post['images'])) {
                $imgArr = json_decode($post['images'], true);
                if (is_array($imgArr) && !empty($imgArr[0])) { $post['image'] = $imgArr[0]; }
            }
        }
        unset($post);

        return ['posts' => $rows, 'has_more' => $hasMore];
    }
}

// ── Shared post-card renderer (used by initial page render AND load_posts.php) ──
if (!function_exists('agri_render_post_card')) {
    function agri_render_post_card($post, $ctx) {
        $isLoggedIn      = $ctx['isLoggedIn'];
        $currentUserId   = $ctx['currentUserId'];
        $currentUserRole = $ctx['currentUserRole'];
        $basePath        = $ctx['basePath'];
        $labels = agri_category_labels();

        $canMarkSolved = $isLoggedIn && $post['category'] === 'question'
            && ((int)$post['user_id'] === (int)$currentUserId || $currentUserRole === 'admin');

        ob_start();
        ?>
        <div class="post-card"
             data-category="<?php echo htmlspecialchars($post['filter_key']); ?>"
             data-solved="<?php echo $post['is_solved'] ? '1' : '0'; ?>"
             data-comments-count="<?php echo count($post['comments']); ?>"
             data-post-id="<?php echo (int)$post['id']; ?>">
            <div class="post-header">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($post['full_name']); ?>&background=<?php echo $post['is_expert'] ? '2e8b57&color=fff' : 'random'; ?>" alt="User">
                <div class="post-meta">
                    <h4>
                        <?php echo htmlspecialchars($post['full_name']); ?>
                        <?php if ($post['is_expert']): ?>
                            <span class="badge-expert"><i class="fa-solid fa-certificate"></i> <span class="lbl-verified-expert">Verified Expert</span></span>
                        <?php endif; ?>
                        <span class="badge-tag"><?php echo htmlspecialchars($labels[$post['category']] ?? 'General'); ?></span>
                        <?php if ($post['crop']): ?>
                            <span class="badge-crop">🌾 <?php echo htmlspecialchars($post['crop']); ?></span>
                        <?php endif; ?>
                        <?php if ($post['is_solved']): ?>
                            <span class="badge-solved"><i class="fa-solid fa-check"></i> <span class="lbl-solved">Solved</span></span>
                        <?php endif; ?>
                    </h4>
                    <small><?php echo date('d M, H:i', strtotime($post['created_at'])); ?><?php echo $post['district'] ? ' • ' . htmlspecialchars($post['district']) : ''; ?></small>
                </div>
            </div>
            <?php if (!empty($post['title'])): ?>
                <h3 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h3>
            <?php endif; ?>
            <p class="post-body-text"><?php echo nl2br(htmlspecialchars($post['body'])); ?></p>
            <?php if ($post['image']): ?>
                <img class="post-image" src="<?php echo htmlspecialchars($basePath . '/' . $post['image']); ?>" alt="Post image" loading="lazy">
            <?php endif; ?>
            <div class="post-actions">
                <button class="action-btn <?php echo $post['liked_by_me'] ? 'liked' : ''; ?>" onclick="toggleLike(this, <?php echo (int)$post['id']; ?>)">
                    <i class="fa-<?php echo $post['liked_by_me'] ? 'solid' : 'regular'; ?> fa-heart"></i>
                    <span class="count"><?php echo (int)$post['likes_count']; ?></span> <span class="lbl-like">Like</span>
                </button>
                <button class="action-btn" onclick="toggleComments('comments-<?php echo (int)$post['id']; ?>')">
                    <i class="fa-regular fa-comment"></i> <span class="count"><?php echo count($post['comments']); ?></span> <span class="lbl-reply">Comments</span>
                </button>
                <button class="action-btn" onclick="sharePost(this, <?php echo (int)$post['id']; ?>)"><i class="fa-solid fa-share-nodes"></i> <span class="lbl-share">Share</span></button>
                <button class="action-btn <?php echo $post['saved_by_me'] ? 'saved' : ''; ?>" onclick="toggleSave(this, <?php echo (int)$post['id']; ?>)">
                    <i class="fa-<?php echo $post['saved_by_me'] ? 'solid' : 'regular'; ?> fa-bookmark"></i> <span class="lbl-save">Save</span>
                </button>
                <button class="action-btn" onclick="reportPost(this, <?php echo (int)$post['id']; ?>)">
                    <i class="fa-solid fa-flag"></i> <span class="lbl-report">Report</span>
                </button>
                <?php if ($canMarkSolved): ?>
                    <button class="action-btn solved-btn" onclick="markSolved(this, <?php echo (int)$post['id']; ?>)">
                        <i class="fa-solid fa-circle-check"></i> <span class="lbl-mark-solved"><?php echo $post['is_solved'] ? 'Unmark Solved' : 'Mark Solved'; ?></span>
                    </button>
                <?php endif; ?>
            </div>
            <div class="comments-section" id="comments-<?php echo (int)$post['id']; ?>">
                <?php foreach ($post['comments'] as $c): ?>
                <div class="comment-item">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($c['full_name']); ?>&background=<?php echo in_array($c['role'], ['expert','admin'], true) ? '2e8b57&color=fff' : 'random'; ?>" alt="User">
                    <div class="comment-content">
                        <h5><?php echo htmlspecialchars($c['full_name']); ?><?php echo in_array($c['role'], ['expert','admin'], true) ? ' <span class="badge-tag" style="background:var(--primary-green);color:white;">Expert</span>' : ''; ?></h5>
                        <p><?php echo nl2br(htmlspecialchars($c['body'])); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
                <div class="add-reply"><input type="text" placeholder="Write a reply..."><button onclick="postReply(this, <?php echo (int)$post['id']; ?>)"><i class="fa-solid fa-paper-plane"></i></button></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

// ── Notify a user (used e.g. when someone comments on their post) ──
if (!function_exists('agri_notify_user')) {
    function agri_notify_user($conn, $userId, $title, $message, $link = null, $type = 'system') {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('issss', $userId, $title, $message, $type, $link);
        @$stmt->execute();
    }
}

// ── Best-effort auto-translate for farmer-submitted listing names ──
// Farmers can type their product/equipment name in whichever language they're
// comfortable with (English, Marathi, or Hindi) — this translates it into the
// other supported language(s) so listings display correctly regardless of the
// site's active language, without asking the farmer to fill in every language
// themselves. Uses a free, keyless translation endpoint; if it's unreachable
// (offline dev box, blocked outbound network, timeout, etc.) it simply returns
// null and the caller should leave the translated column blank rather than
// fail the listing — translation is a nice-to-have, never a blocker.
if (!function_exists('agri_translate_text')) {
    function agri_translate_text($text, $targetLang, $sourceLang = 'auto') {
        $text = trim((string)$text);
        if ($text === '') return null;
        $url = 'https://translate.googleapis.com/translate_a/single?client=gtx&sl='
             . urlencode($sourceLang) . '&tl=' . urlencode($targetLang) . '&dt=t&q=' . urlencode($text);
        try {
            $context = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
            $raw = @file_get_contents($url, false, $context);
            if ($raw === false) return null;
            $data = json_decode($raw, true);
            if (!is_array($data) || empty($data[0])) return null;
            $translated = '';
            foreach ($data[0] as $chunk) {
                if (isset($chunk[0])) $translated .= $chunk[0];
            }
            $translated = trim($translated);
            return $translated !== '' ? $translated : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}

// ── Auto-fill a listing's other-language name column after insert ──
// Best-effort, non-blocking: called AFTER the main insert already succeeded,
// so a translation failure (or the API being unreachable) never affects
// whether the listing itself gets saved or its approval_status.
if (!function_exists('agri_autofill_name_mr')) {
    function agri_autofill_name_mr($conn, $table, $id, $name) {
        $nameMr = agri_translate_text($name, 'mr');
        if ($nameMr === null) return;
        try {
            $stmt = $conn->prepare("UPDATE {$table} SET name_mr = ? WHERE id = ? AND (name_mr IS NULL OR name_mr = '')");
            $stmt->bind_param('si', $nameMr, $id);
            @$stmt->execute();
        } catch (\Throwable $e) {
            // name_mr column missing on this database — nothing to do.
        }
    }
}

// ── Same last-resort fallback as agri_autofill_name_mr, but for Hindi. ──
// Without this, a listing whose primary translator (agri_translate.php)
// left name_hi identical to the raw original had no second chance the
// way name_mr did — Hindi buyers would see the untranslated name while
// Marathi buyers got a translated one. Mirrors agri_autofill_name_mr
// exactly, just targeting name_hi / 'hi'.
if (!function_exists('agri_autofill_name_hi')) {
    function agri_autofill_name_hi($conn, $table, $id, $name) {
        $nameHi = agri_translate_text($name, 'hi');
        if ($nameHi === null) return;
        try {
            $stmt = $conn->prepare("UPDATE {$table} SET name_hi = ? WHERE id = ? AND (name_hi IS NULL OR name_hi = '')");
            $stmt->bind_param('si', $nameHi, $id);
            @$stmt->execute();
        } catch (\Throwable $e) {
            // name_hi column missing on this database — nothing to do.
        }
    }
}
