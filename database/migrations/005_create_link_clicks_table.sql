CREATE TABLE link_clicks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    link_id INTEGER NOT NULL REFERENCES links(id),
    ip_hash VARCHAR(64) NOT NULL,
    country VARCHAR(100) NULL,
    city VARCHAR(100) NULL,
    latitude DECIMAL(10,8) NULL,
    longitude DECIMAL(11,8) NULL,
    device_type VARCHAR(50) NULL,
    browser VARCHAR(100) NULL,
    browser_version VARCHAR(50) NULL,
    os VARCHAR(100) NULL,
    referrer TEXT NULL,
    user_agent TEXT NULL,
    clicked_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- {{DRIVER:pgsql}}
CREATE TABLE link_clicks (
    id SERIAL PRIMARY KEY,
    link_id INTEGER NOT NULL REFERENCES links(id),
    ip_hash VARCHAR(64) NOT NULL,
    country VARCHAR(100) NULL,
    city VARCHAR(100) NULL,
    latitude DECIMAL(10,8) NULL,
    longitude DECIMAL(11,8) NULL,
    device_type VARCHAR(50) NULL,
    browser VARCHAR(100) NULL,
    browser_version VARCHAR(50) NULL,
    os VARCHAR(100) NULL,
    referrer TEXT NULL,
    user_agent TEXT NULL,
    clicked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- {{END}}
