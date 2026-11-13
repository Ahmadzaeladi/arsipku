-- ============================================================================
-- ARSIPKU - SAAS DIGITAL ARCHIVE MANAGEMENT SYSTEM
-- MySQL Database Schema & Seeder
-- ============================================================================
-- Anda dapat mengeksekusi script ini langsung di phpMyAdmin, MySQL Workbench,
-- HeidiSQL, atau terminal: mysql -u root -p < database.sql
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `arsipku` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `arsipku`;

-- Nonaktifkan pengecekan foreign key sementara untuk import bersih
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 0. Tabel: users (akun login)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` VARCHAR(64) NOT NULL PRIMARY KEY,
    `username` VARCHAR(64) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(191) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `avatar_path` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 1. Tabel: workspaces
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `workspaces`;
CREATE TABLE `workspaces` (
    `id` VARCHAR(64) NOT NULL PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(191) NOT NULL UNIQUE,
    `type` VARCHAR(50) NOT NULL COMMENT 'personal, company, school',
    `logo_path` VARCHAR(255) NULL,
    `storage_limit_bytes` BIGINT NOT NULL,
    `plan_name` VARCHAR(100) DEFAULT 'Pro Plan',
    `created_at` DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. Tabel: folders
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `folders`;
CREATE TABLE `folders` (
    `id` VARCHAR(64) NOT NULL PRIMARY KEY,
    `workspace_id` VARCHAR(64) NOT NULL,
    `parent_id` VARCHAR(64) NULL,
    `name` VARCHAR(255) NOT NULL,
    `color` VARCHAR(50) DEFAULT 'indigo',
    `icon` VARCHAR(50) DEFAULT 'folder',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `deleted_at` DATETIME NULL,
    INDEX `idx_folders_workspace` (`workspace_id`),
    INDEX `idx_folders_parent` (`parent_id`),
    CONSTRAINT `fk_folders_workspace` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. Tabel: documents
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `documents`;
CREATE TABLE `documents` (
    `id` VARCHAR(64) NOT NULL PRIMARY KEY,
    `workspace_id` VARCHAR(64) NOT NULL,
    `folder_id` VARCHAR(64) NULL,
    `name` VARCHAR(255) NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `mime_type` VARCHAR(150) NOT NULL,
    `extension` VARCHAR(30) NOT NULL,
    `size_bytes` BIGINT NOT NULL,
    `storage_path` VARCHAR(255) NOT NULL,
    `uploaded_by` VARCHAR(64) NOT NULL,
    `owner_name` VARCHAR(150) NOT NULL,
    `is_favorite` TINYINT(1) DEFAULT 0,
    `is_shared` TINYINT(1) DEFAULT 0,
    `shared_permission` VARCHAR(20) DEFAULT 'view' COMMENT 'view or edit',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `deleted_at` DATETIME NULL,
    INDEX `idx_documents_workspace` (`workspace_id`),
    INDEX `idx_documents_folder` (`folder_id`),
    INDEX `idx_documents_deleted` (`deleted_at`),
    CONSTRAINT `fk_documents_workspace` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4. Tabel: members
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `members`;
CREATE TABLE `members` (
    `id` VARCHAR(64) NOT NULL PRIMARY KEY,
    `workspace_id` VARCHAR(64) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(191) NOT NULL,
    `avatar` TEXT NOT NULL,
    `role` VARCHAR(50) NOT NULL COMMENT 'Owner, Admin, Editor, Viewer',
    `status` VARCHAR(50) NOT NULL COMMENT 'active, invited',
    `joined_at` DATETIME NOT NULL,
    INDEX `idx_members_workspace` (`workspace_id`),
    CONSTRAINT `fk_members_workspace` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4a. Undangan anggota dan tautan bergabung
-- ----------------------------------------------------------------------------
CREATE TABLE `workspace_invite_links` (
    `id` VARCHAR(64) NOT NULL PRIMARY KEY,
    `workspace_id` VARCHAR(64) NOT NULL,
    `token_hash` CHAR(64) NOT NULL UNIQUE,
    `role` VARCHAR(50) NOT NULL COMMENT 'Admin, Editor, Viewer',
    `approval_required` TINYINT(1) NOT NULL DEFAULT 1,
    `expires_at` DATETIME NOT NULL,
    `max_uses` INT UNSIGNED NULL,
    `uses_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_by_user_id` VARCHAR(64) NOT NULL,
    `revoked_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_invite_links_workspace` (`workspace_id`, `revoked_at`),
    CONSTRAINT `fk_invite_links_workspace` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_invite_links_creator` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `member_invitations` (
    `id` VARCHAR(64) NOT NULL PRIMARY KEY,
    `workspace_id` VARCHAR(64) NOT NULL,
    `invitee_user_id` VARCHAR(64) NOT NULL,
    `invited_by_user_id` VARCHAR(64) NULL,
    `invite_link_id` VARCHAR(64) NULL,
    `role` VARCHAR(50) NOT NULL COMMENT 'Admin, Editor, Viewer',
    `source` VARCHAR(20) NOT NULL COMMENT 'email, link',
    `status` VARCHAR(30) NOT NULL DEFAULT 'pending' COMMENT 'pending, accepted, declined, rejected, cancelled',
    `created_at` DATETIME NOT NULL,
    `responded_at` DATETIME NULL,
    INDEX `idx_member_invites_inbox` (`invitee_user_id`, `status`, `created_at`),
    INDEX `idx_member_invites_workspace` (`workspace_id`, `status`, `created_at`),
    INDEX `idx_member_invites_link` (`invite_link_id`),
    CONSTRAINT `fk_member_invites_workspace` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_member_invites_invitee` FOREIGN KEY (`invitee_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_member_invites_inviter` FOREIGN KEY (`invited_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_member_invites_link` FOREIGN KEY (`invite_link_id`) REFERENCES `workspace_invite_links` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 5. Tabel: activities
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `activities`;
CREATE TABLE `activities` (
    `id` VARCHAR(64) NOT NULL PRIMARY KEY,
    `workspace_id` VARCHAR(64) NOT NULL,
    `user_name` VARCHAR(150) NOT NULL,
    `user_avatar` TEXT NOT NULL,
    `action_type` VARCHAR(50) NOT NULL COMMENT 'upload, rename, folder_create, delete, favorite, share',
    `description` TEXT NOT NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_activities_workspace` (`workspace_id`),
    CONSTRAINT `fk_activities_workspace` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- SEED DATA AWAL
-- ============================================================================

-- Workspaces
INSERT INTO `workspaces` (`id`, `name`, `slug`, `type`, `storage_limit_bytes`, `plan_name`, `created_at`) VALUES
('ws_abc', 'PT ABC Solusindo', 'pt-abc-solusindo', 'company', 53687091200, 'Enterprise Business', '2026-01-10 09:00:00'),
('ws_personal', 'Workspace Personal Ahmad', 'personal-ahmad', 'personal', 10737418240, 'Personal Pro', '2026-01-15 14:00:00'),
('ws_school', 'Sekolah Harapan Bangsa', 'sekolah-harapan-bangsa', 'school', 26843545600, 'Edu Institution', '2026-02-01 11:30:00');

-- Folders
INSERT INTO `folders` (`id`, `workspace_id`, `parent_id`, `name`, `color`, `icon`, `created_at`, `updated_at`, `deleted_at`) VALUES
('fld_abc_adm', 'ws_abc', NULL, 'Administrasi Umum', 'indigo', 'folder', '2026-01-12 10:00:00', '2026-01-12 10:00:00', NULL),
('fld_abc_sm', 'ws_abc', 'fld_abc_adm', 'Surat Masuk', 'indigo', 'folder', '2026-01-12 10:30:00', '2026-01-12 10:30:00', NULL),
('fld_abc_sk', 'ws_abc', 'fld_abc_adm', 'Surat Keluar', 'indigo', 'folder', '2026-01-12 10:35:00', '2026-01-12 10:35:00', NULL),
('fld_abc_fin', 'ws_abc', NULL, 'Keuangan & Pajak', 'emerald', 'folder', '2026-01-15 08:20:00', '2026-01-15 08:20:00', NULL),
('fld_abc_inv', 'ws_abc', 'fld_abc_fin', 'Invoice 2026', 'emerald', 'folder', '2026-01-15 08:30:00', '2026-01-15 08:30:00', NULL),
('fld_abc_leg', 'ws_abc', NULL, 'Legalitas & Perjanjian', 'blue', 'folder', '2026-01-18 11:15:00', '2026-01-18 11:15:00', NULL),
('fld_abc_hr', 'ws_abc', NULL, 'Sumber Daya Manusia (SDM)', 'amber', 'folder', '2026-01-20 13:40:00', '2026-01-20 13:40:00', NULL),
('fld_ahm_id', 'ws_personal', NULL, 'Dokumen Kependudukan', 'indigo', 'folder', '2026-01-16 10:00:00', '2026-01-16 10:00:00', NULL),
('fld_ahm_edu', 'ws_personal', NULL, 'Ijazah & Sertifikasi', 'emerald', 'folder', '2026-01-16 10:05:00', '2026-01-16 10:05:00', NULL),
('fld_ahm_fin', 'ws_personal', NULL, 'Keuangan & Asuransi Pribadi', 'blue', 'folder', '2026-01-16 10:10:00', '2026-01-16 10:10:00', NULL),
('fld_sch_cur', 'ws_school', NULL, 'Kurikulum Merdeka 2026', 'indigo', 'folder', '2026-02-05 09:00:00', '2026-02-05 09:00:00', NULL),
('fld_sch_tea', 'ws_school', NULL, 'Data Guru & Pegawai', 'emerald', 'folder', '2026-02-05 09:15:00', '2026-02-05 09:15:00', NULL);

-- Documents
INSERT INTO `documents` (`id`, `workspace_id`, `folder_id`, `name`, `original_name`, `mime_type`, `extension`, `size_bytes`, `storage_path`, `uploaded_by`, `owner_name`, `is_favorite`, `is_shared`, `shared_permission`, `created_at`, `updated_at`, `deleted_at`) VALUES
('doc_abc_01', 'ws_abc', 'fld_abc_leg', 'Surat_Perjanjian_Kerjasama_2026.pdf', 'Surat_Perjanjian_Kerjasama_2026.pdf', 'application/pdf', 'pdf', 2457600, 'sample_kontrak.pdf', 'usr_ahmad_01', 'Ahmad Suryanto', 1, 1, 'edit', '2026-03-01 10:15:00', '2026-03-24 14:30:00', NULL),
('doc_abc_02', 'ws_abc', 'fld_abc_fin', 'Laporan_Keuangan_Kuartal_I_2026.xlsx', 'Laporan_Keuangan_Kuartal_I_2026.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xlsx', 1887436, 'sample_laporan.xlsx', 'usr_siti_02', 'Siti Rahmawati', 1, 0, 'view', '2026-03-10 11:00:00', '2026-03-23 09:45:00', NULL),
('doc_abc_03', 'ws_abc', 'fld_abc_leg', 'Akta_Pendirian_PT_ABC_Solusindo.pdf', 'Akta_Pendirian_PT_ABC_Solusindo.pdf', 'application/pdf', 'pdf', 4404019, 'sample_kontrak.pdf', 'usr_ahmad_01', 'Ahmad Suryanto', 0, 1, 'view', '2026-01-18 11:20:00', '2026-01-18 11:20:00', NULL),
('doc_abc_04', 'ws_abc', 'fld_abc_hr', 'SOP_Karyawan_Work_From_Anywhere.pdf', 'SOP_Karyawan_Work_From_Anywhere.pdf', 'application/pdf', 'pdf', 1024000, 'sample_kontrak.pdf', 'usr_budi_03', 'Budi Santoso', 0, 1, 'view', '2026-02-05 14:00:00', '2026-02-12 16:00:00', NULL),
('doc_abc_05', 'ws_abc', 'fld_abc_adm', 'Desain_Branding_dan_Logo_ArsipKu.png', 'Desain_Branding_dan_Logo_ArsipKu.png', 'image/png', 'png', 3670016, 'sample_logo.png', 'usr_ahmad_01', 'Ahmad Suryanto', 1, 0, 'view', '2026-02-20 15:30:00', '2026-03-20 10:10:00', NULL),
('doc_abc_06', 'ws_abc', 'fld_abc_sk', 'Proposal_Tender_Digitalisasi_Arsip_2026.pdf', 'Proposal_Tender_Digitalisasi_Arsip_2026.pdf', 'application/pdf', 'pdf', 5347737, 'sample_kontrak.pdf', 'usr_ahmad_01', 'Ahmad Suryanto', 0, 1, 'edit', '2026-03-15 13:20:00', '2026-03-22 17:15:00', NULL),
('doc_abc_07', 'ws_abc', 'fld_abc_inv', 'Invoice_Vendor_Cloud_Hosting_AWS_032026.pdf', 'Invoice_Vendor_Cloud_Hosting_AWS_032026.pdf', 'application/pdf', 'pdf', 430080, 'sample_kontrak.pdf', 'usr_siti_02', 'Siti Rahmawati', 0, 0, 'view', '2026-03-18 09:10:00', '2026-03-18 09:10:00', NULL),
('doc_abc_08', 'ws_abc', NULL, 'Catatan_Rapat_Strategis_Q2.txt', 'Catatan_Rapat_Strategis_Q2.txt', 'text/plain', 'txt', 12288, 'sample_catatan.txt', 'usr_ahmad_01', 'Ahmad Suryanto', 0, 0, 'view', '2026-03-22 16:45:00', '2026-03-22 16:45:00', NULL),
('doc_abc_trash_01', 'ws_abc', 'fld_abc_hr', 'Draft_Kontrak_Kerja_Magang_Lama.docx', 'Draft_Kontrak_Kerja_Magang_Lama.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'docx', 524288, 'sample_kontrak.pdf', 'usr_budi_03', 'Budi Santoso', 0, 0, 'view', '2026-01-25 10:00:00', '2026-03-10 11:00:00', '2026-03-15 08:30:00'),
('doc_abc_trash_02', 'ws_abc', 'fld_abc_adm', 'Brosur_Event_Dibatalkan_2025.png', 'Brosur_Event_Dibatalkan_2025.png', 'image/png', 'png', 1468006, 'sample_logo.png', 'usr_ahmad_01', 'Ahmad Suryanto', 0, 0, 'view', '2025-12-10 09:00:00', '2026-01-05 14:00:00', '2026-02-01 10:00:00'),
('doc_ahm_01', 'ws_personal', 'fld_ahm_id', 'KTP_Ahmad_Suryanto.jpg', 'KTP_Ahmad_Suryanto.jpg', 'image/jpeg', 'jpg', 1258291, 'sample_ktp.jpg', 'usr_ahmad_01', 'Ahmad Suryanto', 1, 0, 'view', '2026-01-16 10:15:00', '2026-01-16 10:15:00', NULL),
('doc_ahm_02', 'ws_personal', 'fld_ahm_id', 'Kartu_Keluarga_Ahmad.pdf', 'Kartu_Keluarga_Ahmad.pdf', 'application/pdf', 'pdf', 912384, 'sample_kontrak.pdf', 'usr_ahmad_01', 'Ahmad Suryanto', 0, 0, 'view', '2026-01-16 10:20:00', '2026-01-16 10:20:00', NULL),
('doc_ahm_03', 'ws_personal', 'fld_ahm_edu', 'Ijazah_S1_Teknik_Informatika_ITB.pdf', 'Ijazah_S1_Teknik_Informatika_ITB.pdf', 'application/pdf', 'pdf', 3250585, 'sample_kontrak.pdf', 'usr_ahmad_01', 'Ahmad Suryanto', 1, 1, 'view', '2026-01-16 10:25:00', '2026-01-16 10:25:00', NULL),
('doc_ahm_04', 'ws_personal', 'fld_ahm_fin', 'Polis_Asuransi_Kesehatan_2026.pdf', 'Polis_Asuransi_Kesehatan_2026.pdf', 'application/pdf', 'pdf', 2831155, 'sample_kontrak.pdf', 'usr_ahmad_01', 'Ahmad Suryanto', 0, 0, 'view', '2026-01-20 14:00:00', '2026-01-20 14:00:00', NULL),
('doc_sch_01', 'ws_school', 'fld_sch_cur', 'Silabus_Kurikulum_Merdeka_2026.pdf', 'Silabus_Kurikulum_Merdeka_2026.pdf', 'application/pdf', 'pdf', 3984588, 'sample_kontrak.pdf', 'usr_ahmad_01', 'Ahmad Suryanto', 1, 1, 'view', '2026-02-05 09:30:00', '2026-02-05 09:30:00', NULL),
('doc_sch_02', 'ws_school', 'fld_sch_tea', 'Data_Guru_dan_Staf_Pengajar.xlsx', 'Data_Guru_dan_Staf_Pengajar.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xlsx', 2202009, 'sample_laporan.xlsx', 'usr_ahmad_01', 'Ahmad Suryanto', 0, 0, 'view', '2026-02-06 10:00:00', '2026-02-06 10:00:00', NULL);

-- Members
INSERT INTO `members` (`id`, `workspace_id`, `name`, `email`, `avatar`, `role`, `status`, `joined_at`) VALUES
('mem_1', 'ws_abc', 'Ahmad Suryanto', 'ahmad.suryanto@email.com', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop&q=80', 'Owner', 'active', '2026-01-10 09:00:00'),
('mem_2', 'ws_abc', 'Siti Rahmawati', 'siti.rahma@abcsolusindo.co.id', 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=150&auto=format&fit=crop&q=80', 'Admin', 'active', '2026-01-12 11:30:00'),
('mem_3', 'ws_abc', 'Budi Santoso', 'budi.santoso@abcsolusindo.co.id', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&auto=format&fit=crop&q=80', 'Editor', 'active', '2026-01-15 14:20:00'),
('mem_4', 'ws_abc', 'Dewi Lestari', 'dewi.lestari@abcsolusindo.co.id', 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=150&auto=format&fit=crop&q=80', 'Viewer', 'active', '2026-02-01 09:15:00'),
('mem_5', 'ws_abc', 'Rendra Pratama', 'rendra.pratama@mitra.co.id', 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150&auto=format&fit=crop&q=80', 'Viewer', 'invited', '2026-03-20 16:00:00');

-- Activities
INSERT INTO `activities` (`id`, `workspace_id`, `user_name`, `user_avatar`, `action_type`, `description`, `created_at`) VALUES
('act_1', 'ws_abc', 'Ahmad Suryanto', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop&q=80', 'upload', 'Mengunggah dokumen "Surat_Perjanjian_Kerjasama_2026.pdf"', '2026-03-24 14:30:00'),
('act_2', 'ws_abc', 'Siti Rahmawati', 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=150&auto=format&fit=crop&q=80', 'upload', 'Mengunggah "Laporan_Keuangan_Kuartal_I_2026.xlsx"', '2026-03-23 09:45:00'),
('act_3', 'ws_abc', 'Ahmad Suryanto', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop&q=80', 'rename', 'Mengubah nama dokumen menjadi "Catatan_Rapat_Strategis_Q2.txt"', '2026-03-22 16:45:00'),
('act_4', 'ws_abc', 'Budi Santoso', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&auto=format&fit=crop&q=80', 'folder_create', 'Membuat folder baru "Invoice 2026"', '2026-03-18 09:00:00'),
('act_5', 'ws_abc', 'Ahmad Suryanto', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop&q=80', 'share', 'Membagikan "Proposal_Tender_Digitalisasi_Arsip_2026.pdf"', '2026-03-15 13:20:00');
