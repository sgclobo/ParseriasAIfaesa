<?php
/**
 * db.php — SQLite connection & schema bootstrap
 */

define('DB_PATH', __DIR__ . '/data/parseiras.db');

function get_db(): SQLite3 {
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

function init_schema(SQLite3 $db): void {
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
    ");

    // Seed default admin if no users exist
    $count = $db->querySingle('SELECT COUNT(*) FROM users');
    if ($count == 0) {
        // Default institution
        $db->exec("INSERT OR IGNORE INTO institutions (name) VALUES ('AIFAESA')");
        $instId = $db->lastInsertRowID();
        if (!$instId) {
            $instId = $db->querySingle("SELECT id FROM institutions WHERE name='AIFAESA'");
        }

        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $db->prepare("
            INSERT INTO users (name, position, institution_id, email, role, password_hash)
            VALUES (:n, :p, :i, :e, 'admin', :h)
        ");
        $stmt->bindValue(':n', 'Administrator');
        $stmt->bindValue(':p', 'System Admin');
        $stmt->bindValue(':i', $instId);
        $stmt->bindValue(':e', 'admin@aifaesa.tl');
        $stmt->bindValue(':h', $hash);
        $stmt->execute();
    }
}
