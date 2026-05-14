<?php
// ─────────────────────────────────────────────────────────────
//  AIFAESA — ideias.aifaesa.org
//  Main router / shell
// ─────────────────────────────────────────────────────────────

require_once __DIR__ . '/db.php';
startAppSession();
$isAdmin = isLoggedIn();

// Determine which page to show
$page = isset($_GET['page']) ? trim($_GET['page']) : '';

// Sanitise: only allow alphanumeric, dashes and underscores
if (!preg_match('/^[a-z0-9_\-]*$/i', $page)) {
    $page = '';
}

// Resolve content source
$contentFile   = null;
$pageTitle     = 'Início';
$pageSubtitle  = '';

if ($page === '') {
    // Default welcome page
    $contentFile = __DIR__ . '/content/home.php';
    $pageTitle   = 'Bem-vindo';
} else {
    // Look up article in database
    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM articles WHERE slug = ? AND published = 1');
    $stmt->execute([$page]);
    $article = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($article) {
        $pageTitle    = htmlspecialchars($article['title']);
        $pageSubtitle = htmlspecialchars($article['subtitle'] ?? '');
        // If the article has an attached HTML file, load it
        if ($article['content_file'] && file_exists(__DIR__ . '/uploads/' . $article['content_file'])) {
            $contentFile = __DIR__ . '/uploads/' . $article['content_file'];
        } else {
            $contentFile = __DIR__ . '/content/article_body.php'; // inline content fallback
        }
    } else {
        $contentFile = __DIR__ . '/content/404.php';
        $pageTitle   = 'Página não encontrada';
    }
}

// Fetch sidebar articles (categories + articles)
$db = getDB();
$categories = $db->query(
    'SELECT * FROM categories ORDER BY sort_order ASC, name ASC'
)->fetchAll(PDO::FETCH_ASSOC);

