-- ============================================================================
-- ARSIPKU - SQL CLEAN RESET (MULAI DARI 0 DENGAN 1 USER & 1 WORKSPACE)
-- ============================================================================
-- Jalankan file ini di phpMyAdmin atau MySQL CLI untuk mereset data ke kondisi awal.
-- mysql -u root -p arsipku < clean_reset.sql
-- ============================================================================

USE `arsipku`;

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Kosongkan seluruh tabel
TRUNCATE TABLE `documents`;
TRUNCATE TABLE `folders`;
TRUNCATE TABLE `activities`;
TRUNCATE TABLE `member_invitations`;
TRUNCATE TABLE `workspace_invite_links`;
TRUNCATE TABLE `members`;
TRUNCATE TABLE `workspaces`;

SET FOREIGN_KEY_CHECKS = 1;

-- 2. Buat 1 Workspace Utama
INSERT INTO `workspaces` (`id`, `name`, `slug`, `type`, `storage_limit_bytes`, `plan_name`, `created_at`) 
VALUES ('ws_main', 'Workspace Utama', 'workspace-utama', 'company', 53687091200, 'Enterprise Business', NOW());

-- 3. Buat 1 User Utama (Owner)
INSERT INTO `members` (`id`, `workspace_id`, `name`, `email`, `avatar`, `role`, `status`, `joined_at`) 
VALUES ('usr_owner_01', 'ws_main', 'Admin Arsip', 'admin@arsipku.local', 'https://ui-avatars.com/api/?name=Admin+Arsip&background=4f46e5&color=fff', 'Owner', 'active', NOW());

-- 4. Catat aktivitas awal
INSERT INTO `activities` (`id`, `workspace_id`, `user_name`, `user_avatar`, `action_type`, `description`, `created_at`)
VALUES ('act_init_01', 'ws_main', 'Admin Arsip', 'https://ui-avatars.com/api/?name=Admin+Arsip&background=4f46e5&color=fff', 'workspace_init', 'Sistem ArsipKu berhasil diinisialisasi bersih dari nol', NOW());
