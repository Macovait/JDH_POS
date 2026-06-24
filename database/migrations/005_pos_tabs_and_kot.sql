-- =====================================================================
-- POS: Open Tabs (running tables) + KOT (Kitchen Order Tickets) routing
-- =====================================================================
-- Safe to run multiple times (uses IF NOT EXISTS guards).
-- All tables are tenant-scoped and branch-scoped where applicable.

-- --- Open Tabs (a running tab/check assigned to a table or guest) ---
CREATE TABLE IF NOT EXISTS pos_tabs (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id       INT UNSIGNED NOT NULL,
    branch_id       INT UNSIGNED NOT NULL,
    tab_number      VARCHAR(32)  NOT NULL,
    table_number    VARCHAR(32)  DEFAULT NULL,
    guest_name      VARCHAR(120) DEFAULT NULL,
    server_user_id  INT UNSIGNED DEFAULT NULL,
    status          ENUM('open','settled','voided') NOT NULL DEFAULT 'open',
    subtotal        DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax             DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount        DECIMAL(12,2) NOT NULL DEFAULT 0,
    total           DECIMAL(12,2) NOT NULL DEFAULT 0,
    sale_id         INT UNSIGNED DEFAULT NULL,
    notes           TEXT         DEFAULT NULL,
    opened_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    settled_at      DATETIME     DEFAULT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pos_tabs_tenant_number (tenant_id, tab_number),
    KEY idx_pos_tabs_branch_status (branch_id, status),
    KEY idx_pos_tabs_table (table_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- Tab Items (rows added to a tab over time, possibly across multiple courses) ---
CREATE TABLE IF NOT EXISTS pos_tab_items (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id       INT UNSIGNED NOT NULL,
    tab_id          INT UNSIGNED NOT NULL,
    product_id      INT UNSIGNED DEFAULT NULL,
    name            VARCHAR(200) NOT NULL,
    qty             DECIMAL(10,3) NOT NULL DEFAULT 1,
    unit_price      DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount        DECIMAL(12,2) NOT NULL DEFAULT 0,
    line_total      DECIMAL(12,2) NOT NULL DEFAULT 0,
    course          VARCHAR(40)   DEFAULT NULL,   -- 'starter','main','dessert','drinks'
    station         VARCHAR(40)   DEFAULT 'kitchen', -- 'kitchen','bar','grill','pizza'
    seat_number     TINYINT UNSIGNED DEFAULT NULL,
    notes           TEXT          DEFAULT NULL,
    voided          TINYINT(1)    NOT NULL DEFAULT 0,
    sent_to_kot_at  DATETIME      DEFAULT NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pos_tab_items_tab (tab_id),
    KEY idx_pos_tab_items_station (station, sent_to_kot_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- KOT Print Jobs (groups of items sent to a station) ---
CREATE TABLE IF NOT EXISTS pos_kot_jobs (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id       INT UNSIGNED NOT NULL,
    branch_id       INT UNSIGNED NOT NULL,
    tab_id          INT UNSIGNED DEFAULT NULL,
    sale_id         INT UNSIGNED DEFAULT NULL,
    station         VARCHAR(40)   NOT NULL DEFAULT 'kitchen',
    ticket_number   VARCHAR(32)   NOT NULL,
    items_json      JSON          DEFAULT NULL,
    status          ENUM('pending','printed','failed','ack') NOT NULL DEFAULT 'pending',
    printed_at      DATETIME      DEFAULT NULL,
    acknowledged_at DATETIME      DEFAULT NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_kot_branch_station_status (branch_id, station, status),
    KEY idx_kot_tab (tab_id),
    KEY idx_kot_ticket (ticket_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- Station configuration per branch (which printer/IP per station) ---
CREATE TABLE IF NOT EXISTS pos_kot_stations (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id       INT UNSIGNED NOT NULL,
    branch_id       INT UNSIGNED NOT NULL,
    station         VARCHAR(40)   NOT NULL,   -- 'kitchen','bar','grill','pizza'
    label           VARCHAR(80)   NOT NULL,
    printer_target  VARCHAR(120)  DEFAULT NULL, -- IP:port or device id
    paper_width_mm  TINYINT UNSIGNED NOT NULL DEFAULT 80,
    active          TINYINT(1)    NOT NULL DEFAULT 1,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_station (tenant_id, branch_id, station)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default stations (commented out — uncomment if you want them auto-created)
-- INSERT IGNORE INTO pos_kot_stations (tenant_id, branch_id, station, label)
-- SELECT t.id, b.id, 'kitchen', 'Kitchen' FROM tenants t JOIN branches b ON b.tenant_id = t.id;
