CREATE TABLE boards (
    id SERIAL PRIMARY KEY,
    board_key VARCHAR(20) NOT NULL UNIQUE,
    title VARCHAR(100) NOT NULL,
    subtitle VARCHAR(255) NOT NULL,
    accent VARCHAR(30) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE threads (
    id VARCHAR(32) PRIMARY KEY,
    board_key VARCHAR(20) NOT NULL REFERENCES boards(board_key),
    subject VARCHAR(80) NOT NULL DEFAULT '',
    author_name VARCHAR(30) NOT NULL DEFAULT '익명',
    comment TEXT NOT NULL DEFAULT '',
    image_name VARCHAR(255),
    image_original_name VARCHAR(255),
    created_at TIMESTAMPTZ NOT NULL,
    bumped_at TIMESTAMPTZ NOT NULL
);

CREATE TABLE replies (
    id VARCHAR(32) PRIMARY KEY,
    thread_id VARCHAR(32) NOT NULL REFERENCES threads(id) ON DELETE CASCADE,
    author_name VARCHAR(30) NOT NULL DEFAULT '익명',
    comment TEXT NOT NULL DEFAULT '',
    image_name VARCHAR(255),
    image_original_name VARCHAR(255),
    created_at TIMESTAMPTZ NOT NULL
);

CREATE INDEX idx_threads_board_bumped_at ON threads(board_key, bumped_at DESC);
CREATE INDEX idx_replies_thread_created_at ON replies(thread_id, created_at ASC);
