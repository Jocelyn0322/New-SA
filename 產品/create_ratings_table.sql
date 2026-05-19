-- 產品屬性評分表
CREATE TABLE IF NOT EXISTS product_ratings (
    id         SERIAL PRIMARY KEY,
    product_id INTEGER      NOT NULL,
    username   VARCHAR(100) NOT NULL,
    attribute  VARCHAR(50)  NOT NULL,
    score      INTEGER      NOT NULL CHECK (score >= 1 AND score <= 5),
    created_at TIMESTAMP    DEFAULT NOW(),
    UNIQUE (product_id, username, attribute)
);
