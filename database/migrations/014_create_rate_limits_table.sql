-- {{DRIVER:sqlite}}
CREATE TABLE IF NOT EXISTS rate_limits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    key_name TEXT NOT NULL,
    attempts INTEGER NOT NULL DEFAULT 0,
    expires_at TEXT NOT NULL,
    created_at TEXT DEFAULT (CURRENT_TIMESTAMP)
);
CREATE INDEX IF NOT EXISTS idx_rate_limits_key ON rate_limits(key_name);
CREATE INDEX IF NOT EXISTS idx_rate_limits_expires ON rate_limits(expires_at);
-- {{END}}
-- {{DRIVER:pgsql}}
CREATE TABLE IF NOT EXISTS rate_limits (
    id SERIAL PRIMARY KEY,
    key_name VARCHAR(255) NOT NULL,
    attempts INTEGER NOT NULL DEFAULT 0,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_rate_limits_key ON rate_limits(key_name);
CREATE INDEX idx_rate_limits_expires ON rate_limits(expires_at);
-- {{END}}
