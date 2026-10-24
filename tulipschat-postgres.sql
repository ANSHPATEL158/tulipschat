
BEGIN;

CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(30) NOT NULL UNIQUE,
    email VARCHAR(120) NOT NULL DEFAULT '',
    password VARCHAR(255) NOT NULL,
    role VARCHAR(10) NOT NULL DEFAULT 'user',
    status VARCHAR(10) NOT NULL DEFAULT 'active',
    avatar VARCHAR(100) NOT NULL DEFAULT '',
    gender VARCHAR(10) NOT NULL DEFAULT '',
    bio VARCHAR(300) NOT NULL DEFAULT '',
    last_ip VARCHAR(45) NOT NULL DEFAULT '',
    created_at INTEGER NOT NULL DEFAULT 0,
    last_seen INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE messages (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    message TEXT NOT NULL,
    created_at INTEGER NOT NULL DEFAULT 0
);

CREATE INDEX idx_msg_user ON messages (user_id);

CREATE TABLE settings (
    name VARCHAR(50) PRIMARY KEY,
    value TEXT NOT NULL
);

INSERT INTO settings (name, value) VALUES
('allow_register', '1'),
('announcement', 'Welcome to the chat!'),
('history', '50'),
('max_length', '500'),
('site_name', 'TulipsChat');

COMMIT;
