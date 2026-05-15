<?php
require_once __DIR__ . '/auth.php';
require_login();

$db   = get_db();
$user = current_user();
$isAdmin = is_admin();

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function js_encode(mixed $value): string
{
    return json_encode(
        $value,
        JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_HEX_TAG |
            JSON_HEX_APOS |
            JSON_HEX_AMP |
            JSON_HEX_QUOT
    );
}

function fetch_articles_for_shell(SQLite3 $db, array $user): array
{
    if ($user['role'] === 'admin') {
        $stmt = $db->query(
            "SELECT a.id, a.title, a.content, a.created_at, a.updated_at, a.institution_id,
                    COALESCE(NULLIF(GROUP_CONCAT(DISTINCT i.name), ''), legacy_i.name, 'Todos os públicos') AS institution_names,
                    u.name AS author_name
             FROM articles a
             LEFT JOIN article_institutions ai ON ai.article_id = a.id
             LEFT JOIN institutions i ON i.id = ai.institution_id
             LEFT JOIN institutions legacy_i ON legacy_i.id = a.institution_id
             LEFT JOIN users u ON u.id = a.created_by
             GROUP BY a.id
             ORDER BY a.created_at DESC"
        );
    } else {
        $institutionId = (int)($user['institution_id'] ?? 0);
        $stmt = $db->prepare(
            "SELECT a.id, a.title, a.content, a.created_at, a.updated_at, a.institution_id,
                    COALESCE(NULLIF(GROUP_CONCAT(DISTINCT i.name), ''), legacy_i.name, 'Todos os públicos') AS institution_names,
                    u.name AS author_name
             FROM articles a
             LEFT JOIN article_institutions ai ON ai.article_id = a.id
             LEFT JOIN institutions i ON i.id = ai.institution_id
             LEFT JOIN institutions legacy_i ON legacy_i.id = a.institution_id
             LEFT JOIN users u ON u.id = a.created_by
             WHERE EXISTS (
                    SELECT 1 FROM article_institutions ai2
                    WHERE ai2.article_id = a.id AND ai2.institution_id = :institution_id
             )
             OR (
                    NOT EXISTS (SELECT 1 FROM article_institutions ai3 WHERE ai3.article_id = a.id)
                    AND (a.institution_id IS NULL OR a.institution_id = :institution_id)
             )
             GROUP BY a.id
             ORDER BY a.created_at DESC"
        );
        $stmt->bindValue(':institution_id', $institutionId, SQLITE3_INTEGER);
        $stmt = $stmt->execute();
    }

    $rows = [];
    while ($row = $stmt->fetchArray(SQLITE3_ASSOC)) {
        $rows[] = $row;
    }
    return $rows;
}

