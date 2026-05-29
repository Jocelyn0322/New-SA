-- 使用者新增產品申請表
CREATE TABLE IF NOT EXISTS product_submissions (
    id           SERIAL PRIMARY KEY,
    username     VARCHAR(100) NOT NULL,
    product_name VARCHAR(200) NOT NULL,
    brand        VARCHAR(100),
    category     VARCHAR(50),
    description  TEXT,
    price        VARCHAR(50),
    purchase_link TEXT,
    status       VARCHAR(20)  DEFAULT 'pending',  -- pending / approved / rejected
    admin_note   TEXT,
    created_at   TIMESTAMP    DEFAULT NOW()
);

-- 使用者回報產品狀況表
CREATE TABLE IF NOT EXISTS product_reports (
    id          SERIAL PRIMARY KEY,
    username    VARCHAR(100) NOT NULL,
    product_id  INTEGER      NOT NULL,
    report_type VARCHAR(50)  NOT NULL,  -- discontinued / new_version / wrong_info / other
    description TEXT,
    status      VARCHAR(20)  DEFAULT 'pending',  -- pending / resolved
    created_at  TIMESTAMP    DEFAULT NOW()
);
