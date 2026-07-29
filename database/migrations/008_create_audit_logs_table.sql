CREATE TABLE audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NULL REFERENCES users(id),
    workspace_id INTEGER NULL REFERENCES workspaces(id),
    action VARCHAR(100) NOT NULL,
    meta TEXT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- {{DRIVER:pgsql}}
CREATE TABLE audit_logs (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NULL REFERENCES users(id),
    workspace_id INTEGER NULL REFERENCES workspaces(id),
    action VARCHAR(100) NOT NULL,
    meta TEXT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- {{END}}
