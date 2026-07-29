-- {{DRIVER:sqlite}}
CREATE TABLE IF NOT EXISTS blocklist (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    pattern TEXT NOT NULL,
    created_at TEXT DEFAULT (CURRENT_TIMESTAMP)
);
CREATE INDEX IF NOT EXISTS idx_blocklist_pattern ON blocklist(pattern);
-- {{END}}
-- {{DRIVER:pgsql}}
CREATE TABLE IF NOT EXISTS blocklist (
    id SERIAL PRIMARY KEY,
    pattern VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_blocklist_pattern ON blocklist(pattern);
-- {{END}}
