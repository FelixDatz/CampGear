-- =====================================================================
-- CampGear — skema database
-- Kompatibel: MariaDB 10.4+ / MySQL 8 (InnoDB, utf8mb4)
--
-- PERINGATAN: menjalankan file ini MENGHAPUS seluruh tabel CampGear
-- beserta datanya, lalu membuatnya ulang dalam keadaan kosong.
--
-- Cara pakai:
--   Lokal : php database/setup.php --fresh   (skema + data contoh)
--   Hosting: phpMyAdmin > pilih database > Import schema.sql lalu seed.sql
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS
    audit_logs,
    rate_limits,
    login_attempts,
    remember_tokens,
    damage_reports,
    handover_items,
    handover_checklists,
    cancellation_requests,
    payments,
    booking_items,
    bookings,
    package_items,
    packages,
    equipment,
    categories,
    articles,
    users;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Pengguna & keamanan
-- ---------------------------------------------------------------------

CREATE TABLE users (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name           VARCHAR(100) NOT NULL,
    email          VARCHAR(150) NOT NULL,
    phone          VARCHAR(20)  NULL,
    address        VARCHAR(255) NULL,
    password_hash  VARCHAR(255) NOT NULL,
    role           ENUM('user','staff','admin') NOT NULL DEFAULT 'user',
    is_active      TINYINT(1)   NOT NULL DEFAULT 1,
    last_login_at  DATETIME     NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Token "Ingat saya": hanya hash validator yang disimpan.
CREATE TABLE remember_tokens (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,
    selector        CHAR(24)     NOT NULL,
    validator_hash  CHAR(64)     NOT NULL,
    expires_at      DATETIME     NOT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_remember_selector (selector),
    KEY idx_remember_user (user_id),
    CONSTRAINT fk_remember_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Percobaan login gagal (pembatas brute-force).
CREATE TABLE login_attempts (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email         VARCHAR(150) NOT NULL,
    ip_address    VARCHAR(45)  NOT NULL,
    attempted_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_login_attempts_ip (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pembatas frekuensi permintaan (rate limiting) berbasis kunci/IP.
CREATE TABLE rate_limits (
    rate_key      VARCHAR(80)  NOT NULL,
    hits          INT UNSIGNED NOT NULL DEFAULT 1,
    reset_at      DATETIME     NOT NULL,
    PRIMARY KEY (rate_key),
    KEY idx_rate_limits_reset (reset_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Katalog
-- ---------------------------------------------------------------------

CREATE TABLE categories (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name         VARCHAR(80)  NOT NULL,
    slug         VARCHAR(100) NOT NULL,
    description  VARCHAR(255) NULL,
    icon         VARCHAR(50)  NULL COMMENT 'nama ikon Bootstrap Icons, mis. bi-house',
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE equipment (
    id                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    category_id       INT UNSIGNED  NOT NULL,
    name              VARCHAR(120)  NOT NULL,
    slug              VARCHAR(140)  NOT NULL,
    description       TEXT          NULL,
    image             VARCHAR(255)  NULL,
    stock_total       INT UNSIGNED  NOT NULL DEFAULT 0 COMMENT 'jumlah unit fisik yang dimiliki',
    daily_rate        DECIMAL(12,2) NOT NULL COMMENT 'tarif sewa per unit per hari',
    is_active         TINYINT(1)    NOT NULL DEFAULT 1,
    created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_equipment_slug (slug),
    KEY idx_equipment_category (category_id),
    KEY idx_equipment_active (is_active),
    CONSTRAINT fk_equipment_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE RESTRICT,
    CONSTRAINT chk_equipment_rate CHECK (daily_rate >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Paket sewa: kumpulan alat dengan diskon persentase.
CREATE TABLE packages (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name              VARCHAR(120) NOT NULL,
    slug              VARCHAR(140) NOT NULL,
    description       TEXT         NULL,
    image             VARCHAR(255) NULL,
    discount_percent  DECIMAL(5,2) NOT NULL DEFAULT 0,
    is_active         TINYINT(1)   NOT NULL DEFAULT 1,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_packages_slug (slug),
    CONSTRAINT chk_packages_discount CHECK (discount_percent BETWEEN 0 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE package_items (
    package_id    INT UNSIGNED NOT NULL,
    equipment_id  INT UNSIGNED NOT NULL,
    quantity      INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (package_id, equipment_id),
    KEY idx_package_items_equipment (equipment_id),
    CONSTRAINT fk_package_items_package FOREIGN KEY (package_id) REFERENCES packages (id) ON DELETE CASCADE,
    CONSTRAINT fk_package_items_equipment FOREIGN KEY (equipment_id) REFERENCES equipment (id) ON DELETE RESTRICT,
    CONSTRAINT chk_package_items_qty CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Transaksi sewa
-- ---------------------------------------------------------------------

-- Status booking:
--   pending_payment -> confirmed -> picked_up -> returned -> completed
--   pending_payment -> expired   (tidak dibayar sampai payment_due_at)
--   pending_payment/confirmed -> cancelled (pembatalan disetujui)
CREATE TABLE bookings (
    id              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    code            VARCHAR(20)       NOT NULL,
    user_id         INT UNSIGNED      NULL COMMENT 'NULL = booking tamu tanpa akun',
    customer_name   VARCHAR(100)      NOT NULL,
    customer_email  VARCHAR(150)      NOT NULL,
    customer_phone  VARCHAR(20)       NOT NULL,
    start_date      DATE              NOT NULL,
    end_date        DATE              NOT NULL COMMENT 'inklusif',
    total_days      SMALLINT UNSIGNED NOT NULL,
    subtotal        DECIMAL(12,2)     NOT NULL,
    discount_total  DECIMAL(12,2)     NOT NULL DEFAULT 0,
    grand_total     DECIMAL(12,2)     NOT NULL COMMENT 'biaya sewa yang dibayar = subtotal - diskon',
    status          ENUM('pending_payment','confirmed','picked_up','returned','completed','cancelled','expired')
                                      NOT NULL DEFAULT 'pending_payment',
    notes           VARCHAR(500)      NULL,
    -- Deposit dicatat secara administratif oleh staf (tidak dihitung otomatis).
    deposit_amount  DECIMAL(12,2)     NULL COMMENT 'nominal deposit yang diterima, diisi staf',
    deposit_status  ENUM('not_recorded','held','returned') NOT NULL DEFAULT 'not_recorded',
    deposit_note    VARCHAR(255)      NULL,
    payment_due_at  DATETIME          NULL,
    confirmed_at    DATETIME          NULL,
    picked_up_at    DATETIME          NULL,
    returned_at     DATETIME          NULL,
    completed_at    DATETIME          NULL,
    cancelled_at    DATETIME          NULL,
    created_at      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bookings_code (code),
    KEY idx_bookings_status_dates (status, start_date, end_date),
    KEY idx_bookings_user (user_id),
    KEY idx_bookings_email (customer_email),
    KEY idx_bookings_created (created_at),
    CONSTRAINT fk_bookings_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT chk_bookings_dates CHECK (end_date >= start_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rincian alat per booking. Tarif disalin (snapshot) saat booking dibuat,
-- sehingga perubahan tarif di kemudian hari tidak mengubah transaksi lama.
CREATE TABLE booking_items (
    id                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    booking_id        INT UNSIGNED  NOT NULL,
    equipment_id      INT UNSIGNED  NOT NULL,
    package_id        INT UNSIGNED  NULL COMMENT 'terisi bila alat berasal dari paket',
    quantity          INT UNSIGNED  NOT NULL,
    daily_rate        DECIMAL(12,2) NOT NULL,
    line_total        DECIMAL(12,2) NOT NULL COMMENT 'quantity x daily_rate x total_days',
    discount_amount   DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'diskon paket untuk baris ini',
    PRIMARY KEY (id),
    KEY idx_booking_items_booking (booking_id),
    KEY idx_booking_items_equipment (equipment_id, booking_id),
    CONSTRAINT fk_booking_items_booking FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE CASCADE,
    CONSTRAINT fk_booking_items_equipment FOREIGN KEY (equipment_id) REFERENCES equipment (id) ON DELETE RESTRICT,
    CONSTRAINT fk_booking_items_package FOREIGN KEY (package_id) REFERENCES packages (id) ON DELETE SET NULL,
    CONSTRAINT chk_booking_items_qty CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pembayaran (simulasi/dummy). purpose 'damage' = pembayaran penggantian kerusakan/kehilangan.
CREATE TABLE payments (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    booking_id  INT UNSIGNED  NOT NULL,
    purpose     ENUM('rental','damage') NOT NULL DEFAULT 'rental',
    method      ENUM('transfer','ewallet','cash') NOT NULL,
    amount      DECIMAL(12,2) NOT NULL,
    status      ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending',
    reference   VARCHAR(40)   NULL,
    proof_image VARCHAR(255)  NULL COMMENT 'path bukti pembayaran privat di storage/payments/',
    note        VARCHAR(255)  NULL COMMENT 'catatan staf saat verifikasi / alasan penolakan',
    handled_by  INT UNSIGNED  NULL COMMENT 'staff yang menerima pembayaran tunai atau verifikasi',
    paid_at     DATETIME      NULL,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payments_reference (reference),
    KEY idx_payments_booking (booking_id),
    KEY idx_payments_status_paid (status, paid_at),
    CONSTRAINT fk_payments_booking FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE CASCADE,
    CONSTRAINT fk_payments_staff FOREIGN KEY (handled_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT chk_payments_amount CHECK (amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pengajuan pembatalan. Disimpan terpisah dari booking agar riwayat,
-- alasan, peninjau, dan catatan refund/deposit tetap tercatat.
-- Nominal refund tidak dihitung otomatis; staf mencatatnya saat meninjau.
CREATE TABLE cancellation_requests (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    booking_id      INT UNSIGNED  NOT NULL,
    reason          TEXT          NOT NULL,
    status          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    requested_by    INT UNSIGNED  NULL COMMENT 'NULL = diajukan tamu lewat kode booking',
    reviewed_by     INT UNSIGNED  NULL,
    review_note     VARCHAR(500)  NULL,
    refund_amount   DECIMAL(12,2) NULL COMMENT 'nominal refund yang dicatat staf (opsional)',
    refund_note     VARCHAR(500)  NULL COMMENT 'catatan refund/deposit',
    refund_status   ENUM('not_applicable','pending','processed') NOT NULL DEFAULT 'not_applicable',
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at     DATETIME      NULL,
    PRIMARY KEY (id),
    KEY idx_cancellations_booking (booking_id),
    KEY idx_cancellations_status (status, created_at),
    CONSTRAINT fk_cancellations_booking FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE CASCADE,
    CONSTRAINT fk_cancellations_requester FOREIGN KEY (requested_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_cancellations_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Checklist serah terima saat pengambilan (pickup) dan pengembalian (return).
CREATE TABLE handover_checklists (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id  INT UNSIGNED NOT NULL,
    type        ENUM('pickup','return') NOT NULL,
    handled_by  INT UNSIGNED NULL,
    notes       VARCHAR(500) NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_handover_booking_type (booking_id, type),
    CONSTRAINT fk_handover_booking FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE CASCADE,
    CONSTRAINT fk_handover_staff FOREIGN KEY (handled_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE handover_items (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    checklist_id     INT UNSIGNED NOT NULL,
    booking_item_id  INT UNSIGNED NOT NULL,
    qty_good         INT UNSIGNED NOT NULL DEFAULT 0,
    qty_damaged      INT UNSIGNED NOT NULL DEFAULT 0,
    qty_lost         INT UNSIGNED NOT NULL DEFAULT 0,
    note             VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_handover_items (checklist_id, booking_item_id),
    KEY idx_handover_items_booking_item (booking_item_id),
    CONSTRAINT fk_handover_items_checklist FOREIGN KEY (checklist_id) REFERENCES handover_checklists (id) ON DELETE CASCADE,
    CONSTRAINT fk_handover_items_booking_item FOREIGN KEY (booking_item_id) REFERENCES booking_items (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Laporan kerusakan/kehilangan. Penyewa wajib mengganti; booking tidak bisa
-- diselesaikan selama masih ada laporan berstatus 'unsettled'.
-- Nominal penggantian dicatat staf per kasus (boleh kosong bila belum ditentukan).
CREATE TABLE damage_reports (
    id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    booking_id       INT UNSIGNED  NOT NULL,
    booking_item_id  INT UNSIGNED  NOT NULL,
    type             ENUM('damaged','lost') NOT NULL,
    quantity         INT UNSIGNED  NOT NULL,
    description      VARCHAR(500)  NULL,
    charge_amount    DECIMAL(12,2) NULL COMMENT 'nominal penggantian, diisi staf; NULL = belum ditentukan',
    status           ENUM('unsettled','settled') NOT NULL DEFAULT 'unsettled',
    settlement_note  VARCHAR(500)  NULL COMMENT 'cara penyelesaian penggantian',
    reported_by      INT UNSIGNED  NULL,
    settled_by       INT UNSIGNED  NULL,
    created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    settled_at       DATETIME      NULL,
    PRIMARY KEY (id),
    KEY idx_damage_booking (booking_id),
    KEY idx_damage_status (status),
    CONSTRAINT fk_damage_booking FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE CASCADE,
    CONSTRAINT fk_damage_booking_item FOREIGN KEY (booking_item_id) REFERENCES booking_items (id) ON DELETE CASCADE,
    CONSTRAINT fk_damage_reporter FOREIGN KEY (reported_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_damage_settler FOREIGN KEY (settled_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT chk_damage_values CHECK (quantity > 0 AND (charge_amount IS NULL OR charge_amount >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Konten & audit
-- ---------------------------------------------------------------------

CREATE TABLE articles (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    author_id     INT UNSIGNED NULL,
    title         VARCHAR(200) NOT NULL,
    slug          VARCHAR(220) NOT NULL,
    excerpt       VARCHAR(300) NULL,
    body          MEDIUMTEXT   NOT NULL,
    cover_image   VARCHAR(255) NULL,
    status        ENUM('draft','published') NOT NULL DEFAULT 'draft',
    published_at  DATETIME     NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_articles_slug (slug),
    KEY idx_articles_status_published (status, published_at),
    CONSTRAINT fk_articles_author FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catatan tindakan Staff/Admin.
CREATE TABLE audit_logs (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED    NULL,
    action       VARCHAR(60)     NOT NULL COMMENT 'mis. booking.confirm, equipment.update',
    entity_type  VARCHAR(40)     NULL,
    entity_id    INT UNSIGNED    NULL,
    description  VARCHAR(255)    NOT NULL DEFAULT '',
    meta         TEXT            NULL COMMENT 'JSON detail tambahan',
    ip_address   VARCHAR(45)     NULL,
    created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_user_created (user_id, created_at),
    KEY idx_audit_entity (entity_type, entity_id),
    KEY idx_audit_created (created_at),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
