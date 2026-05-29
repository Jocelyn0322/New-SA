-- =====================================================
-- Migration 2: 三個 table 合併
-- 在 Supabase > SQL Editor 貼上並執行
-- =====================================================

-- ══════════════════════════════════════════════════
-- 方案A：user_lab_weights → 合併進 users
-- ══════════════════════════════════════════════════

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS weight_l FLOAT DEFAULT 1.0,
    ADD COLUMN IF NOT EXISTS weight_a FLOAT DEFAULT 1.0,
    ADD COLUMN IF NOT EXISTS weight_b FLOAT DEFAULT 1.0;

UPDATE users u
SET weight_l = w.weight_l,
    weight_a = w.weight_a,
    weight_b = w.weight_b
FROM user_lab_weights w
WHERE u.username = w.username;

DROP TABLE IF EXISTS user_lab_weights;

-- ══════════════════════════════════════════════════
-- 方案B：user_product_clicks + user_product_feedback
--        → user_product_interactions
-- ══════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS user_product_interactions (
    id                  SERIAL PRIMARY KEY,
    username            VARCHAR(100) NOT NULL,
    product_id          VARCHAR(100) NOT NULL,
    source              VARCHAR(50)  DEFAULT 'ai_recommendation',
    click_count         INTEGER      DEFAULT 1,
    clicked_at          TIMESTAMP    DEFAULT NOW(),
    feedback_type       VARCHAR(50),
    detected_l          FLOAT,
    detected_a          FLOAT,
    detected_b          FLOAT,
    feedback_updated_at TIMESTAMP,
    UNIQUE (username, product_id)
);

INSERT INTO user_product_interactions (username, product_id, source, clicked_at)
SELECT DISTINCT ON (username, product_id) username, product_id, source, clicked_at
FROM user_product_clicks
ORDER BY username, product_id, clicked_at DESC
ON CONFLICT (username, product_id) DO NOTHING;

UPDATE user_product_interactions i
SET feedback_type       = f.feedback_type,
    detected_l          = f.detected_l,
    detected_a          = f.detected_a,
    detected_b          = f.detected_b,
    feedback_updated_at = f.updated_at
FROM user_product_feedback f
WHERE i.username = f.username AND i.product_id = f.product_id;

DROP TABLE IF EXISTS user_product_clicks;
DROP TABLE IF EXISTS user_product_feedback;

-- ══════════════════════════════════════════════════
-- 方案C：product_submissions + product_reports
--        → product_requests
-- ══════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS product_requests (
    id            SERIAL PRIMARY KEY,
    type          VARCHAR(20)  NOT NULL CHECK (type IN ('submission', 'report')),
    username      VARCHAR(100) NOT NULL,
    description   TEXT,
    status        VARCHAR(20)  DEFAULT 'pending',
    admin_note    TEXT,
    created_at    TIMESTAMP    DEFAULT NOW(),
    product_name  VARCHAR(200),
    brand         VARCHAR(100),
    category      VARCHAR(50),
    price         VARCHAR(50),
    purchase_link TEXT,
    product_id    INTEGER,
    report_type   VARCHAR(50)
);

INSERT INTO product_requests
    (type, username, product_name, brand, category, description, price, purchase_link, status, admin_note, created_at)
SELECT
    'submission', username, product_name, brand, category, description, price, purchase_link, status, admin_note, created_at
FROM product_submissions;

INSERT INTO product_requests
    (type, username, product_id, report_type, description, status, created_at)
SELECT
    'report', username, product_id, report_type, description, status, created_at
FROM product_reports;

DROP TABLE IF EXISTS product_submissions;
DROP TABLE IF EXISTS product_reports;