$articlesByCategory = [];
foreach ($categories as $cat) {
    $stmt = $db->prepare(
        'SELECT id, title, slug, subtitle FROM articles
         WHERE category_id = ? AND published = 1
         ORDER BY sort_order ASC, created_at DESC'
    );
    $stmt->execute([$cat['id']]);
    $articlesByCategory[$cat['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Uncategorised articles
$uncategorised = $db->query(
    'SELECT id, title, slug, subtitle FROM articles
     WHERE category_id IS NULL AND published = 1
     ORDER BY sort_order ASC, created_at DESC'
)->fetchAll(PDO::FETCH_ASSOC);

$currentSlug = $page;
?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> — AIFAESA Ideias</title>
    <link rel="icon" type="image/x-icon" href="assets/img/aifaesa.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,300;0,400;0,700;1,300&family=Source+Sans+3:wght@300;400;600;700&display=swap" rel="stylesheet">

    <style>
        /* ── Design tokens ───────────────────────────────────── */
        :root {
            --primary: #1a3a5c;
            --primary-light: #2c5f8a;
            --primary-dark: #0f2338;
            --accent: #c8973a;
            --accent-light: #e8b85a;
            --bg: #f8f6f1;
            --surface: #ffffff;
            --border: #ddd8cc;
            --text: #1e1e1e;
            --text-muted: #5a5a5a;
            --sidebar-w: 300px;
            --topbar-h: 58px;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html,
        body {
            height: 100%;
            margin: 0;
            font-family: 'Source Sans 3', sans-serif;
            background:
                radial-gradient(1200px 500px at 80% -10%, rgba(200, 151, 58, .12), transparent 60%),
                radial-gradient(900px 420px at -10% 120%, rgba(26, 58, 92, .14), transparent 70%),
                var(--bg);
            color: var(--text);
            font-size: 1rem;
            line-height: 1.7;
        }

        body {
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            pointer-events: none;
            background:
                linear-gradient(120deg, rgba(255, 255, 255, .24), transparent 30%),
                repeating-linear-gradient(90deg, rgba(26, 58, 92, .02) 0 1px, transparent 1px 18px);
            z-index: 0;
        }

        /* ── Top bar ─────────────────────────────────────────── */
        .topbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--topbar-h);
            background: rgba(15, 35, 56, .94);
            backdrop-filter: blur(8px);
            border-bottom: 3px solid var(--accent);
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0 1.2rem;
            z-index: 200;
        }

        .topbar .brand {
            display: flex;
            align-items: center;
            gap: .6rem;
            text-decoration: none;
        }

        .topbar .brand-badge {
            background: var(--accent);
            color: var(--primary-dark);
            font-family: 'Merriweather', serif;
            font-weight: 700;
            font-size: .72rem;
            padding: .2rem .55rem;
            border-radius: 2px;
            letter-spacing: .04em;
        }

        .topbar .brand-name {
            color: #fff;
            font-family: 'Merriweather', serif;
            font-size: 1rem;
            font-weight: 700;
        }

        .topbar .brand-name span {
            color: var(--accent-light);
        }

        .topbar .topbar-sub {
            color: rgba(255, 255, 255, .45);
            font-size: .78rem;
            letter-spacing: .06em;
            text-transform: uppercase;
            border-left: 1px solid rgba(255, 255, 255, .15);
            padding-left: .9rem;
            display: none;
        }

        @media (min-width: 640px) {
            .topbar .topbar-sub {
                display: block;
            }
        }

        .topbar-actions {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .topbar .btn-add {
            background: var(--accent);
            color: var(--primary-dark);
            font-weight: 700;
            font-size: .78rem;
            padding: .35rem .85rem;
            border-radius: 3px;
            text-decoration: none;
            letter-spacing: .04em;
            text-transform: uppercase;
            border: none;
            transition: background .15s, transform .15s, box-shadow .15s;
        }

        .topbar .btn-add:hover {
            background: var(--accent-light);
            transform: translateY(-1px);
            box-shadow: 0 8px 18px rgba(0, 0, 0, .22);
        }

        .sidebar-toggle {
            background: none;
            border: none;
            color: rgba(255, 255, 255, .7);
            font-size: 1.25rem;
            cursor: pointer;
            padding: .2rem .4rem;
            display: block;
        }

        @media (min-width: 900px) {
            .sidebar-toggle {
                display: none;
            }
        }

        /* ── Layout shell ────────────────────────────────────── */
        .shell {
            display: flex;
            padding-top: var(--topbar-h);
            min-height: 100vh;
        }

        /* ── Sidebar ─────────────────────────────────────────── */
        .sidebar {
            width: var(--sidebar-w);
            flex-shrink: 0;
            background: linear-gradient(180deg, var(--primary) 0%, #18314f 100%);
            color: #fff;
            position: fixed;
            top: var(--topbar-h);
            left: 0;
            bottom: 0;
            overflow-y: auto;
            z-index: 150;
            transition: transform .25s ease;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, .15) transparent;
        }

        .sidebar.collapsed {
            transform: translateX(calc(-1 * var(--sidebar-w)));
        }

        .sidebar-header {
            padding: 1.2rem 1.2rem .6rem;
            border-bottom: 1px solid rgba(255, 255, 255, .1);
        }

        .sidebar-header h6 {
            font-size: .68rem;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: var(--accent-light);
            margin: 0;
            font-weight: 600;
        }

        /* Home link */
        .sidebar-home {
            display: flex;
            align-items: center;
            gap: .6rem;
            padding: .75rem 1.2rem;
            color: rgba(255, 255, 255, .85);
            text-decoration: none;
            font-size: .9rem;
            font-weight: 600;
            border-bottom: 1px solid rgba(255, 255, 255, .08);
            transition: background .12s, color .12s;
        }

        .sidebar-home:hover,
        .sidebar-home.active {
            background: rgba(255, 255, 255, .1);
            color: var(--accent-light);
        }

        .sidebar-home i {
            font-size: 1rem;
            color: var(--accent);
        }

        /* Category */
        .sidebar-category {
            padding: .9rem 1.2rem .3rem;
        }

        .sidebar-category-label {
            font-size: .65rem;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: var(--accent-light);
            font-weight: 700;
            opacity: .8;
        }

        /* Nav links */
        .sidebar-nav {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .sidebar-nav li a {
            display: flex;
            align-items: flex-start;
            gap: .55rem;
            padding: .55rem 1.2rem .55rem 1.4rem;
            color: rgba(255, 255, 255, .78);
            text-decoration: none;
            font-size: .875rem;
            line-height: 1.35;
            border-left: 3px solid transparent;
            transition: background .12s, color .12s, border-color .12s, transform .12s;
        }

        .sidebar-nav li a i {
            color: rgba(255, 255, 255, .3);
            font-size: .85rem;
            margin-top: .1rem;
            flex-shrink: 0;
        }

        .sidebar-nav li a:hover {
            background: rgba(255, 255, 255, .08);
            color: #fff;
            border-left-color: rgba(200, 151, 58, .5);
            transform: translateX(2px);
        }

        .sidebar-nav li a.active {
            background: rgba(200, 151, 58, .15);
            color: var(--accent-light);
            border-left-color: var(--accent);
        }

        .sidebar-nav li a.active i {
            color: var(--accent);
        }

        /* ── Sidebar overlay (mobile) ───────────────────────── */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .45);
            z-index: 140;
        }

        .sidebar-overlay.visible {
            display: block;
        }

        /* ── Main content ────────────────────────────────────── */
        .main {
            flex: 1;
            margin-left: var(--sidebar-w);
            min-width: 0;
            transition: margin-left .25s ease;
            position: relative;
            z-index: 1;
            animation: contentRise .35s ease-out;
        }

        .main.expanded {
            margin-left: 0;
        }

        @media (max-width: 899px) {
            .main {
                margin-left: 0;
            }

            .sidebar {
                transform: translateX(calc(-1 * var(--sidebar-w)));
            }

            .sidebar.open {
                transform: translateX(0);
            }
        }

        /* ── Content frame ───────────────────────────────────── */
        .content-frame {
            min-height: calc(100vh - var(--topbar-h));
            padding: 0;
        }

        /* ── Article iframe (preserves article's own styling) ── */
        .article-iframe {
            display: block;
            width: 100%;
            border: none;
            min-height: calc(100vh - var(--topbar-h) - 3rem);
        }

        /* ── Footer ──────────────────────────────────────────── */
        .site-footer {
            background: var(--primary-dark);
            color: rgba(255, 255, 255, .45);
            font-size: .78rem;
            text-align: center;
            padding: 1rem;
            border-top: 3px solid var(--accent);
        }

        .site-footer strong {
            color: var(--accent-light);
        }

        @keyframes contentRise {
            from {
                opacity: 0;
                transform: translateY(8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {
                animation: none !important;
                transition: none !important;
                scroll-behavior: auto !important;
            }
        }

        /* ── Sidebar admin controls ─────────────────────────── */
        .sidebar-item-wrap {
            position: relative;
        }

        .sidebar-item-wrap:hover .sidebar-admin-actions {
            opacity: 1;
            pointer-events: auto;
        }

        .sidebar-admin-actions {
            position: absolute;
            right: .5rem;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            gap: .2rem;
            opacity: 0;
            pointer-events: none;
            transition: opacity .15s;
        }

        .sidebar-admin-actions a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            border-radius: 3px;
            font-size: .72rem;
            text-decoration: none;
            transition: background .12s;
        }

        .sidebar-admin-actions a:first-child {
            background: rgba(200, 151, 58, .25);
            color: var(--accent-light);
        }

        .sidebar-admin-actions a:first-child:hover {
            background: rgba(200, 151, 58, .45);
        }

        .sidebar-admin-actions a:last-child {
            background: rgba(180, 60, 60, .25);
            color: #f0a0a0;
        }

        .sidebar-admin-actions a:last-child:hover {
            background: rgba(180, 60, 60, .5);
        }

        /* Sidebar admin mode indicator */
        .sidebar-admin-bar {
            background: rgba(200, 151, 58, .12);
            border-top: 1px solid rgba(200, 151, 58, .25);
            border-bottom: 1px solid rgba(200, 151, 58, .25);
            padding: .45rem 1.2rem;
            font-size: .7rem;
            color: var(--accent-light);
            letter-spacing: .06em;
            display: flex;
            align-items: center;
            gap: .4rem;
        }

        /* Sidebar delete confirm mini-modal */
        .sidebar-del-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .55);
            z-index: 500;
            align-items: center;
            justify-content: center;
        }

        .sidebar-del-modal.show {
            display: flex;
        }

        .sidebar-del-box {
            background: #fff;
            border-radius: 7px;
            padding: 1.8rem;
            max-width: 380px;
            width: 90%;
            border-top: 4px solid #8b2e2e;
            box-shadow: 0 16px 50px rgba(0, 0, 0, .3);
        }

        .sidebar-del-box h4 {
            font-family: 'Merriweather', serif;
            color: #8b2e2e;
            margin: 0 0 .5rem;
            font-size: 1rem;
        }

        .sidebar-del-box p {
            color: #5a5a5a;
            font-size: .9rem;
            margin: 0 0 1.4rem;
        }

        .sidebar-del-actions {
            display: flex;
            gap: .6rem;
        }
    </style>
</head>

<body>

    <!-- ══ TOP BAR ═══════════════════════════════════════════════════ -->
    <header class="topbar">
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
            <i class="bi bi-list"></i>
        </button>
        <a href="/" class="brand">
            <span class="brand-badge">AIFAESA</span>
            <span class="brand-name">ideias<span>.aifaesa.org</span></span>
        </a>
        <span class="topbar-sub">Portal de Propostas e Documentação</span>
        <div class="topbar-actions">
            <?php if ($isAdmin): ?>
                <a href="/add-article.php" class="btn-add"><i class="bi bi-plus-lg"></i> Novo Artigo</a>
                <a href="/admin/" class="btn-add" style="background:rgba(200,151,58,.2);border:1px solid var(--accent);color:var(--accent-light);"><i class="bi bi-shield-lock"></i> Admin</a>
                <a href="/logout.php" class="btn-add" style="background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.15);color:rgba(255,255,255,.65);"><i class="bi bi-box-arrow-right"></i> Sair</a>
            <?php else: ?>
                <a href="/login.php" class="btn-add" style="background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.15);color:rgba(255,255,255,.65);"><i class="bi bi-shield-lock"></i> Admin</a>
            <?php endif; ?>
        </div>
    </header>

    <div class="shell">

        <!-- ══ SIDEBAR ═══════════════════════════════════════════════ -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h6><i class="bi bi-journal-text"></i> &nbsp;Conteúdos</h6>
            </div>

            <?php if ($isAdmin): ?>
                <div class="sidebar-admin-bar">
                    <i class="bi bi-shield-fill-check"></i> Modo Administrador
                </div>
            <?php endif; ?>

            <!-- Home -->
            <a href="/" class="sidebar-home <?= $currentSlug === '' ? 'active' : '' ?>">
                <i class="bi bi-house-door-fill"></i> Início
            </a>

            <!-- Uncategorised -->
            <?php if (!empty($uncategorised)): ?>
                <ul class="sidebar-nav">
                    <?php foreach ($uncategorised as $art): ?>
                        <li class="sidebar-item-wrap">
                            <a href="/?page=<?= urlencode($art['slug']) ?>"
                                class="<?= $currentSlug === $art['slug'] ? 'active' : '' ?>">
                                <i class="bi bi-file-earmark-text"></i>
                                <?= htmlspecialchars($art['title']) ?>
                            </a>
                            <?php if ($isAdmin): ?>
                                <div class="sidebar-admin-actions">
                                    <a href="/admin/edit-article.php?id=<?= $art['id'] ?>" title="Editar"><i class="bi bi-pencil"></i></a>
                                    <a href="#" title="Eliminar" onclick="sidebarDelete(<?= $art['id'] ?>, '<?= addslashes(htmlspecialchars($art['title'])) ?>'); return false;"><i class="bi bi-trash3"></i></a>
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <!-- Categorised -->
            <?php foreach ($categories as $cat): ?>
                <?php if (!empty($articlesByCategory[$cat['id']])): ?>
                    <div class="sidebar-category">
                        <div class="sidebar-category-label" style="display:flex;align-items:center;gap:.4rem;">
                            <span><?= htmlspecialchars($cat['icon'] ?? '') ?> <?= htmlspecialchars($cat['name']) ?></span>
                            <?php if ($isAdmin): ?>
                                <a href="/admin/categories.php?edit=<?= $cat['id'] ?>" title="Editar categoria"
                                    style="margin-left:auto;color:rgba(232,184,90,.5);font-size:.72rem;text-decoration:none;" title="Editar categoria">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <ul class="sidebar-nav">
                        <?php foreach ($articlesByCategory[$cat['id']] as $art): ?>
                            <li class="sidebar-item-wrap">
                                <a href="/?page=<?= urlencode($art['slug']) ?>"
                                    class="<?= $currentSlug === $art['slug'] ? 'active' : '' ?>">
                                    <i class="bi bi-file-earmark-text"></i>
                                    <?= htmlspecialchars($art['title']) ?>
                                </a>
                                <?php if ($isAdmin): ?>
                                    <div class="sidebar-admin-actions">
                                        <a href="/admin/edit-article.php?id=<?= $art['id'] ?>" title="Editar"><i class="bi bi-pencil"></i></a>
                                        <a href="#" title="Eliminar" onclick="sidebarDelete(<?= $art['id'] ?>, '<?= addslashes(htmlspecialchars($art['title'])) ?>'); return false;"><i class="bi bi-trash3"></i></a>
                                    </div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php endforeach; ?>

            <!-- Bottom padding -->
            <div style="height:2rem;"></div>
        </nav>

        <!-- ══ MAIN ══════════════════════════════════════════════════ -->
        <main class="main" id="main">
            <div class="content-frame">
                <?php
                if ($contentFile && file_exists($contentFile)):
                    $ext = strtolower(pathinfo($contentFile, PATHINFO_EXTENSION));
                    // HTML article files: load in an iframe to preserve their own styles
                    if ($ext === 'html' || $ext === 'htm'):
                        // Build the URL path to the file relative to the web root
                        $webPath = str_replace(__DIR__, '', $contentFile);
                        $webPath = str_replace('\\', '/', $webPath); // Windows compat
                ?>
                        <iframe
                            class="article-iframe"
                            src="<?= htmlspecialchars($webPath) ?>"
                            id="articleFrame"
                            title="<?= htmlspecialchars($pageTitle) ?>"
                            scrolling="yes">
                        </iframe>
                        <script>
                            // Auto-resize iframe to its content height to avoid double scrollbars
                            (function() {
                                var frame = document.getElementById('articleFrame');

                                function resize() {
                                    try {
                                        var h = frame.contentDocument.documentElement.scrollHeight;
                                        if (h > 200) frame.style.height = h + 'px';
                                    } catch (e) {}
                                }
                                frame.addEventListener('load', function() {
                                    resize();
                                    // Watch for content changes (e.g. fonts loading late)
                                    setTimeout(resize, 400);
                                    setTimeout(resize, 1200);
                                });
                            })();
                        </script>
                <?php
                    else:
                        // PHP / inline content: include normally
                        if (isset($article)) {
                            $currentArticle = $article;
                        }
                        include $contentFile;
                    endif;
                else:
                    include __DIR__ . '/content/404.php';
                endif;
                ?>
            </div>

            <footer class="site-footer">
                <strong>AIFAESA, I.P.</strong> — Portal de Propostas e Documentação Técnica &nbsp;·&nbsp;
                ideias.aifaesa.org &nbsp;·&nbsp; <?= date('Y') ?>
            </footer>
        </main>

    </div><!-- /shell -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YkgF8OeRe9Z0DFYZ+RP/z86Lh6LPJVTBXXqOv52ij0mJlXa6Sc1U2m7hpXb7M8F"
        crossorigin="anonymous"></script>
    <script>
        // Sidebar toggle (mobile)
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const toggleBtn = document.getElementById('sidebarToggle');
        const main = document.getElementById('main');

        function openSidebar() {
            sidebar.classList.add('open');
            overlay.classList.add('visible');
        }

        function closeSidebar() {
            sidebar.classList.remove('open');
            overlay.classList.remove('visible');
        }

        toggleBtn.addEventListener('click', () => {
            if (sidebar.classList.contains('open')) closeSidebar();
            else openSidebar();
        });
        overlay.addEventListener('click', closeSidebar);

        // Desktop collapse toggle
        let desktopCollapsed = false;
        if (window.innerWidth >= 900) {
            toggleBtn.addEventListener('dblclick', () => {
                desktopCollapsed = !desktopCollapsed;
                sidebar.classList.toggle('collapsed', desktopCollapsed);
                main.classList.toggle('expanded', desktopCollapsed);
            });
        }
    </script>

    <!-- Sidebar delete mini-modal (admin only) -->
    <?php if ($isAdmin): ?>
        <div class="sidebar-del-modal" id="sidebarDelModal">
            <div class="sidebar-del-box">
                <h4><i class="bi bi-trash3"></i> Confirmar eliminação</h4>
                <p id="sidebarDelMsg">Tem a certeza?</p>
                <div class="sidebar-del-actions">
                    <form id="sidebarDelForm" method="POST" action="/admin/articles.php">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="sidebarDelId">
                        <button type="submit"
                            style="background:#8b2e2e;color:#fff;border:none;padding:.5rem 1.1rem;border-radius:4px;font-weight:700;cursor:pointer;font-size:.88rem;">
                            <i class="bi bi-trash3"></i> Eliminar
                        </button>
                    </form>
                    <button onclick="document.getElementById('sidebarDelModal').classList.remove('show')"
                        style="background:none;border:1.5px solid #ccc;color:#555;padding:.5rem 1rem;border-radius:4px;cursor:pointer;font-size:.88rem;">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
        <script>
            function sidebarDelete(id, title) {
                document.getElementById('sidebarDelId').value = id;
                document.getElementById('sidebarDelMsg').textContent =
                    'Eliminar permanentemente o artigo "' + title + '"? O ficheiro HTML também será apagado.';
                document.getElementById('sidebarDelModal').classList.add('show');
            }
            document.getElementById('sidebarDelModal').addEventListener('click', function(e) {
                if (e.target === this) this.classList.remove('show');
            });
        </script>
    <?php endif; ?>
</body>

</html>