$articles = fetch_articles_for_shell($db, $user);
$defaultArticleId = $articles[0]['id'] ?? null;
?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="theme-color" content="#0d2133" />
    <title>ParseriasAifaesa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Manrope:wght@700;800&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
    <style>
        :root {
            --bg: #edf2f7;
            --surface: rgba(255, 255, 255, 0.88);
            --surface-strong: #ffffff;
            --surface-dark: #0d2133;
            --line: rgba(13, 33, 51, 0.12);
            --text: #132133;
            --muted: #5e6c7f;
            --primary: #0b5cab;
            --primary-strong: #0a4a8b;
            --accent: #b8892f;
            --success: #0c7a4d;
            --danger: #b43a4d;
            --shadow: 0 24px 60px rgba(13, 33, 51, 0.12);
            --sidebar-w: 19rem;
            --topbar-h: 4.25rem;
            color-scheme: light;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            min-height: 100%;
            margin: 0;
        }

        body {
            font-family: "Inter", sans-serif;
            color: var(--text);
            background:
                radial-gradient(1200px 500px at 100% -10%, rgba(11, 92, 171, 0.18), transparent 60%),
                radial-gradient(900px 420px at -15% 110%, rgba(184, 137, 47, 0.18), transparent 62%),
                linear-gradient(180deg, #f8fbff 0%, var(--bg) 100%);
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            background:
                linear-gradient(120deg, rgba(255, 255, 255, 0.22), transparent 30%),
                repeating-linear-gradient(90deg, rgba(13, 33, 51, 0.02) 0 1px, transparent 1px 18px);
            opacity: 0.7;
        }

        .material-symbols-outlined {
            font-variation-settings: "FILL" 0, "wght" 500, "GRAD" 0, "opsz" 24;
        }

        .app-shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr;
            padding-top: var(--topbar-h);
        }

        .topbar {
            position: fixed;
            inset: 0 0 auto 0;
            height: var(--topbar-h);
            z-index: 40;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0 1rem;
            background: rgba(13, 33, 51, 0.96);
            backdrop-filter: blur(18px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
            color: #fff;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            min-width: 0;
            color: inherit;
            text-decoration: none;
        }

        .brand-badge {
            display: grid;
            place-items: center;
            width: 2.6rem;
            height: 2.6rem;
            border-radius: 0.9rem;
            background: linear-gradient(145deg, #f0d08a, #dcae43);
            color: var(--surface-dark);
            box-shadow: 0 10px 24px rgba(184, 137, 47, 0.28);
        }

        .brand-name {
            display: grid;
            line-height: 1.1;
        }

        .brand-name strong {
            font-family: "Manrope", sans-serif;
            font-size: 1rem;
            letter-spacing: 0.01em;
        }

        .brand-name span {
            font-size: 0.72rem;
            color: rgba(255, 255, 255, 0.68);
            text-transform: uppercase;
            letter-spacing: 0.16em;
            margin-top: 0.15rem;
        }

        .topbar-spacer {
            flex: 1;
        }

        .topbar-chip,
        .icon-button {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            border: 0;
            border-radius: 999px;
            padding: 0.65rem 0.9rem;
            background: rgba(255, 255, 255, 0.1);
            color: inherit;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
        }

        .icon-button {
            padding-inline: 0.8rem;
        }

        .layout {
            display: grid;
            grid-template-columns: 1fr;
            min-height: calc(100vh - var(--topbar-h));
        }

        .sidebar {
            position: fixed;
            inset: var(--topbar-h) auto 0 0;
            width: min(100vw, var(--sidebar-w));
            background: linear-gradient(180deg, #10273d 0%, #0d2133 100%);
            color: rgba(255, 255, 255, 0.96);
            overflow-y: auto;
            z-index: 30;
            transform: translateX(-102%);
            transition: transform 220ms ease;
            box-shadow: 18px 0 48px rgba(13, 33, 51, 0.28);
        }

        .sidebar.open {
            transform: translateX(0);
        }

        .sidebar-panel {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .user-card {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 0.85rem;
            align-items: center;
            padding: 1rem;
            margin: 1rem;
            border-radius: 1.15rem;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .avatar {
            width: 3rem;
            height: 3rem;
            border-radius: 999px;
            overflow: hidden;
            display: grid;
            place-items: center;
            background: linear-gradient(145deg, #f0d08a, #dcae43);
            color: var(--surface-dark);
            font-weight: 800;
            flex: none;
        }

        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .user-meta strong {
            display: block;
            font-size: 0.98rem;
        }

        .user-meta small {
            color: rgba(255, 255, 255, 0.65);
            display: block;
            margin-top: 0.15rem;
        }

        .nav-section-title {
            margin: 0 0 0.65rem;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.18em;
            color: rgba(255, 255, 255, 0.55);
        }

        .nav-list {
            display: grid;
            gap: 0.45rem;
            padding: 0 1rem 1rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            width: 100%;
            border: 1px solid transparent;
            border-radius: 1rem;
            padding: 0.8rem 0.85rem;
            color: inherit;
            text-decoration: none;
            background: rgba(255, 255, 255, 0.04);
            cursor: pointer;
            font-weight: 600;
            transition: transform 150ms ease, background 150ms ease, border-color 150ms ease;
            text-align: left;
        }

        .nav-link:hover,
        .nav-link.active {
            transform: translateX(2px);
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(255, 255, 255, 0.12);
        }

        .article-list {
            display: grid;
            gap: 0.45rem;
            padding: 0 1rem 1.2rem;
        }

        .article-item {
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 1rem;
            padding: 0.9rem 0.85rem;
            background: rgba(255, 255, 255, 0.05);
            color: inherit;
            cursor: pointer;
            text-align: left;
        }

        .article-item.active {
            border-color: rgba(240, 208, 138, 0.55);
            background: rgba(240, 208, 138, 0.16);
        }

        .article-item strong {
            display: block;
            font-size: 0.96rem;
        }

        .article-item small {
            display: block;
            color: rgba(255, 255, 255, 0.62);
            margin-top: 0.2rem;
        }

        .main {
            padding: 1rem;
            position: relative;
            z-index: 1;
        }

        .surface {
            background: var(--surface);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.7);
            box-shadow: var(--shadow);
            border-radius: 1.5rem;
        }

        .hero-panel {
            padding: 1rem;
            display: grid;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .hero-kicker {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            width: fit-content;
            padding: 0.45rem 0.7rem;
            border-radius: 999px;
            background: rgba(11, 92, 171, 0.08);
            color: var(--primary-strong);
            font-weight: 700;
            font-size: 0.8rem;
        }

        .hero-panel h1,
        .section-head h2,
        .panel h3 {
            margin: 0;
            font-family: "Manrope", sans-serif;
            letter-spacing: -0.02em;
        }

        .hero-panel h1 {
            font-size: clamp(1.6rem, 5vw, 2.5rem);
            line-height: 1.05;
        }

        .hero-panel p {
            margin: 0;
            color: var(--muted);
            max-width: 70ch;
        }

        .content-grid {
            display: grid;
            gap: 1rem;
        }

        .panel {
            padding: 1rem;
            border-radius: 1.35rem;
            background: rgba(255, 255, 255, 0.92);
            border: 1px solid rgba(13, 33, 51, 0.08);
            box-shadow: 0 14px 30px rgba(13, 33, 51, 0.08);
        }

        .section-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .section-head p {
            margin: 0.3rem 0 0;
            color: var(--muted);
            font-size: 0.92rem;
        }

        .btn-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
        }

        .btn {
            appearance: none;
            border: 0;
            border-radius: 999px;
            padding: 0.8rem 1rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
        }

        .btn-ghost {
            background: rgba(11, 92, 171, 0.08);
            color: var(--primary-strong);
        }

        .btn-danger {
            background: rgba(180, 58, 77, 0.1);
            color: var(--danger);
        }

        .btn-soft {
            background: rgba(13, 33, 51, 0.06);
            color: var(--text);
        }

        .field-grid {
            display: grid;
            gap: 0.8rem;
        }

        .field {
            display: grid;
            gap: 0.35rem;
        }

        .field label {
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--muted);
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .field input,
        .field textarea,
        .field select {
            width: 100%;
            border-radius: 1rem;
            border: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.96);
            padding: 0.9rem 1rem;
            font: inherit;
            color: var(--text);
        }

        .field textarea {
            min-height: 10rem;
            resize: vertical;
        }

        .chips {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.45rem 0.7rem;
            border-radius: 999px;
            background: rgba(11, 92, 171, 0.08);
            color: var(--primary-strong);
            font-weight: 600;
            font-size: 0.85rem;
        }

        .cards {
            display: grid;
            gap: 0.8rem;
        }

        .list-card {
            border: 1px solid rgba(13, 33, 51, 0.08);
            border-radius: 1.1rem;
            padding: 0.9rem;
            background: rgba(255, 255, 255, 0.9);
            display: grid;
            gap: 0.75rem;
        }

        .list-card-head {
            display: flex;
            justify-content: space-between;
            gap: 0.75rem;
            align-items: flex-start;
        }

        .list-card-head h4 {
            margin: 0;
            font-size: 1rem;
        }

        .list-card-head p {
            margin: 0.2rem 0 0;
            color: var(--muted);
            font-size: 0.9rem;
        }

        .list-meta {
            color: var(--muted);
            font-size: 0.85rem;
        }

        .divider {
            height: 1px;
            background: rgba(13, 33, 51, 0.08);
            margin: 0.15rem 0;
        }

        .article-body {
            padding: 1rem;
            border-radius: 1.2rem;
            background: linear-gradient(180deg, rgba(11, 92, 171, 0.05), rgba(11, 92, 171, 0.02));
            border: 1px solid rgba(11, 92, 171, 0.08);
            white-space: pre-wrap;
            line-height: 1.75;
        }

        .comment {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 0.75rem;
            padding: 0.85rem 0;
            border-top: 1px solid rgba(13, 33, 51, 0.08);
        }

        .comment:first-child {
            border-top: 0;
            padding-top: 0;
        }

        .comment h5 {
            margin: 0;
            font-size: 0.94rem;
        }

        .comment p {
            margin: 0.25rem 0 0;
            line-height: 1.6;
        }

        .comment small {
            color: var(--muted);
        }

        .empty {
            padding: 1rem;
            border-radius: 1rem;
            background: rgba(13, 33, 51, 0.04);
            color: var(--muted);
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.84rem;
            font-weight: 700;
            color: var(--success);
            padding: 0.4rem 0.6rem;
            border-radius: 999px;
            background: rgba(12, 122, 77, 0.08);
        }

        .hidden {
            display: none !important;
        }

        .overlay {
            position: fixed;
            inset: var(--topbar-h) 0 0 0;
            background: rgba(13, 33, 51, 0.34);
            backdrop-filter: blur(2px);
            z-index: 20;
            opacity: 0;
            pointer-events: none;
            transition: opacity 180ms ease;
        }

        .overlay.open {
            opacity: 1;
            pointer-events: auto;
        }

        @media (min-width: 980px) {
            .app-shell {
                grid-template-columns: var(--sidebar-w) 1fr;
            }

            .sidebar {
                transform: none;
                position: sticky;
                top: var(--topbar-h);
                height: calc(100vh - var(--topbar-h));
                width: auto;
            }

            .overlay,
            .menu-button {
                display: none !important;
            }

            .main {
                padding: 1.25rem;
            }

            .content-grid {
                grid-template-columns: minmax(0, 1.6fr) minmax(20rem, 0.8fr);
            }

            .hero-panel,
            .panel {
                padding: 1.25rem;
            }
        }

        @media (max-width: 979px) {
            .sidebar {
                max-width: 20rem;
            }
        }
    </style>
</head>

<body>
    <header class="topbar">
        <button class="icon-button menu-button" type="button" id="menuButton" aria-label="Abrir menu">
            <span class="material-symbols-outlined">menu</span>
        </button>
        <a class="brand" href="app.php">
            <span class="brand-badge"><span class="material-symbols-outlined">article</span></span>
            <span class="brand-name">
                <strong>ParseriasAifaesa</strong>
                <span>mobile-first portal</span>
            </span>
        </a>
        <div class="topbar-spacer"></div>
        <div class="topbar-chip">
            <span class="material-symbols-outlined">badge</span>
            <?= h($user['role']) ?>
        </div>
        <a class="topbar-chip" href="api/logout.php">
            <span class="material-symbols-outlined">logout</span>
            Sair
        </a>
    </header>

    <div class="overlay" id="overlay"></div>

    <div class="app-shell">
        <aside class="sidebar" id="sidebar">
            <div class="user-card">
                <div class="avatar">
                    <?php if (!empty($user['photo'])): ?>
                        <img src="uploads/<?= h($user['photo']) ?>" alt="<?= h($user['name']) ?>" />
                    <?php else: ?>
                        <span><?= strtoupper(substr($user['name'], 0, 1)) ?></span>
                    <?php endif; ?>
                </div>
                <div class="user-meta">
                    <strong><?= h($user['name']) ?></strong>
                    <small><?= h($user['institution_name'] ?? 'Sem instituição') ?></small>
                    <small><?= h($user['position'] ?: 'Utilizador') ?></small>
                </div>
            </div>

            <div class="sidebar-panel">
                <p class="nav-section-title">Navegação</p>
                <div class="nav-list">
                    <button class="nav-link active" type="button" data-view="articles-home">
                        <span class="material-symbols-outlined">article</span>
                        Artigos
                    </button>
                    <?php if ($isAdmin): ?>
                        <button class="nav-link" type="button" data-view="users-management">
                            <span class="material-symbols-outlined">group</span>
                            Users Management
                        </button>
                        <button class="nav-link" type="button" data-view="institutions-management">
                            <span class="material-symbols-outlined">apartment</span>
                            Institutions Management
                        </button>
                        <button class="nav-link" type="button" data-view="articles-management">
                            <span class="material-symbols-outlined">edit_document</span>
                            Articles Management
                        </button>
                    <?php else: ?>
                        <button class="nav-link" type="button" data-view="profile-view">
                            <span class="material-symbols-outlined">person</span>
                            Profile
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="sidebar-panel">
                <p class="nav-section-title">Lista de artigos</p>
            </div>
            <div class="article-list" id="articleList">
                <?php if ($articles): ?>
                    <?php foreach ($articles as $article): ?>
                        <button class="article-item<?= (int)$article['id'] === (int)$defaultArticleId ? ' active' : '' ?>" type="button" data-article-id="<?= (int)$article['id'] ?>">
                            <strong><?= h($article['title']) ?></strong>
                            <small><?= h($article['institution_names'] ?: 'Todos os públicos') ?></small>
                        </button>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty" style="margin: 0 1rem 1rem;">Sem artigos disponíveis para a sua instituição.</div>
                <?php endif; ?>
            </div>
        </aside>

        <main class="main">
            <section class="surface hero-panel">
                <div class="hero-kicker">
                    <span class="material-symbols-outlined" style="font-size: 18px;">stylus_note</span>
                    Portal institucional
                </div>
                <h1>Conteúdo, comentários e gestão num fluxo adaptado a mobile.</h1>
                <p>Os utilizadores veem apenas conteúdos da sua instituição. O administrador gere utilizadores, artigos, comentários e o catálogo de instituições sem sair do mesmo ecrã.</p>
            </section>

            <div class="content-grid">
                <section class="panel" id="primaryPanel">
                    <div class="section-head">
                        <div>
                            <h2 id="panelTitle">Artigos</h2>
                            <p id="panelSubtitle">Escolha um artigo na barra lateral para ler o conteúdo e ver os comentários.</p>
                        </div>
                        <div class="btn-row" id="panelActions"></div>
                    </div>
                    <div id="panelBody"></div>
                </section>

                <aside class="panel">
                    <div class="section-head">
                        <div>
                            <h3>Instituições</h3>
                            <p>Atualizadas sempre que um utilizador é criado ou editado.</p>
                        </div>
                    </div>
                    <div class="chips" id="institutionChips">
                        <span class="chip"><?= h($user['institution_name'] ?? 'Sem instituição') ?></span>
                    </div>
                </aside>
            </div>
        </main>
    </div>

    <script>
        const state = {
            currentUser: <?= js_encode($user) ?>,
            isAdmin: <?= $isAdmin ? 'true' : 'false' ?>,
            articles: <?= js_encode($articles) ?>,
            institutions: [],
            activeView: 'articles-home',
            activeArticleId: <?= $defaultArticleId !== null ? (int) $defaultArticleId : 'null' ?>,
        };

        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        const menuButton = document.getElementById('menuButton');
        const panelTitle = document.getElementById('panelTitle');
        const panelSubtitle = document.getElementById('panelSubtitle');
        const panelActions = document.getElementById('panelActions');
        const panelBody = document.getElementById('panelBody');
        const institutionChips = document.getElementById('institutionChips');

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, (ch) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            } [ch]));
        }

        function formatDate(value) {
            if (!value) return '';
            const date = new Date(String(value).replace(' ', 'T'));
            if (Number.isNaN(date.getTime())) return value;
            return date.toLocaleString('pt-PT', {
                dateStyle: 'medium',
                timeStyle: 'short'
            });
        }

        function photoUrl(photo) {
            return photo ? `uploads/${encodeURIComponent(photo)}` : '';
        }

        function avatarMarkup(user) {
            const letter = escapeHtml((user.name || '?').slice(0, 1).toUpperCase());
            if (user.photo) {
                return `<img src="${photoUrl(user.photo)}" alt="${escapeHtml(user.name || 'Utilizador')}" />`;
            }
            return `<span>${letter}</span>`;
        }

        function closeSidebar() {
            sidebar.classList.remove('open');
            overlay.classList.remove('open');
        }

        function openSidebar() {
            sidebar.classList.add('open');
            overlay.classList.add('open');
        }

        menuButton?.addEventListener('click', () => {
            if (sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
        overlay.addEventListener('click', closeSidebar);

        async function requestJson(url, options = {}) {
            const response = await fetch(url, options);
            const payload = await response.json().catch(() => ({}));
            if (!response.ok || payload.error) {
                throw new Error(payload.error || 'Falha ao comunicar com o servidor.');
            }
            return payload;
        }

        function setActiveNav(view) {
            state.activeView = view;
            document.querySelectorAll('.nav-link').forEach((button) => {
                button.classList.toggle('active', button.dataset.view === view);
            });
            if (view !== 'articles-home') {
                document.querySelectorAll('.article-item').forEach((button) => {
                    button.classList.remove('active');
                });
            }
        }

        function setPanelHeader(title, subtitle, actions = '') {
            panelTitle.textContent = title;
            panelSubtitle.textContent = subtitle;
            panelActions.innerHTML = actions;
        }

        function renderInstitutionChips(items) {
            if (!items.length) {
                institutionChips.innerHTML = '<span class="chip">Sem instituições registadas</span>';
                return;
            }
            institutionChips.innerHTML = items.map((item) => `<span class="chip">${escapeHtml(item.name)}</span>`).join('');
        }

        async function loadInstitutions() {
            if (!state.isAdmin) {
                renderInstitutionChips([{
                    name: state.currentUser.institution_name || 'Sem instituição'
                }]);
                return;
            }
            const data = await requestJson('api/institutions.php');
            state.institutions = data.institutions || [];
            renderInstitutionChips(state.institutions);
        }

        function renderArticleListActive(articleId) {
            document.querySelectorAll('.article-item').forEach((button) => {
                button.classList.toggle('active', Number(button.dataset.articleId) === Number(articleId));
            });
        }

        function renderArticleView(article, comments) {
            const commentHtml = comments.length ?
                comments.map((comment) => `
          <div class="comment">
            <div class="avatar" style="width: 2.4rem; height: 2.4rem;">
              ${comment.user_photo ? `<img src="${photoUrl(comment.user_photo)}" alt="${escapeHtml(comment.user_name)}" />` : `<span>${escapeHtml((comment.user_name || '?').slice(0, 1).toUpperCase())}</span>`}
            </div>
            <div>
              <h5>${escapeHtml(comment.user_name)}</h5>
              <small>${escapeHtml(formatDate(comment.created_at))}</small>
              <p>${escapeHtml(comment.comment)}</p>
            </div>
          </div>
        `).join('') :
                '<div class="empty">Ainda não existem comentários neste artigo.</div>';

            setPanelHeader(
                article.title,
                `${article.institution_names || 'Todos os públicos'} · ${article.author_name || 'Autor desconhecido'} · ${formatDate(article.created_at)}`,
                `<button class="btn btn-ghost" type="button" id="refreshArticleButton"><span class="material-symbols-outlined">refresh</span>Atualizar</button>`
            );

            panelBody.innerHTML = `
        <div class="cards">
          <article class="list-card">
            <div class="list-card-head">
              <div>
                <h4>${escapeHtml(article.title)}</h4>
                                <p>${escapeHtml(article.institution_names || 'Todos os públicos')}</p>
              </div>
              <span class="status"><span class="material-symbols-outlined" style="font-size: 16px;">check_circle</span>Publicado</span>
            </div>
            <div class="divider"></div>
            <div class="article-body">${escapeHtml(article.content || 'Sem conteúdo.')}</div>
          </article>

          <article class="list-card">
            <div class="list-card-head">
              <div>
                <h4>Comentários</h4>
                <p>Leia e responda ao artigo selecionado.</p>
              </div>
            </div>
            <div id="commentsList">${commentHtml}</div>
            <form class="field-grid" id="commentForm">
              <div class="field">
                <label for="commentText">Novo comentário</label>
                <textarea id="commentText" name="comment" placeholder="Escreva a sua opinião..." required></textarea>
              </div>
              <div class="btn-row">
                <button class="btn btn-primary" type="submit"><span class="material-symbols-outlined">send</span>Publicar comentário</button>
              </div>
            </form>
          </article>
        </div>
      `;

            document.getElementById('refreshArticleButton').addEventListener('click', () => loadArticle(article.id));
            document.getElementById('commentForm').addEventListener('submit', async (event) => {
                event.preventDefault();
                const commentText = document.getElementById('commentText').value.trim();
                if (!commentText) return;

                const payload = await requestJson('api/comments.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        article_id: article.id,
                        comment: commentText
                    }),
                });

                const commentsList = document.getElementById('commentsList');
                const appended = `
          <div class="comment">
            <div class="avatar" style="width: 2.4rem; height: 2.4rem;">
              ${payload.user_photo ? `<img src="${photoUrl(payload.user_photo)}" alt="${escapeHtml(payload.user_name)}" />` : `<span>${escapeHtml((payload.user_name || '?').slice(0, 1).toUpperCase())}</span>`}
            </div>
            <div>
              <h5>${escapeHtml(payload.user_name)}</h5>
              <small>${escapeHtml(formatDate(payload.created_at))}</small>
              <p>${escapeHtml(commentText)}</p>
            </div>
          </div>
        `;
                if (commentsList.querySelector('.empty')) {
                    commentsList.innerHTML = appended;
                } else {
                    commentsList.insertAdjacentHTML('beforeend', appended);
                }
                event.target.reset();
            });
        }

        async function loadArticle(articleId) {
            setActiveNav('articles-home');
            renderArticleListActive(articleId);
            const [articlePayload, commentPayload] = await Promise.all([
                requestJson(`api/articles.php?action=get&id=${encodeURIComponent(articleId)}`),
                requestJson(`api/comments.php?article_id=${encodeURIComponent(articleId)}`),
            ]);
            state.activeArticleId = articleId;
            renderArticleView(articlePayload.article, commentPayload.comments || []);
            closeSidebar();
        }

        function renderArticleHome() {
            setActiveNav('articles-home');
            setPanelHeader(
                'Artigos',
                'Os artigos disponíveis abaixo respeitam a instituição do utilizador autenticado.',
                state.articles.length ? `<button class="btn btn-ghost" type="button" id="reloadArticlesButton"><span class="material-symbols-outlined">refresh</span>Atualizar lista</button>` : ''
            );

            const listHtml = state.articles.length ?
                state.articles.map((article) => `
          <button class="list-card" type="button" data-open-article="${article.id}" style="text-align:left; cursor:pointer;">
            <div class="list-card-head">
              <div>
                <h4>${escapeHtml(article.title)}</h4>
                                <p>${escapeHtml(article.institution_names || 'Todos os públicos')}</p>
              </div>
              <span class="status"><span class="material-symbols-outlined" style="font-size: 16px;">arrow_forward</span>Ler</span>
            </div>
            <div class="list-meta">${escapeHtml(article.author_name || 'Autor desconhecido')} · ${escapeHtml(formatDate(article.created_at))}</div>
          </button>
        `).join('') :
                '<div class="empty">Ainda não há artigos disponíveis para a sua instituição.</div>';

            panelBody.innerHTML = `<div class="cards">${listHtml}</div>`;

            document.getElementById('reloadArticlesButton')?.addEventListener('click', () => renderArticleHome());
            document.querySelectorAll('[data-open-article]').forEach((button) => {
                button.addEventListener('click', () => loadArticle(button.dataset.openArticle));
            });
        }

        function renderUsersManagement(users, institutions) {
            setActiveNav('users-management');
            setPanelHeader('Users Management', 'Add, edit and delete users. The institution field also refreshes the institution list after every registration.', `
        <button class="btn btn-ghost" type="button" id="refreshUsersButton"><span class="material-symbols-outlined">refresh</span>Refresh</button>
      `);

            const institutionOptions = institutions.map((item) => `<option value="${item.id}">${escapeHtml(item.name)}</option>`).join('');
            const usersHtml = users.length ?
                users.map((item) => `
          <div class="list-card">
            <div class="list-card-head">
              <div>
                <h4>${escapeHtml(item.name)}</h4>
                <p>${escapeHtml(item.position || 'Sem cargo')} · ${escapeHtml(item.institution_name || item.institution || 'Sem instituição')}</p>
              </div>
              <span class="chip">${escapeHtml(item.role)}</span>
            </div>
            <div class="list-meta">${escapeHtml(item.email)} · ${escapeHtml(item.whatsapp || 'Sem WhatsApp')}</div>
            <div class="btn-row">
              <button class="btn btn-ghost" type="button" data-edit-user="${item.id}"><span class="material-symbols-outlined">edit</span>Edit</button>
              <button class="btn btn-danger" type="button" data-delete-user="${item.id}"><span class="material-symbols-outlined">delete</span>Delete</button>
            </div>
          </div>
        `).join('') :
                '<div class="empty">No users registered yet.</div>';

            panelBody.innerHTML = `
        <div class="cards">
          <form class="list-card field-grid" id="userForm" enctype="multipart/form-data">
            <div class="list-card-head">
              <div>
                <h4 id="userFormTitle">Add user</h4>
                                <p>Select one institution from the registered list.</p>
              </div>
              <span class="chip">Admin only</span>
            </div>
            <input type="hidden" name="action" value="create" />
            <input type="hidden" name="id" value="" />
            <div class="field-grid" style="grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));">
              <div class="field"><label for="userName">Name</label><input id="userName" name="name" required /></div>
              <div class="field"><label for="userPosition">Position</label><input id="userPosition" name="position" /></div>
              <div class="field"><label for="userInstitution">Institution</label><select id="userInstitution" name="institution_id"><option value="">Select institution</option>${institutionOptions}</select></div>
              <div class="field"><label for="userWhatsapp">WhatsApp number</label><input id="userWhatsapp" name="whatsapp" /></div>
              <div class="field"><label for="userEmail">Email</label><input id="userEmail" name="email" type="email" required /></div>
              <div class="field"><label for="userPhoto">Photo</label><input id="userPhoto" name="photo" type="file" accept="image/*" /></div>
              <div class="field"><label for="userRole">Role</label><select id="userRole" name="role"><option value="user">user</option><option value="admin">admin</option></select></div>
              <div class="field"><label for="userPassword">Password</label><input id="userPassword" name="password" type="password" required /></div>
            </div>
            <div class="btn-row">
              <button class="btn btn-primary" type="submit"><span class="material-symbols-outlined">person_add</span>Save user</button>
              <button class="btn btn-soft" type="button" id="resetUserFormButton">Reset</button>
            </div>
          </form>

          <div class="list-card">
            <div class="list-card-head">
              <div>
                <h4>Institutions</h4>
                <p>Refreshed after each user registration or edit.</p>
              </div>
            </div>
            <div class="chips" id="adminInstitutionChips"></div>
          </div>

          <div class="list-card">
            <div class="list-card-head">
              <div>
                <h4>Registered users</h4>
                <p>Manage access and profile details.</p>
              </div>
            </div>
            <div class="cards">${usersHtml}</div>
          </div>
        </div>
      `;

            const adminInstitutionChips = document.getElementById('adminInstitutionChips');
            adminInstitutionChips.innerHTML = institutions.length ?
                institutions.map((item) => `<span class="chip">${escapeHtml(item.name)}</span>`).join('') :
                '<span class="chip">No institutions yet</span>';

            document.getElementById('userForm').addEventListener('submit', async (event) => {
                event.preventDefault();
                const formElements = event.target.elements;
                const formData = new FormData(event.target);
                const response = await requestJson('api/users.php', {
                    method: 'POST',
                    body: formData,
                });

                if (response.success) {
                    event.target.reset();
                    formElements.action.value = 'create';
                    formElements.id.value = '';
                    document.getElementById('userFormTitle').textContent = 'Add user';
                    formElements.password.required = true;
                    await Promise.all([loadAdminUsers(), loadInstitutions()]);
                }
            });

            document.getElementById('resetUserFormButton').addEventListener('click', () => {
                const form = document.getElementById('userForm');
                const elements = form.elements;
                form.reset();
                elements.action.value = 'create';
                elements.id.value = '';
                elements.password.required = true;
                document.getElementById('userFormTitle').textContent = 'Add user';
            });

            document.getElementById('refreshUsersButton').addEventListener('click', async () => {
                await Promise.all([loadAdminUsers(), loadInstitutions()]);
            });

            document.querySelectorAll('[data-edit-user]').forEach((button) => {
                button.addEventListener('click', () => {
                    const item = users.find((user) => Number(user.id) === Number(button.dataset.editUser));
                    if (!item) return;
                    const form = document.getElementById('userForm');
                    const elements = form.elements;
                    elements.action.value = 'update';
                    elements.id.value = item.id;
                    elements.name.value = item.name || '';
                    elements.position.value = item.position || '';
                    elements.institution_id.value = item.institution_id || '';
                    elements.whatsapp.value = item.whatsapp || '';
                    elements.email.value = item.email || '';
                    elements.role.value = item.role || 'user';
                    elements.password.value = '';
                    elements.password.required = false;
                    document.getElementById('userFormTitle').textContent = `Edit user #${item.id}`;
                    closeSidebar();
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                });
            });

            document.querySelectorAll('[data-delete-user]').forEach((button) => {
                button.addEventListener('click', async () => {
                    if (!confirm('Delete this user?')) return;
                    await requestJson('api/users.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'delete',
                            id: Number(button.dataset.deleteUser)
                        }),
                    });
                    await Promise.all([loadAdminUsers(), loadInstitutions()]);
                });
            });
        }

        function renderInstitutionsManagement(institutions) {
            setActiveNav('institutions-management');
            setPanelHeader('Institutions Management', 'Add, edit and delete institutions used by users and article targeting.', `
                <button class="btn btn-ghost" type="button" id="refreshInstitutionsButton"><span class="material-symbols-outlined">refresh</span>Refresh</button>
            `);

            const institutionsHtml = institutions.length ?
                institutions.map((item) => `
                    <div class="list-card">
                        <div class="list-card-head">
                            <div>
                                <h4>${escapeHtml(item.name)}</h4>
                                <p>ID #${item.id}</p>
                            </div>
                            <span class="chip">Institution</span>
                        </div>
                        <div class="btn-row">
                            <button class="btn btn-ghost" type="button" data-edit-institution="${item.id}" data-institution-name="${escapeHtml(item.name)}"><span class="material-symbols-outlined">edit</span>Edit</button>
                            <button class="btn btn-danger" type="button" data-delete-institution="${item.id}"><span class="material-symbols-outlined">delete</span>Delete</button>
                        </div>
                    </div>
                `).join('') :
                '<div class="empty">No institutions registered yet.</div>';

            panelBody.innerHTML = `
                <div class="cards">
                    <form class="list-card field-grid" id="institutionForm">
                        <div class="list-card-head">
                            <div>
                                <h4 id="institutionFormTitle">Add institution</h4>
                                <p>Use this module to keep institution options centralized.</p>
                            </div>
                            <span class="chip">Admin only</span>
                        </div>
                        <input type="hidden" name="action" value="create" />
                        <input type="hidden" name="id" value="" />
                        <div class="field"><label for="institutionName">Institution name</label><input id="institutionName" name="name" required /></div>
                        <div class="btn-row">
                            <button class="btn btn-primary" type="submit"><span class="material-symbols-outlined">save</span>Save institution</button>
                            <button class="btn btn-soft" type="button" id="resetInstitutionFormButton">Reset</button>
                        </div>
                    </form>

                    <div class="list-card">
                        <div class="list-card-head">
                            <div>
                                <h4>Registered institutions</h4>
                                <p>These values appear in user registration and article targeting.</p>
                            </div>
                        </div>
                        <div class="cards">${institutionsHtml}</div>
                    </div>
                </div>
            `;

            document.getElementById('institutionForm').addEventListener('submit', async (event) => {
                event.preventDefault();
                const formElements = event.target.elements;
                const payload = Object.fromEntries(new FormData(event.target).entries());
                const response = await requestJson('api/institutions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload),
                });

                if (response.success) {
                    event.target.reset();
                    formElements.action.value = 'create';
                    formElements.id.value = '';
                    document.getElementById('institutionFormTitle').textContent = 'Add institution';
                    await Promise.all([loadInstitutions(), refreshSidebarArticles()]);
                    renderInstitutionsManagement(state.institutions);
                }
            });

            document.getElementById('refreshInstitutionsButton').addEventListener('click', async () => {
                await Promise.all([loadInstitutions(), refreshSidebarArticles()]);
                renderInstitutionsManagement(state.institutions);
            });

            document.getElementById('resetInstitutionFormButton').addEventListener('click', () => {
                const form = document.getElementById('institutionForm');
                const elements = form.elements;
                form.reset();
                elements.action.value = 'create';
                elements.id.value = '';
                document.getElementById('institutionFormTitle').textContent = 'Add institution';
            });

            document.querySelectorAll('[data-edit-institution]').forEach((button) => {
                button.addEventListener('click', () => {
                    const form = document.getElementById('institutionForm');
                    const elements = form.elements;
                    elements.action.value = 'update';
                    elements.id.value = button.dataset.editInstitution;
                    elements.name.value = button.dataset.institutionName;
                    document.getElementById('institutionFormTitle').textContent = `Edit institution #${button.dataset.editInstitution}`;
                });
            });

            document.querySelectorAll('[data-delete-institution]').forEach((button) => {
                button.addEventListener('click', async () => {
                    if (!confirm('Delete this institution?')) return;
                    await requestJson('api/institutions.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'delete',
                            id: Number(button.dataset.deleteInstitution),
                        }),
                    });
                    await Promise.all([loadInstitutions(), refreshSidebarArticles()]);
                    renderInstitutionsManagement(state.institutions);
                });
            });
        }

        function renderArticlesManagement(articles, institutions) {
            setActiveNav('articles-management');
            setPanelHeader('Articles Management', 'Add, edit and delete articles. Target audience is selected with institution checkboxes.', `
        <button class="btn btn-ghost" type="button" id="refreshArticlesAdminButton"><span class="material-symbols-outlined">refresh</span>Refresh</button>
      `);

            const CERT_TEMPLATE_TITLE = 'Training Certifications for Product Quality and Compliance';
            const CERT_TEMPLATE_BODY = `Background:\nMany economic operators still lack formal technical certifications for product handling, safety controls, and traceability practices.\n\nProposal:\nAIFAESA and INDIMO should coordinate a recurring training certification program focused on quality assurance, compliance requirements, and practical inspection readiness.\n\nImplementation Actions:\n1. Map high-priority operators by sector and risk profile.\n2. Define competency modules and minimum certification criteria.\n3. Deliver phased trainings with assessment and re-certification cycles.\n4. Publish certified operator status for transparency and market confidence.\n\nExpected Result:\nStronger product quality controls, improved compliance levels, and a consistent certification culture across operators.`;

            const REG_TEMPLATE_TITLE = 'Compulsory Product Registration Database for All Economic Operators';
            const REG_TEMPLATE_BODY = `Background:\nProduct registration remains uneven and difficult to verify across sectors, which creates risks for market surveillance and consumer protection.\n\nProposal:\nAIFAESA and DNRKPK-MCI should propose and enforce compulsory product registration by all economic operators in a government-managed database.\n\nImplementation Actions:\n1. Establish legal requirement for mandatory product registration before market entry.\n2. Define standard data fields: product identity, operator, origin, compliance documents, and validity period.\n3. Create verification workflows linking registration status to inspection and licensing processes.\n4. Implement penalties for non-registration and incentives for early compliance.\n\nExpected Result:\nA reliable national product registry that supports enforcement, improves traceability, and strengthens public trust.`;

            const defaultInstitution = institutions.find((item) => String(item.name).toUpperCase() === 'AIFAESA');
            const defaultInstitutionId = defaultInstitution ? Number(defaultInstitution.id) : null;
            const institutionCheckboxes = institutions.map((item) => {
                const checked = defaultInstitutionId !== null && Number(item.id) === defaultInstitutionId ? 'checked' : '';
                return `<label style="display:flex; align-items:center; gap:0.5rem; padding:0.35rem 0;"><input type="checkbox" name="institution_ids[]" value="${item.id}" ${checked} />${escapeHtml(item.name)}</label>`;
            }).join('');
            const articlesHtml = articles.length ?
                articles.map((article) => {
                    const targetCount = Array.isArray(article.institution_ids) ?
                        article.institution_ids.length :
                        String(article.institution_names || '').split(',').map((value) => value.trim()).filter(Boolean).length;
                    const targetLabel = targetCount > 0 ? `${targetCount} target${targetCount > 1 ? 's' : ''}` : 'Public article';
                    return `
          <div class="list-card">
            <div class="list-card-head">
              <div>
                <h4>${escapeHtml(article.title)}</h4>
                                <p>${escapeHtml(article.institution_names || 'Todos os públicos')}</p>
              </div>
              <span class="chip">${escapeHtml(article.author_name || 'Unknown')}</span>
            </div>
                        <div class="list-meta">${escapeHtml(formatDate(article.created_at))} · ${escapeHtml(targetLabel)}</div>
            <div class="btn-row">
              <button class="btn btn-ghost" type="button" data-edit-article="${article.id}"><span class="material-symbols-outlined">edit</span>Edit</button>
              <button class="btn btn-danger" type="button" data-delete-article="${article.id}"><span class="material-symbols-outlined">delete</span>Delete</button>
            </div>
          </div>
                `;
                }).join('') :
                '<div class="empty">No articles registered yet.</div>';

            panelBody.innerHTML = `
        <div class="cards">
          <form class="list-card field-grid" id="articleForm">
            <div class="list-card-head">
              <div>
                <h4 id="articleFormTitle">Add article</h4>
                                <p>Select one or more institutions as target audience.</p>
              </div>
              <span class="chip">Admin only</span>
            </div>
            <input type="hidden" name="action" value="create" />
            <input type="hidden" name="id" value="" />
            <div class="field-grid" style="grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));">
              <div class="field"><label for="articleTitle">Title</label><input id="articleTitle" name="title" required /></div>
              <div class="field" style="grid-column: 1 / -1;"><label for="articleContent">Content</label><textarea id="articleContent" name="content" required></textarea></div>
                            <div class="field" style="grid-column: 1 / -1;"><label>Target audience (institutions)</label><div id="articleInstitutionCheckboxes">${institutionCheckboxes || '<span class="empty">No institutions available.</span>'}</div></div>
            </div>
            <div class="btn-row">
              <button class="btn btn-primary" type="submit"><span class="material-symbols-outlined">save</span>Save article</button>
                                                        <button class="btn btn-ghost" type="button" id="applyCertificationTemplateButton"><span class="material-symbols-outlined">school</span>Use certification template</button>
                                                        <button class="btn btn-ghost" type="button" id="applyRegistrationTemplateButton"><span class="material-symbols-outlined">inventory_2</span>Use registration template</button>
              <button class="btn btn-soft" type="button" id="resetArticleFormButton">Reset</button>
            </div>
          </form>

                    <div class="list-card">
                        <div class="list-card-head">
                            <div>
                                <h4>Article template</h4>
                                <p>Use the quick buttons to load one of the two article templates below.</p>
                            </div>
                        </div>
                        <pre style="white-space:pre-wrap; margin:0; padding:0.8rem; border-radius:0.8rem; background:rgba(13,33,51,0.04); border:1px solid rgba(13,33,51,0.1); font-family:inherit; line-height:1.45;">${escapeHtml(CERT_TEMPLATE_TITLE + '\n\n' + CERT_TEMPLATE_BODY + '\n\n---\n\n' + REG_TEMPLATE_TITLE + '\n\n' + REG_TEMPLATE_BODY)}</pre>
                    </div>

          <div class="list-card">
            <div class="list-card-head">
              <div>
                <h4>Institution list</h4>
                <p>Used in article targeting and user registration.</p>
              </div>
            </div>
            <div class="chips" id="articleInstitutionChips"></div>
          </div>

          <div class="list-card">
            <div class="list-card-head">
              <div>
                <h4>Registered articles</h4>
                <p>Manage what each institution can access.</p>
              </div>
            </div>
            <div class="cards">${articlesHtml}</div>
          </div>
        </div>
      `;

            const articleInstitutionChips = document.getElementById('articleInstitutionChips');
            articleInstitutionChips.innerHTML = institutions.length ?
                institutions.map((item) => `<span class="chip">${escapeHtml(item.name)}</span>`).join('') :
                '<span class="chip">No institutions yet</span>';

            document.getElementById('articleForm').addEventListener('submit', async (event) => {
                event.preventDefault();
                const formElements = event.target.elements;
                const formData = new FormData(event.target);
                const institutionIds = Array.from(document.querySelectorAll('#articleInstitutionCheckboxes input[name="institution_ids[]"]:checked')).map((el) => Number(el.value));
                const payload = Object.fromEntries(formData.entries());
                payload.institution_ids = institutionIds;
                const response = await requestJson('api/articles.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload),
                });
                if (response.success) {
                    event.target.reset();
                    formElements.action.value = 'create';
                    formElements.id.value = '';
                    document.getElementById('articleFormTitle').textContent = 'Add article';
                    if (defaultInstitutionId !== null) {
                        document.querySelectorAll('#articleInstitutionCheckboxes input[name="institution_ids[]"]').forEach((checkbox) => {
                            checkbox.checked = Number(checkbox.value) === defaultInstitutionId;
                        });
                    }
                    await Promise.all([loadAdminArticles(), loadInstitutions(), refreshSidebarArticles()]);
                }
            });

            function applyTemplate(title, content, targetNames) {
                const form = document.getElementById('articleForm');
                const elements = form.elements;
                elements.title.value = title;
                elements.content.value = content;
                const normalizedTargets = targetNames.map((name) => String(name).trim().toUpperCase());
                document.querySelectorAll('#articleInstitutionCheckboxes input[name="institution_ids[]"]').forEach((checkbox) => {
                    const institution = institutions.find((item) => Number(item.id) === Number(checkbox.value));
                    const institutionName = (institution?.name || '').toUpperCase();
                    checkbox.checked = normalizedTargets.includes(institutionName);
                });
                elements.content.focus();
            }

            document.getElementById('applyCertificationTemplateButton').addEventListener('click', () => {
                applyTemplate(CERT_TEMPLATE_TITLE, CERT_TEMPLATE_BODY, ['AIFAESA', 'INDIMO']);
            });

            document.getElementById('applyRegistrationTemplateButton').addEventListener('click', () => {
                applyTemplate(REG_TEMPLATE_TITLE, REG_TEMPLATE_BODY, ['AIFAESA', 'DNRKPK-MCI']);
            });

            document.getElementById('resetArticleFormButton').addEventListener('click', () => {
                const form = document.getElementById('articleForm');
                const elements = form.elements;
                form.reset();
                elements.action.value = 'create';
                elements.id.value = '';
                document.getElementById('articleFormTitle').textContent = 'Add article';
                if (defaultInstitutionId !== null) {
                    document.querySelectorAll('#articleInstitutionCheckboxes input[name="institution_ids[]"]').forEach((checkbox) => {
                        checkbox.checked = Number(checkbox.value) === defaultInstitutionId;
                    });
                }
            });

            document.getElementById('refreshArticlesAdminButton').addEventListener('click', async () => {
                await Promise.all([loadAdminArticles(), loadInstitutions(), refreshSidebarArticles()]);
            });

            document.querySelectorAll('[data-edit-article]').forEach((button) => {
                button.addEventListener('click', () => {
                    const item = articles.find((article) => Number(article.id) === Number(button.dataset.editArticle));
                    if (!item) return;
                    const form = document.getElementById('articleForm');
                    const elements = form.elements;
                    elements.action.value = 'update';
                    elements.id.value = item.id;
                    elements.title.value = item.title || '';
                    elements.content.value = item.content || '';
                    const targetIds = Array.isArray(item.institution_ids) ? item.institution_ids.map(Number) : [];
                    document.querySelectorAll('#articleInstitutionCheckboxes input[name="institution_ids[]"]').forEach((checkbox) => {
                        checkbox.checked = targetIds.includes(Number(checkbox.value));
                    });
                    document.getElementById('articleFormTitle').textContent = `Edit article #${item.id}`;
                    closeSidebar();
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                });
            });

            document.querySelectorAll('[data-delete-article]').forEach((button) => {
                button.addEventListener('click', async () => {
                    if (!confirm('Delete this article?')) return;
                    await requestJson('api/articles.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'delete',
                            id: Number(button.dataset.deleteArticle)
                        }),
                    });
                    await Promise.all([loadAdminArticles(), loadInstitutions(), refreshSidebarArticles()]);
                });
            });
        }

        function renderProfileView() {
            setActiveNav('profile-view');
            setPanelHeader('Profile', 'Update your photo or password. The profile controls are available for non-admin users from the sidebar.', '');

            panelBody.innerHTML = `
        <div class="cards">
          <section class="list-card">
            <div class="list-card-head">
              <div>
                <h4>${escapeHtml(state.currentUser.name || 'Profile')}</h4>
                <p>${escapeHtml(state.currentUser.email || '')}</p>
              </div>
              <span class="chip">${escapeHtml(state.currentUser.role || 'user')}</span>
            </div>
            <div class="btn-row">
              <div class="avatar" style="width: 4rem; height: 4rem;">${avatarMarkup(state.currentUser)}</div>
              <div>
                <strong>${escapeHtml(state.currentUser.position || 'Sem cargo')}</strong>
                <div class="list-meta">${escapeHtml(state.currentUser.institution_name || 'Sem instituição')}</div>
              </div>
            </div>
          </section>

          <form class="list-card field-grid" id="profilePhotoForm" enctype="multipart/form-data">
            <div class="list-card-head">
              <div>
                <h4>Replace photo</h4>
                <p>Upload a new photo to replace the current one.</p>
              </div>
            </div>
            <input type="hidden" name="action" value="update_photo" />
            <div class="field"><label for="profilePhoto">Photo</label><input id="profilePhoto" name="photo" type="file" accept="image/*" required /></div>
            <div class="btn-row"><button class="btn btn-primary" type="submit"><span class="material-symbols-outlined">photo_camera</span>Update photo</button></div>
          </form>

          <form class="list-card field-grid" id="profilePasswordForm">
            <div class="list-card-head">
              <div>
                <h4>Change password</h4>
                <p>Keep it at least six characters long.</p>
              </div>
            </div>
            <input type="hidden" name="action" value="change_password" />
            <div class="field-grid" style="grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));">
              <div class="field"><label for="currentPassword">Current password</label><input id="currentPassword" name="current_password" type="password" required /></div>
              <div class="field"><label for="newPassword">New password</label><input id="newPassword" name="new_password" type="password" required /></div>
              <div class="field"><label for="confirmPassword">Confirm password</label><input id="confirmPassword" name="confirm_password" type="password" required /></div>
            </div>
            <div class="btn-row"><button class="btn btn-primary" type="submit"><span class="material-symbols-outlined">lock_reset</span>Change password</button></div>
          </form>
        </div>
      `;

            document.getElementById('profilePhotoForm').addEventListener('submit', async (event) => {
                event.preventDefault();
                const formData = new FormData(event.target);
                const response = await requestJson('api/profile.php', {
                    method: 'POST',
                    body: formData,
                });
                if (response.success) {
                    state.currentUser.photo = response.photo;
                    renderProfileView();
                }
            });

            document.getElementById('profilePasswordForm').addEventListener('submit', async (event) => {
                event.preventDefault();
                const formData = new FormData(event.target);
                const response = await requestJson('api/profile.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(Object.fromEntries(formData.entries())),
                });
                if (response.success) {
                    event.target.reset();
                    alert('Password updated successfully.');
                }
            });
            closeSidebar();
        }

        async function loadAdminUsers() {
            const data = await requestJson('api/users.php');
            renderUsersManagement(data.users || [], state.institutions);
        }

        async function loadAdminInstitutions() {
            const data = await requestJson('api/institutions.php');
            state.institutions = data.institutions || [];
            renderInstitutionChips(state.institutions);
            renderInstitutionsManagement(state.institutions);
        }

        async function loadAdminArticles() {
            const data = await requestJson('api/articles.php');
            renderArticlesManagement(data.articles || [], state.institutions);
        }

        async function refreshSidebarArticles() {
            const data = await requestJson('api/articles.php');
            state.articles = data.articles || [];
            const list = document.getElementById('articleList');
            list.innerHTML = state.articles.length ?
                state.articles.map((article) => `
            <button class="article-item${Number(article.id) === Number(state.activeArticleId) ? ' active' : ''}" type="button" data-article-id="${article.id}">
              <strong>${escapeHtml(article.title)}</strong>
                            <small>${escapeHtml(article.institution_names || 'Todos os públicos')}</small>
            </button>
          `).join('') :
                '<div class="empty" style="margin: 0 1rem 1rem;">Sem artigos disponíveis para a sua instituição.</div>';

            document.querySelectorAll('.article-item').forEach((button) => {
                button.addEventListener('click', () => loadArticle(button.dataset.articleId));
            });
        }

        document.querySelectorAll('[data-view]').forEach((button) => {
            button.addEventListener('click', async () => {
                const view = button.dataset.view;
                closeSidebar();
                if (view === 'articles-home') {
                    renderArticleHome();
                    return;
                }
                if (view === 'users-management' && state.isAdmin) {
                    await loadAdminUsers();
                    return;
                }
                if (view === 'institutions-management' && state.isAdmin) {
                    await loadAdminInstitutions();
                    return;
                }
                if (view === 'articles-management' && state.isAdmin) {
                    await loadAdminArticles();
                    return;
                }
                if (view === 'profile-view' && !state.isAdmin) {
                    renderProfileView();
                }
            });
        });

        document.querySelectorAll('.article-item').forEach((button) => {
            button.addEventListener('click', () => loadArticle(button.dataset.articleId));
        });

        async function boot() {
            try {
                await loadInstitutions();
                if (state.isAdmin) {
                    renderArticleHome();
                } else if (state.activeArticleId !== null) {
                    await loadArticle(state.activeArticleId);
                } else {
                    renderArticleHome();
                }
            } catch (error) {
                panelBody.innerHTML = `<div class="empty">${escapeHtml(error.message || 'Something went wrong.')}</div>`;
            }
        }

        boot();
    </script>
</body>

</html>