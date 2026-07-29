-- {{DRIVER:sqlite}}
CREATE TABLE IF NOT EXISTS webhook_deliveries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    webhook_id INTEGER NOT NULL,
    event_type TEXT NOT NULL,
    request_body TEXT,
    response_status INTEGER,
    response_body TEXT,
    success INTEGER DEFAULT 0,
    attempted_at TEXT DEFAULT (CURRENT_TIMESTAMP),
    FOREIGN KEY (webhook_id) REFERENCES webhooks(id) ON DELETE CASCADE
);
-- {{END}}
-- {{DRIVER:pgsql}}
CREATE TABLE IF NOT EXISTS webhook_deliveries (
    id SERIAL PRIMARY KEY,
    webhook_id INTEGER NOT NULL REFERENCES webhooks(id) ON DELETE CASCADE,
    event_type VARCHAR(255) NOT NULL,
    request_body TEXT,
    response_status INTEGER,
    response_body TEXT,
    success BOOLEAN DEFAULT FALSE,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- {{END}}
