CREATE TABLE links (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    workspace_id INTEGER NOT NULL REFERENCES workspaces(id),
    user_id INTEGER NOT NULL REFERENCES users(id),
    original_url TEXT NOT NULL,
    slug VARCHAR(255) NOT NULL,
    custom_domain_id INTEGER NULL REFERENCES custom_domains(id),
    password_hash VARCHAR(255) NULL,
    expires_at DATETIME NULL,
    click_limit INTEGER NULL,
    is_active INTEGER DEFAULT 1,
    is_cloaked INTEGER DEFAULT 0,
    utm_source VARCHAR(255) NULL,
    utm_medium VARCHAR(255) NULL,
    utm_campaign VARCHAR(255) NULL,
    utm_term VARCHAR(255) NULL,
    utm_content VARCHAR(255) NULL,
    link_type VARCHAR(50) DEFAULT 'direct',
    deep_link_scheme VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(workspace_id, slug)
);

-- {{DRIVER:pgsql}}
CREATE TABLE links (
    id SERIAL PRIMARY KEY,
    workspace_id INTEGER NOT NULL REFERENCES workspaces(id),
    user_id INTEGER NOT NULL REFERENCES users(id),
    original_url TEXT NOT NULL,
    slug VARCHAR(255) NOT NULL,
    custom_domain_id INTEGER NULL REFERENCES custom_domains(id),
    password_hash VARCHAR(255) NULL,
    expires_at TIMESTAMP NULL,
    click_limit INTEGER NULL,
    is_active BOOLEAN DEFAULT TRUE,
    is_cloaked BOOLEAN DEFAULT FALSE,
    utm_source VARCHAR(255) NULL,
    utm_medium VARCHAR(255) NULL,
    utm_campaign VARCHAR(255) NULL,
    utm_term VARCHAR(255) NULL,
    utm_content VARCHAR(255) NULL,
    link_type VARCHAR(50) DEFAULT 'direct',
    deep_link_scheme VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(workspace_id, slug)
);
-- {{END}}
