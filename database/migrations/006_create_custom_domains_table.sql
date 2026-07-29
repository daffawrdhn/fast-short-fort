CREATE TABLE custom_domains (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    workspace_id INTEGER NOT NULL REFERENCES workspaces(id),
    domain VARCHAR(255) NOT NULL UNIQUE,
    verified_at DATETIME NULL,
    dns_record VARCHAR(255) NULL,
    is_active INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- {{DRIVER:pgsql}}
CREATE TABLE custom_domains (
    id SERIAL PRIMARY KEY,
    workspace_id INTEGER NOT NULL REFERENCES workspaces(id),
    domain VARCHAR(255) NOT NULL UNIQUE,
    verified_at TIMESTAMP NULL,
    dns_record VARCHAR(255) NULL,
    is_active BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- {{END}}
