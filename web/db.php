<?php

/**
 * db.php — SQLite connection & schema bootstrap
 */

define('DB_PATH', __DIR__ . '/data/parseiras.db');

function get_db(): SQLite3
{
    static $db = null;
    if ($db === null) {
        if (!is_dir(__DIR__ . '/data')) {
            mkdir(__DIR__ . '/data', 0755, true);
        }
        $db = new SQLite3(DB_PATH);
        $db->enableExceptions(true);
        $db->exec('PRAGMA journal_mode = WAL;');
        $db->exec('PRAGMA foreign_keys = ON;');
        init_schema($db);
    }
    return $db;
}

function init_schema(SQLite3 $db): void
{
    $db->exec("
        CREATE TABLE IF NOT EXISTS institutions (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            name       TEXT    NOT NULL UNIQUE,
            created_at DATETIME DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS users (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            name           TEXT    NOT NULL,
            position       TEXT    DEFAULT '',
            institution_id INTEGER REFERENCES institutions(id) ON DELETE SET NULL,
            whatsapp       TEXT    DEFAULT '',
            email          TEXT    NOT NULL UNIQUE,
            photo          TEXT    DEFAULT '',
            password_hash  TEXT    NOT NULL,
            role           TEXT    NOT NULL DEFAULT 'user' CHECK(role IN ('admin','user')),
            last_login     DATETIME,
            created_at     DATETIME DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS articles (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            title          TEXT    NOT NULL,
            content        TEXT    NOT NULL DEFAULT '',
            institution_id INTEGER REFERENCES institutions(id) ON DELETE SET NULL,
            created_by     INTEGER REFERENCES users(id) ON DELETE SET NULL,
            created_at     DATETIME DEFAULT (datetime('now')),
            updated_at     DATETIME DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS comments (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            article_id INTEGER NOT NULL REFERENCES articles(id) ON DELETE CASCADE,
            user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            comment    TEXT    NOT NULL,
            created_at DATETIME DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS article_institutions (
            article_id     INTEGER NOT NULL REFERENCES articles(id) ON DELETE CASCADE,
            institution_id INTEGER NOT NULL REFERENCES institutions(id) ON DELETE CASCADE,
            PRIMARY KEY (article_id, institution_id)
        );

        CREATE TABLE IF NOT EXISTS mobile_sessions (
            token      TEXT PRIMARY KEY,
            user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT (datetime('now'))
        );
    ");

    seed_institutions($db);
    migrate_legacy_article_institutions($db);

    seed_user($db, [
        'name' => 'Administrator',
        'position' => 'System Admin',
        'institution' => 'AIFAESA',
        'whatsapp' => '',
        'email' => 'admin@aifaesa.tl',
        'role' => 'admin',
        'password' => 'admin123',
    ]);

    seed_user($db, [
        'name' => 'sgclobo',
        'position' => 'Administrator',
        'institution' => 'AIFAESA',
        'whatsapp' => '78482777',
        'email' => 'sgclobo@gmail.com',
        'role' => 'admin',
        'password' => 'admin123',
    ]);

    seed_user($db, [
        'name' => 'drsergio',
        'position' => 'Member',
        'institution' => 'AIFAESA',
        'whatsapp' => '77262600',
        'email' => 'drsergiolobo@gmail.com',
        'role' => 'user',
        'password' => 'user123',
    ]);

    seed_article($db, [
        'title' => 'Training Certifications for Product Quality and Compliance',
        'content' => "Background:\nMany economic operators still lack formal technical certifications for product handling, safety controls, and traceability practices. In several sectors, documentation is incomplete, training is informal, and quality checks are performed only after a problem has already reached the market. That creates avoidable risks for consumers, weakens trust in the inspection process, and makes it harder for institutions to coordinate a consistent response.\n\nProposal:\nAIFAESA and INDIMO should coordinate a recurring training certification program focused on quality assurance, compliance requirements, and practical inspection readiness. The program should be designed as a working system rather than a one-off event: each cycle should train operators, verify competencies, record outcomes, and assign clear follow-up actions for those who still need improvement.\n\nImplementation Actions:\n1. Map high-priority operators by sector and risk profile. Operators handling sensitive or high-volume products should be prioritized first so that the most important gaps are addressed early.\n2. Define competency modules and minimum certification criteria. The modules should cover product identification, storage conditions, traceability, labeling, hygiene, and response to inspection findings.\n3. Deliver phased trainings with assessment and re-certification cycles. Short lessons, practical examples, and post-training evaluations will help participants retain the information and apply it in daily work.\n4. Publish certified operator status for transparency and market confidence. A simple public registry or internal dashboard can show which operators are compliant, which are in training, and which need corrective action.\n5. Coordinate follow-up visits after training. Real compliance only happens when the lessons are reinforced in practice through inspections, reminders, and clear deadlines.\n\nOperational Considerations:\nThe certification framework should be written in language that is clear enough for operators to understand quickly, but detailed enough for inspectors to apply consistently. Forms should be short, digital where possible, and linked to a shared database so that records are not lost across departments. If the same operator appears in multiple inspections, the system should allow the team to see the training history immediately.\n\nExpected Result:\nStronger product quality controls, improved compliance levels, and a consistent certification culture across operators. Over time, the certification program can become a normal part of doing business, not just a temporary response to a specific risk.",
        'institutions' => ['AIFAESA', 'INDIMO'],
    ]);

    seed_article($db, [
        'title' => 'Compulsory Product Registration Database for All Economic Operators',
        'content' => "Background:\nProduct registration remains uneven and difficult to verify across sectors, which creates risks for market surveillance and consumer protection. In many environments, products enter the market with incomplete records, inconsistent naming conventions, or no easily accessible verification trail. That makes it difficult for inspectors to know whether an item was approved, whether its origin can be traced, or whether the operator has complied with the required documentation.\n\nProposal:\nAIFAESA and DNRKPK-MCI should propose and enforce compulsory product registration by all economic operators in a government-managed database. The system should serve as the central reference for every product that is legally placed on the market, making it possible to check registration status before distribution, during inspection, and when a complaint is received.\n\nImplementation Actions:\n1. Establish legal requirement for mandatory product registration before market entry. Operators should not be allowed to distribute products until the minimum registration data is completed and validated.\n2. Define standard data fields: product identity, operator, origin, compliance documents, and validity period. Additional fields can include batch number, category, storage requirements, and the institution responsible for approval.\n3. Create verification workflows linking registration status to inspection and licensing processes. Inspectors should be able to confirm whether a product is active, expired, suspended, or pending review without searching across multiple systems.\n4. Implement penalties for non-registration and incentives for early compliance. A balanced system should combine enforcement with support so that operators understand the process and complete it on time.\n5. Maintain a searchable audit trail. Every change to a registration record should be recorded so that authorities can see who updated the entry, when it was updated, and what changed.\n\nOperational Considerations:\nThe database should be built with clear ownership rules. Each institution should know which records it can create, approve, review, or suspend. The interface should allow both technical and non-technical staff to find records quickly, while still preserving the integrity of the data model. It is also useful to keep a simple status legend visible at all times so that users can understand the meaning of each record without training.\n\nExpected Result:\nA reliable national product registry that supports enforcement, improves traceability, and strengthens public trust. Once the system is in place, it becomes easier to spot irregularities, manage inspections, and respond to market risks in a coordinated way.",
        'institutions' => ['AIFAESA', 'DNRKPK-MCI'],
    ]);
}

function seed_institutions(SQLite3 $db): void
{
    $defaults = ['AIFAESA', 'INDIMO', 'MAE', 'PAM', 'DNRKPK-MIC', 'DNRKPK-MCI'];
    $stmt = $db->prepare('INSERT OR IGNORE INTO institutions (name) VALUES (:name)');
    foreach ($defaults as $name) {
        $stmt->bindValue(':name', $name);
        $stmt->execute();
    }
}

function migrate_legacy_article_institutions(SQLite3 $db): void
{
    $res = $db->query('SELECT id, institution_id FROM articles WHERE institution_id IS NOT NULL');
    $insert = $db->prepare('INSERT OR IGNORE INTO article_institutions (article_id, institution_id) VALUES (:article_id, :institution_id)');

    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $insert->bindValue(':article_id', (int)$row['id'], SQLITE3_INTEGER);
        $insert->bindValue(':institution_id', (int)$row['institution_id'], SQLITE3_INTEGER);
        $insert->execute();
    }
}

function seed_user(SQLite3 $db, array $user): void
{
    $institutionName = trim((string)($user['institution'] ?? ''));
    $institutionId = null;

    if ($institutionName !== '') {
        $db->exec("INSERT OR IGNORE INTO institutions (name) VALUES ('" . SQLite3::escapeString($institutionName) . "')");
        $institutionId = $db->querySingle("SELECT id FROM institutions WHERE name='" . SQLite3::escapeString($institutionName) . "'");
    }

    $stmt = $db->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->bindValue(':email', $user['email']);
    $res = $stmt->execute();
    if ($res->fetchArray(SQLITE3_ASSOC)) {
        return;
    }

    $hash = password_hash((string)$user['password'], PASSWORD_DEFAULT);
    $insert = $db->prepare("
        INSERT INTO users (name, position, institution_id, whatsapp, email, role, password_hash)
        VALUES (:name, :position, :institution_id, :whatsapp, :email, :role, :password_hash)
    ");
    $insert->bindValue(':name', trim((string)$user['name']));
    $insert->bindValue(':position', trim((string)$user['position']));
    $insert->bindValue(':institution_id', $institutionId, SQLITE3_INTEGER);
    $insert->bindValue(':whatsapp', trim((string)$user['whatsapp']));
    $insert->bindValue(':email', trim((string)$user['email']));
    $insert->bindValue(':role', in_array($user['role'] ?? 'user', ['admin', 'user'], true) ? $user['role'] : 'user');
    $insert->bindValue(':password_hash', $hash);
    $insert->execute();
}

function seed_article(SQLite3 $db, array $article): void
{
    $title = trim((string)($article['title'] ?? ''));
    $content = trim((string)($article['content'] ?? ''));
    $institutions = $article['institutions'] ?? [];

    if ($title === '' || $content === '' || !is_array($institutions)) {
        return;
    }

    $check = $db->prepare('SELECT id FROM articles WHERE title = :title LIMIT 1');
    $check->bindValue(':title', $title);
    $existing = $check->execute()->fetchArray(SQLITE3_ASSOC);
    if ($existing) {
        return;
    }

    $authorId = (int)$db->querySingle("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1");
    $insert = $db->prepare('INSERT INTO articles (title, content, institution_id, created_by) VALUES (:title, :content, NULL, :created_by)');
    $insert->bindValue(':title', $title);
    $insert->bindValue(':content', $content);
    $insert->bindValue(':created_by', $authorId > 0 ? $authorId : null, SQLITE3_INTEGER);
    $insert->execute();

    $articleId = (int)$db->lastInsertRowID();
    if ($articleId <= 0) {
        return;
    }

    $targetInsert = $db->prepare('INSERT OR IGNORE INTO article_institutions (article_id, institution_id) VALUES (:article_id, :institution_id)');
    foreach ($institutions as $institutionName) {
        $name = trim((string)$institutionName);
        if ($name === '') {
            continue;
        }

        $escaped = SQLite3::escapeString($name);
        $db->exec("INSERT OR IGNORE INTO institutions (name) VALUES ('$escaped')");
        $institutionId = (int)$db->querySingle("SELECT id FROM institutions WHERE name = '$escaped'");
        if ($institutionId <= 0) {
            continue;
        }

        $targetInsert->bindValue(':article_id', $articleId, SQLITE3_INTEGER);
        $targetInsert->bindValue(':institution_id', $institutionId, SQLITE3_INTEGER);
        $targetInsert->execute();
    }
}
