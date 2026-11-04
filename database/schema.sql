CREATE DATABASE IF NOT EXISTS pemweb_obe_proyeksemester
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pemweb_obe_proyeksemester;

CREATE TABLE IF NOT EXISTS users (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama          VARCHAR(100) NOT NULL,
    email         VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('admin','kasir') NOT NULL DEFAULT 'kasir',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS kategori (
    id   SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS menu (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kategori_id SMALLINT UNSIGNED NOT NULL,
    nama        VARCHAR(100) NOT NULL,
    deskripsi   VARCHAR(255) NOT NULL DEFAULT '',
    harga       INT UNSIGNED NOT NULL,
    stok        INT UNSIGNED NOT NULL DEFAULT 0,
    gambar      VARCHAR(255) NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_menu_kategori FOREIGN KEY (kategori_id) REFERENCES kategori(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pesanan (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id        BIGINT UNSIGNED NULL,
    nama_pelanggan VARCHAR(100) NOT NULL,
    status         ENUM('Baru','Diproses','Selesai','Dibatalkan') NOT NULL DEFAULT 'Baru',
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pesanan_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_pesanan_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS detail_pesanan (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pesanan_id   BIGINT UNSIGNED NOT NULL,
    menu_id      BIGINT UNSIGNED NOT NULL,
    jumlah       INT UNSIGNED NOT NULL DEFAULT 1,
    harga_satuan INT UNSIGNED NOT NULL,
    CONSTRAINT fk_detail_pesanan FOREIGN KEY (pesanan_id) REFERENCES pesanan(id) ON DELETE CASCADE,
    CONSTRAINT fk_detail_menu    FOREIGN KEY (menu_id)    REFERENCES menu(id),
    UNIQUE KEY uq_detail_pesanan_menu (pesanan_id, menu_id)
) ENGINE=InnoDB;