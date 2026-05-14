<?php
// ─────────────────────────────────────────────────────────────
//  add-article.php — Form to add a new article / document
// ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/db.php';
requireLogin('/add-article.php');

$db         = getDB();
$errors     = [];
$success    = false;
$newSlug    = '';

// ── Fetch categories for the select ──────────────────────────
$categories = $db->query(
    'SELECT id, name FROM categories ORDER BY sort_order ASC, name ASC'
)->fetchAll(PDO::FETCH_ASSOC);

// ── Handle form submission ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title      = trim($_POST['title']      ?? '');
    $subtitle   = trim($_POST['subtitle']   ?? '');
    $summary    = trim($_POST['summary']    ?? '');
    $author     = trim($_POST['author']     ?? '');
    $catId      = (int)($_POST['category_id'] ?? 0) ?: null;
    $published  = isset($_POST['published']) ? 1 : 0;
    $customSlug = trim($_POST['custom_slug'] ?? '');

    // Validation
    if ($title === '') {
        $errors[] = 'O título é obrigatório.';
    }

    // Slug
    $slug = $customSlug !== '' ? makeSlug($customSlug) : makeSlug($title);
    if ($slug === '') {
        $errors[] = 'Não foi possível gerar um identificador (slug) a partir do título.';
    }

    // Check slug uniqueness
    if ($slug !== '') {
        $exists = $db->prepare('SELECT id FROM articles WHERE slug = ?');
        $exists->execute([$slug]);
        if ($exists->fetch()) {
            $slug .= '-' . date('ymd');
        }
    }

    // File upload
    $uploadedFile = null;
    $uploadDir    = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0750, true);

    if (isset($_FILES['content_file']) && $_FILES['content_file']['error'] === UPLOAD_ERR_OK) {
        $tmpPath  = $_FILES['content_file']['tmp_name'];
        $origName = $_FILES['content_file']['name'];
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if (!in_array($ext, ['html', 'htm'])) {
            $errors[] = 'Apenas ficheiros HTML (.html, .htm) são aceites.';
        } elseif ($_FILES['content_file']['size'] > 8 * 1024 * 1024) {
            $errors[] = 'O ficheiro não pode exceder 8 MB.';
        } else {
            $safeFileName = $slug . '.html';
            $destPath     = $uploadDir . $safeFileName;
            if (move_uploaded_file($tmpPath, $destPath)) {
                $uploadedFile = $safeFileName;
            } else {
                $errors[] = 'Falha ao guardar o ficheiro. Verifique as permissões da pasta uploads/.';
            }
        }
    } elseif (isset($_FILES['content_file']) && $_FILES['content_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Erro no upload do ficheiro (código: ' . $_FILES['content_file']['error'] . ').';
    }

    // Insert if no errors
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                INSERT INTO articles
                    (category_id, title, slug, subtitle, summary, content_file, author, published)
                VALUES
                    (:cat, :title, :slug, :sub, :summary, :file, :author, :pub)
            ");
            $stmt->execute([
                ':cat'     => $catId,
                ':title'   => $title,
                ':slug'    => $slug,
                ':sub'     => $subtitle,
                ':summary' => $summary,
                ':file'    => $uploadedFile,
                ':author'  => $author,
                ':pub'     => $published,
            ]);
            $success = true;
            $newSlug = $slug;
        } catch (PDOException $e) {
            $errors[] = 'Erro ao guardar no banco de dados: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adicionar Artigo — AIFAESA Ideias</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@400;700&family=Source+Sans+3:wght@300;400;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary:       #1a3a5c;
            --primary-light: #2c5f8a;
            --primary-dark:  #0f2338;
            --accent:        #c8973a;
            --accent-light:  #e8b85a;
            --bg:            #f8f6f1;
            --surface:       #ffffff;
            --border:        #ddd8cc;
            --text:          #1e1e1e;
            --text-muted:    #5a5a5a;
            --danger:        #8b2e2e;
            --success:       #2a5c3f;
        }

        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; font-family: 'Source Sans 3', sans-serif; background: var(--bg); color: var(--text); line-height: 1.7; }

        /* Top bar */
        .topbar {
            background: var(--primary-dark);
            border-bottom: 3px solid var(--accent);
            padding: .9rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .topbar a.brand {
            display: flex; align-items: center; gap: .55rem; text-decoration: none;
        }
        .brand-badge {
            background: var(--accent); color: var(--primary-dark);
            font-family: 'Merriweather', serif; font-weight: 700; font-size: .72rem;
            padding: .2rem .55rem; border-radius: 2px;
        }
        .brand-name {
            color: #fff; font-family: 'Merriweather', serif; font-size: 1rem; font-weight: 700;
        }
        .brand-name span { color: var(--accent-light); }
        .topbar-back {
            margin-left: auto;
            color: rgba(255,255,255,.65);
            text-decoration: none;
            font-size: .82rem;
            display: flex; align-items: center; gap: .35rem;
            transition: color .15s;
        }
        .topbar-back:hover { color: var(--accent-light); }

        /* Page layout */
        .page-wrap {
            max-width: 780px;
            margin: 0 auto;
            padding: 2.5rem 1.5rem 5rem;
        }

        .page-header {
            margin-bottom: 2rem;
            padding-bottom: 1.2rem;
            border-bottom: 2px solid var(--border);
        }
        .page-header h1 {
            font-family: 'Merriweather', serif;
            font-size: 1.7rem;
            color: var(--primary);
            margin: 0 0 .3rem;
        }
        .page-header p { color: var(--text-muted); margin: 0; font-size: .93rem; }

        /* Form */
        .form-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 2rem;
            box-shadow: 0 2px 12px rgba(0,0,0,.05);
        }

        .form-section-label {
            font-size: .68rem;
            letter-spacing: .15em;
            text-transform: uppercase;
            color: var(--accent);
            font-weight: 700;
            margin-bottom: .8rem;
            padding-bottom: .4rem;
            border-bottom: 1px solid var(--border);
        }

        label {
            display: block;
            font-weight: 600;
            font-size: .88rem;
            color: var(--primary);
            margin-bottom: .3rem;
        }
        label .req { color: #b94040; margin-left: .2rem; }
        label .hint { font-weight: 400; color: var(--text-muted); font-size: .8rem; }

        input[type="text"],
        input[type="email"],
        textarea,
        select {
            width: 100%;
            border: 1.5px solid var(--border);
            border-radius: 4px;
            padding: .55rem .8rem;
            font-family: 'Source Sans 3', sans-serif;
            font-size: .93rem;
            color: var(--text);
            background: var(--surface);
            transition: border-color .15s, box-shadow .15s;
            outline: none;
        }
        input:focus, textarea:focus, select:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(44,95,138,.12);
        }
        textarea { resize: vertical; min-height: 90px; }

        .field-group { margin-bottom: 1.3rem; }

        /* Drop zone */
        .drop-zone {
            border: 2px dashed var(--border);
            border-radius: 6px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: border-color .15s, background .15s;
            background: #fafaf7;
            position: relative;
        }
        .drop-zone:hover, .drop-zone.dragging {
            border-color: var(--primary-light);
            background: #eef3f8;
        }
        .drop-zone input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }
        .drop-zone .dz-icon { font-size: 2.2rem; color: var(--border); display: block; margin-bottom: .5rem; }
        .drop-zone .dz-label { font-weight: 600; color: var(--primary-light); }
        .drop-zone .dz-sub { font-size: .8rem; color: var(--text-muted); margin-top: .2rem; }
        .drop-zone .dz-selected { margin-top: .7rem; font-size: .85rem; color: var(--success); font-weight: 600; display: none; }

        /* Slug preview */
        .slug-preview {
            font-size: .8rem;
            color: var(--text-muted);
            margin-top: .35rem;
            display: flex;
            align-items: center;
            gap: .4rem;
        }
        .slug-preview code {
            background: #eee;
            padding: .05rem .35rem;
            border-radius: 3px;
            font-size: .78rem;
            color: var(--primary);
        }

        /* Toggle switch */
        .toggle-row {
            display: flex;
            align-items: center;
            gap: .8rem;
        }
        .toggle-row label { margin: 0; }
        .switch {
            position: relative;
            display: inline-block;
            width: 42px; height: 24px;
        }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute;
            inset: 0;
            background: #ccc;
            border-radius: 24px;
            transition: .2s;
            cursor: pointer;
        }
        .slider:before {
            content: '';
            position: absolute;
            width: 18px; height: 18px;
            left: 3px; bottom: 3px;
            background: #fff;
            border-radius: 50%;
            transition: .2s;
        }
        input:checked + .slider { background: var(--primary); }
        input:checked + .slider:before { transform: translateX(18px); }

        /* Buttons */
        .btn-submit {
            background: var(--primary);
            color: #fff;
            border: none;
            padding: .7rem 2rem;
            border-radius: 4px;
            font-family: 'Source Sans 3', sans-serif;
            font-weight: 700;
            font-size: .95rem;
            cursor: pointer;
            transition: background .15s;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
        }
        .btn-submit:hover { background: var(--primary-light); }
        .btn-cancel {
            background: none;
            border: 1.5px solid var(--border);
            color: var(--text-muted);
            padding: .65rem 1.5rem;
            border-radius: 4px;
            font-family: 'Source Sans 3', sans-serif;
            font-size: .93rem;
            cursor: pointer;
            text-decoration: none;
            transition: border-color .15s;
        }
        .btn-cancel:hover { border-color: var(--primary-light); color: var(--primary); }

        /* Alerts */
        .alert-error {
            background: #f8ecea;
            border: 1px solid #d9a0a0;
            border-left: 4px solid var(--danger);
            border-radius: 4px;
            padding: 1rem 1.2rem;
            margin-bottom: 1.5rem;
        }
        .alert-error h5 {
            color: var(--danger);
            font-size: .9rem;
            font-weight: 700;
            margin: 0 0 .4rem;
        }
        .alert-error ul { margin: 0; padding-left: 1.2rem; font-size: .88rem; color: #6b2020; }

        .alert-success {
            background: #eaf4ed;
            border: 1px solid #9acfaa;
            border-left: 4px solid var(--success);
            border-radius: 4px;
            padding: 1.5rem 1.5rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        .alert-success h4 {
            font-family: 'Merriweather', serif;
            color: var(--success);
            margin: 0 0 .5rem;
        }
        .alert-success p { color: #2a5c3f; margin: 0 0 1rem; font-size: .93rem; }
        .alert-success .success-actions { display: flex; gap: .8rem; justify-content: center; flex-wrap: wrap; }
        .btn-view {
            background: var(--success);
            color: #fff;
            padding: .5rem 1.2rem;
            border-radius: 3px;
            text-decoration: none;
            font-weight: 700;
            font-size: .85rem;
        }
        .btn-addmore {
            background: none;
            border: 1.5px solid var(--success);
            color: var(--success);
            padding: .45rem 1.1rem;
            border-radius: 3px;
            text-decoration: none;
            font-weight: 600;
            font-size: .85rem;
        }

        .col2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        @media (max-width: 560px) { .col2 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<header class="topbar">
    <a href="/" class="brand">
        <span class="brand-badge">AIFAESA</span>
        <span class="brand-name">ideias<span>.aifaesa.org</span></span>
    </a>
    <div style="margin-left:auto; display:flex; align-items:center; gap:.6rem;">
        <a href="/admin/" class="topbar-back"><i class="bi bi-shield-lock"></i> Painel admin</a>
        <a href="/" class="topbar-back"><i class="bi bi-arrow-left"></i> Portal</a>
        <a href="/logout.php" class="topbar-back"><i class="bi bi-box-arrow-right"></i> Sair</a>
    </div>
</header>

<div class="page-wrap">

    <div class="page-header">
        <h1><i class="bi bi-file-earmark-plus" style="color:var(--accent);"></i> Adicionar Novo Documento</h1>
        <p>Preencha o formulário abaixo para publicar um novo artigo ou documento técnico no portal. O ficheiro HTML será carregado e ficará disponível na barra lateral para consulta.</p>
    </div>

    <?php if ($success): ?>
    <!-- ── Success state ── -->
    <div class="alert-success">
        <div style="font-size:2.5rem; margin-bottom:.5rem;">&#x2705;</div>
        <h4>Documento publicado com sucesso!</h4>
        <p>O documento foi adicionado ao portal e está agora disponível na barra lateral.</p>
        <div class="success-actions">
            <a href="/?page=<?= urlencode($newSlug) ?>" class="btn-view"><i class="bi bi-eye"></i> Ver documento</a>
            <a href="/add-article.php" class="btn-addmore"><i class="bi bi-plus"></i> Adicionar outro</a>
            <a href="/" class="btn-addmore"><i class="bi bi-house"></i> Voltar ao início</a>
        </div>
    </div>

    <?php else: ?>

    <?php if (!empty($errors)): ?>
    <div class="alert-error">
        <h5><i class="bi bi-exclamation-triangle"></i> Por favor corrija os seguintes erros:</h5>
        <ul>
            <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form class="form-card" method="POST" enctype="multipart/form-data" novalidate>

        <!-- ── Identificação ── -->
        <div class="form-section-label"><i class="bi bi-card-text"></i> &nbsp; Identificação do Documento</div>

        <div class="field-group">
            <label for="title">Título <span class="req">*</span></label>
            <input type="text" id="title" name="title" required
                   value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                   placeholder="Ex.: Proposta de Regulamento de Fiscalização Alimentar">
            <div class="slug-preview" id="slugPreview">
                <i class="bi bi-link-45deg"></i> URL: <code id="slugDisplay">/</code>
            </div>
        </div>

        <div class="field-group">
            <label for="subtitle">Subtítulo <span class="hint">(opcional)</span></label>
            <input type="text" id="subtitle" name="subtitle"
                   value="<?= htmlspecialchars($_POST['subtitle'] ?? '') ?>"
                   placeholder="Breve descrição complementar ao título">
        </div>

        <div class="col2">
            <div class="field-group">
                <label for="category_id">Categoria</label>
                <select id="category_id" name="category_id">
                    <option value="">— Sem categoria —</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"
                        <?= (($_POST['category_id'] ?? '') == $cat['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field-group">
                <label for="author">Autor / Unidade orgânica <span class="hint">(opcional)</span></label>
                <input type="text" id="author" name="author"
                       value="<?= htmlspecialchars($_POST['author'] ?? '') ?>"
                       placeholder="Ex.: Departamento de RH">
            </div>
        </div>

        <div class="field-group">
            <label for="summary">Sumário / Resumo <span class="hint">(opcional — aparece como descrição)</span></label>
            <textarea id="summary" name="summary" rows="3"
                      placeholder="Breve descrição do conteúdo e objetivo do documento..."><?= htmlspecialchars($_POST['summary'] ?? '') ?></textarea>
        </div>

        <!-- ── Slug avançado ── -->
        <details style="margin-bottom:1.3rem;">
            <summary style="cursor:pointer; font-size:.85rem; color:var(--text-muted); font-weight:600; user-select:none;">
                <i class="bi bi-gear"></i> Opções avançadas (slug / URL personalizado)
            </summary>
            <div style="margin-top:.8rem;">
                <label for="custom_slug">URL personalizado <span class="hint">(deixe em branco para gerar automaticamente)</span></label>
                <input type="text" id="custom_slug" name="custom_slug"
                       value="<?= htmlspecialchars($_POST['custom_slug'] ?? '') ?>"
                       placeholder="Ex.: regulamento-fiscalizacao-2025"
                       pattern="[a-z0-9\-]*">
                <p style="font-size:.78rem; color:var(--text-muted); margin-top:.3rem;">
                    Utilize apenas letras minúsculas, números e hífenes. O sistema irá gerar automaticamente a partir do título se este campo estiver vazio.
                </p>
            </div>
        </details>

        <!-- ── Ficheiro ── -->
        <div class="form-section-label" style="margin-top:1.5rem;"><i class="bi bi-upload"></i> &nbsp; Ficheiro HTML</div>

        <div class="field-group">
            <label>Ficheiro do documento <span class="hint">(formato .html — máx. 8 MB)</span></label>
            <div class="drop-zone" id="dropZone">
                <input type="file" name="content_file" id="fileInput" accept=".html,.htm">
                <i class="bi bi-file-earmark-code dz-icon"></i>
                <div class="dz-label">Clique para selecionar ou arraste o ficheiro aqui</div>
                <div class="dz-sub">Aceita ficheiros .html e .htm</div>
                <div class="dz-selected" id="dzSelected"></div>
            </div>
            <p style="font-size:.8rem; color:var(--text-muted); margin-top:.4rem;">
                <i class="bi bi-info-circle"></i> O ficheiro deve ser um documento HTML completo ou apenas o conteúdo a apresentar. Utilize o template fornecido para garantir consistência visual.
            </p>
        </div>

        <!-- ── Publicação ── -->
        <div class="form-section-label" style="margin-top:1.5rem;"><i class="bi bi-eye"></i> &nbsp; Publicação</div>

        <div class="field-group">
            <div class="toggle-row">
                <label class="switch">
                    <input type="checkbox" name="published" id="published"
                           <?= (($_POST['published'] ?? '1') ? 'checked' : '') ?>>
                    <span class="slider"></span>
                </label>
                <label for="published" style="cursor:pointer;">
                    Publicar imediatamente após guardar
                    <span class="hint">(desative para guardar como rascunho)</span>
                </label>
            </div>
        </div>

        <!-- ── Actions ── -->
        <div style="display:flex; gap:.8rem; margin-top:2rem; padding-top:1.2rem; border-top:1px solid var(--border); flex-wrap:wrap;">
            <button type="submit" class="btn-submit">
                <i class="bi bi-cloud-upload"></i> Guardar e Publicar
            </button>
            <a href="/" class="btn-cancel">Cancelar</a>
        </div>

    </form>

    <!-- Template download hint -->
    <div style="margin-top:1.5rem; background:#fff; border:1px solid var(--border); border-left:4px solid var(--accent); border-radius:4px; padding:1rem 1.2rem; font-size:.87rem;">
        <strong><i class="bi bi-file-earmark-code" style="color:var(--accent);"></i> Ainda não tem o ficheiro HTML?</strong>
        Descarregue o <a href="/template-artigo.html" style="color:var(--primary-light); font-weight:600;">template de artigo</a> como ponto de partida. Edite-o com o conteúdo pretendido e faça o upload acima.
    </div>

    <?php endif; ?>

</div><!-- /page-wrap -->

<script>
// Auto slug from title
const titleInput  = document.getElementById('title');
const customSlug  = document.getElementById('custom_slug');
const slugDisplay = document.getElementById('slugDisplay');

function toSlug(str) {
    const map = {'á':'a','à':'a','â':'a','ã':'a','ä':'a','é':'e','è':'e','ê':'e','ë':'e','í':'i','ì':'i','î':'i','ï':'i','ó':'o','ò':'o','ô':'o','õ':'o','ö':'o','ú':'u','ù':'u','û':'u','ü':'u','ç':'c','ñ':'n'};
    str = str.toLowerCase().replace(/[áàâãäéèêëíìîïóòôõöúùûüçñ]/g, c => map[c] || c);
    str = str.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    return str;
}
function updateSlug() {
    const base = customSlug.value.trim() !== '' ? toSlug(customSlug.value) : toSlug(titleInput.value);
    slugDisplay.textContent = base ? '/?page=' + base : '/';
}

titleInput.addEventListener('input', updateSlug);
customSlug.addEventListener('input', updateSlug);
updateSlug();

// File drop zone
const dropZone  = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const dzSel     = document.getElementById('dzSelected');

fileInput.addEventListener('change', () => {
    if (fileInput.files.length > 0) {
        dzSel.textContent = '✓ ' + fileInput.files[0].name;
        dzSel.style.display = 'block';
    }
});
dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('dragging'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragging'));
dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('dragging');
    if (e.dataTransfer.files.length > 0) {
        fileInput.files = e.dataTransfer.files;
        dzSel.textContent = '✓ ' + e.dataTransfer.files[0].name;
        dzSel.style.display = 'block';
    }
});
</script>
</body>
</html>
