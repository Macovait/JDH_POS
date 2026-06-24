-- Notification jobs queue for async WhatsApp/SMS dispatch
CREATE TABLE IF NOT EXISTS notification_jobs (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id   INT UNSIGNED NOT NULL,
    type        VARCHAR(60)  NOT NULL,          -- order_placed, order_shipped, etc.
    payload     JSON         NOT NULL,
    status      ENUM('pending','processing','sent','failed') NOT NULL DEFAULT 'pending',
    attempts    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    error       TEXT         NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME    NULL,
    INDEX idx_nj_status   (status, created_at),
    INDEX idx_nj_tenant   (tenant_id),
    INDEX idx_nj_type     (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Domain mapping table for subdomain / custom domain per tenant
CREATE TABLE IF NOT EXISTS pos_domains (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id   INT UNSIGNED NOT NULL,
    domain      VARCHAR(253) NOT NULL,          -- e.g. myshop.platform.co.ke or www.myshop.co.ke
    active      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_domain (domain),
    INDEX idx_pd_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- online_order_items (if not already exists)
CREATE TABLE IF NOT EXISTS online_order_items (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id    BIGINT UNSIGNED NOT NULL,
    tenant_id   INT UNSIGNED    NOT NULL,
    product_id  INT UNSIGNED    NULL,
    name        VARCHAR(255)    NOT NULL,
    quantity    INT UNSIGNED    NOT NULL DEFAULT 1,
    price       DECIMAL(12,2)   NOT NULL,
    subtotal    DECIMAL(12,2)   NOT NULL,
    INDEX idx_ooi_order  (order_id),
    INDEX idx_ooi_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
