<?php
/**
 * ArsipKu - Database Connection & MySQL Auto-Migration/Seeder
 * PHP Native, No Framework
 */

declare(strict_types=1);

require_once __DIR__ . '/Config.php';

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            try {
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES ' . DB_CHARSET . ' COLLATE utf8mb4_unicode_ci'
                ];

                // 1. Coba koneksi langsung ke database yang ditentukan
                $dsnWithDb = sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    DB_HOST,
                    DB_PORT,
                    DB_NAME,
                    DB_CHARSET
                );

                try {
                    $pdo = new PDO($dsnWithDb, DB_USER, DB_PASS, $options);
                } catch (PDOException $e) {
                    // Jika database belum ada (error code 1049: Unknown database), buat otomatis
                    if ($e->getCode() === 1049 || str_contains($e->getMessage(), 'Unknown database')) {
                        $dsnNoDb = sprintf(
                            'mysql:host=%s;port=%d;charset=%s',
                            DB_HOST,
                            DB_PORT,
                            DB_CHARSET
                        );
                        $pdoHost = new PDO($dsnNoDb, DB_USER, DB_PASS, $options);
                        $pdoHost->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                        $pdoHost->exec("USE `" . DB_NAME . "`");
                        $pdo = $pdoHost;
                    } else {
                        throw $e;
                    }
                }

                // 2. Periksa apakah tabel workspaces sudah ada di database
                $checkStmt = $pdo->query("SHOW TABLES LIKE 'workspaces'");
                $hasTable = (bool)$checkStmt->fetch();

                if (!$hasTable) {
                    self::migrateAndSeed($pdo);
                }

                self::ensureUsersTable($pdo);
                self::ensureProfileColumns($pdo);
                self::ensureDocumentSharesTable($pdo);
                self::migrateLegacyDocumentFiles($pdo);
                self::$instance = $pdo;

            } catch (PDOException $e) {
                self::handleConnectionError($e);
            }
        }

        return self::$instance;
    }

    private static function ensureUsersTable(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS users (
                id VARCHAR(64) NOT NULL PRIMARY KEY,
                username VARCHAR(64) NOT NULL UNIQUE,
                name VARCHAR(150) NOT NULL,
                email VARCHAR(191) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                avatar_path VARCHAR(255) NULL,
                created_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    private static function ensureProfileColumns(PDO $db): void
    {
        $columns = [
            'users' => ['avatar_path', 'VARCHAR(255) NULL AFTER password_hash'],
            'workspaces' => ['logo_path', 'VARCHAR(255) NULL AFTER type'],
        ];
        foreach ($columns as $table => [$column, $definition]) {
            $stmt = $db->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
            if (!$stmt->fetch()) {
                $db->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
            }
        }
    }

    private static function ensureDocumentSharesTable(PDO $db): void
    {
        $tableExisted = (bool) $db->query("SHOW TABLES LIKE 'document_shares'")->fetchColumn();
        $db->exec("
            CREATE TABLE IF NOT EXISTS document_shares (
                document_id VARCHAR(64) NOT NULL,
                workspace_id VARCHAR(64) NOT NULL,
                shared_with_user_id VARCHAR(64) NOT NULL,
                shared_by_user_id VARCHAR(64) NULL,
                permission VARCHAR(20) NOT NULL DEFAULT 'view',
                created_at DATETIME NOT NULL,
                PRIMARY KEY (document_id, shared_with_user_id),
                INDEX idx_document_shares_recipient (workspace_id, shared_with_user_id),
                CONSTRAINT fk_document_shares_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
                CONSTRAINT fk_document_shares_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
                CONSTRAINT fk_document_shares_recipient FOREIGN KEY (shared_with_user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_document_shares_creator FOREIGN KEY (shared_by_user_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        if ($tableExisted) {
            return;
        }

        $db->exec("
            INSERT IGNORE INTO document_shares
                (document_id, workspace_id, shared_with_user_id, shared_by_user_id, permission, created_at)
            SELECT d.id, d.workspace_id, u.id, NULL,
                   CASE WHEN d.shared_permission = 'edit' THEN 'edit' ELSE 'view' END,
                   d.updated_at
            FROM documents d
            INNER JOIN members m ON m.workspace_id = d.workspace_id AND m.status = 'active'
            INNER JOIN users u ON LOWER(u.email) = LOWER(m.email)
            WHERE d.is_shared = 1 AND u.id <> d.uploaded_by;
        ");
    }

    private static function migrateLegacyDocumentFiles(PDO $db): void
    {
        $uploadRoot = realpath(UPLOAD_PATH);
        $privateRoot = realpath(DOCUMENT_UPLOAD_PATH);
        if ($uploadRoot === false || $privateRoot === false || $uploadRoot === $privateRoot) {
            return;
        }

        $stmt = $db->query("
            SELECT DISTINCT storage_path
            FROM documents
            WHERE storage_path IS NOT NULL AND storage_path <> ''
        ");
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $storagePath) {
            if (!is_string($storagePath) || basename($storagePath) !== $storagePath) {
                throw new RuntimeException('Lokasi dokumen lama tidak valid; migrasi penyimpanan dihentikan.');
            }
            $legacyPath = $uploadRoot . DIRECTORY_SEPARATOR . $storagePath;
            if (is_link($legacyPath)) {
                throw new RuntimeException('Lokasi dokumen lama menunjuk ke symbolic link; migrasi penyimpanan dihentikan.');
            }
            if (!is_file($legacyPath)) {
                continue;
            }

            $privatePath = $privateRoot . DIRECTORY_SEPARATOR . $storagePath;
            if (is_link($privatePath)) {
                throw new RuntimeException('Lokasi dokumen privat menunjuk ke symbolic link; migrasi penyimpanan dihentikan.');
            }
            if (!copy($legacyPath, $privatePath)) {
                throw new RuntimeException('Berkas dokumen lama tidak dapat dipindahkan ke penyimpanan privat.');
            }
            $legacySize = filesize($legacyPath);
            $privateSize = filesize($privatePath);
            $legacyHash = hash_file('sha256', $legacyPath);
            $privateHash = hash_file('sha256', $privatePath);
            if ($legacySize === false
                || $privateSize === false
                || $legacyHash === false
                || $privateHash === false
                || $legacySize !== $privateSize
                || !hash_equals($legacyHash, $privateHash)) {
                throw new RuntimeException('Berkas dokumen lama tidak dapat dipindahkan ke penyimpanan privat.');
            }
            if (!unlink($legacyPath)) {
                throw new RuntimeException('Berkas dokumen lama gagal dihapus setelah migrasi privat.');
            }
        }
    }

    private static function migrateAndSeed(PDO $db): void
    {
        // 1. Buat Tabel-tabel MySQL
        $db->exec("
            CREATE TABLE IF NOT EXISTS workspaces (
                id VARCHAR(64) NOT NULL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(191) NOT NULL UNIQUE,
                type VARCHAR(50) NOT NULL, -- 'personal', 'company', 'school'
                logo_path VARCHAR(255) NULL,
                storage_limit_bytes BIGINT NOT NULL,
                plan_name VARCHAR(100) DEFAULT 'Pro Plan',
                created_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS folders (
                id VARCHAR(64) NOT NULL PRIMARY KEY,
                workspace_id VARCHAR(64) NOT NULL,
                parent_id VARCHAR(64) NULL,
                name VARCHAR(255) NOT NULL,
                color VARCHAR(50) DEFAULT 'indigo',
                icon VARCHAR(50) DEFAULT 'folder',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL,
                INDEX idx_folders_workspace (workspace_id),
                INDEX idx_folders_parent (parent_id),
                CONSTRAINT fk_folders_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS documents (
                id VARCHAR(64) NOT NULL PRIMARY KEY,
                workspace_id VARCHAR(64) NOT NULL,
                folder_id VARCHAR(64) NULL,
                name VARCHAR(255) NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                mime_type VARCHAR(150) NOT NULL,
                extension VARCHAR(30) NOT NULL,
                size_bytes BIGINT NOT NULL,
                storage_path VARCHAR(255) NOT NULL,
                uploaded_by VARCHAR(64) NOT NULL,
                owner_name VARCHAR(150) NOT NULL,
                is_favorite TINYINT(1) DEFAULT 0,
                is_shared TINYINT(1) DEFAULT 0,
                shared_permission VARCHAR(20) DEFAULT 'view', -- 'view' or 'edit'
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL,
                INDEX idx_documents_workspace (workspace_id),
                INDEX idx_documents_folder (folder_id),
                INDEX idx_documents_deleted (deleted_at),
                CONSTRAINT fk_documents_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS members (
                id VARCHAR(64) NOT NULL PRIMARY KEY,
                workspace_id VARCHAR(64) NOT NULL,
                name VARCHAR(150) NOT NULL,
                email VARCHAR(191) NOT NULL,
                avatar TEXT NOT NULL,
                role VARCHAR(50) NOT NULL, -- 'Owner', 'Admin', 'Editor', 'Viewer'
                status VARCHAR(50) NOT NULL, -- 'active', 'invited'
                joined_at DATETIME NOT NULL,
                INDEX idx_members_workspace (workspace_id),
                CONSTRAINT fk_members_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS activities (
                id VARCHAR(64) NOT NULL PRIMARY KEY,
                workspace_id VARCHAR(64) NOT NULL,
                user_name VARCHAR(150) NOT NULL,
                user_avatar TEXT NOT NULL,
                action_type VARCHAR(50) NOT NULL, -- 'upload', 'rename', 'folder_create', 'delete', 'favorite', 'share'
                description TEXT NOT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_activities_workspace (workspace_id),
                CONSTRAINT fk_activities_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // 2. Seeding Workspaces
        $stmtWs = $db->prepare("INSERT INTO workspaces (id, name, slug, type, storage_limit_bytes, plan_name, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $wsList = [
            ['ws_abc', 'PT ABC Solusindo', 'pt-abc-solusindo', 'company', 50 * 1024 * 1024 * 1024, 'Enterprise Business', '2026-01-10 09:00:00'],
            ['ws_personal', 'Workspace Personal Ahmad', 'personal-ahmad', 'personal', 10 * 1024 * 1024 * 1024, 'Personal Pro', '2026-01-15 14:00:00'],
            ['ws_school', 'Sekolah Harapan Bangsa', 'sekolah-harapan-bangsa', 'school', 25 * 1024 * 1024 * 1024, 'Edu Institution', '2026-02-01 11:30:00'],
        ];

        foreach ($wsList as $ws) {
            $stmtWs->execute($ws);
        }

        // 3. Seeding Folders
        $stmtFolder = $db->prepare("INSERT INTO folders (id, workspace_id, parent_id, name, color, icon, created_at, updated_at, deleted_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $folders = [
            // PT ABC Solusindo
            ['fld_abc_adm', 'ws_abc', null, 'Administrasi Umum', 'indigo', 'folder', '2026-01-12 10:00:00', '2026-01-12 10:00:00', null],
            ['fld_abc_sm', 'ws_abc', 'fld_abc_adm', 'Surat Masuk', 'indigo', 'folder', '2026-01-12 10:30:00', '2026-01-12 10:30:00', null],
            ['fld_abc_sk', 'ws_abc', 'fld_abc_adm', 'Surat Keluar', 'indigo', 'folder', '2026-01-12 10:35:00', '2026-01-12 10:35:00', null],
            ['fld_abc_fin', 'ws_abc', null, 'Keuangan & Pajak', 'emerald', 'folder', '2026-01-15 08:20:00', '2026-01-15 08:20:00', null],
            ['fld_abc_inv', 'ws_abc', 'fld_abc_fin', 'Invoice 2026', 'emerald', 'folder', '2026-01-15 08:30:00', '2026-01-15 08:30:00', null],
            ['fld_abc_leg', 'ws_abc', null, 'Legalitas & Perjanjian', 'blue', 'folder', '2026-01-18 11:15:00', '2026-01-18 11:15:00', null],
            ['fld_abc_hr', 'ws_abc', null, 'Sumber Daya Manusia (SDM)', 'amber', 'folder', '2026-01-20 13:40:00', '2026-01-20 13:40:00', null],

            // Personal Ahmad
            ['fld_ahm_id', 'ws_personal', null, 'Dokumen Kependudukan', 'indigo', 'folder', '2026-01-16 10:00:00', '2026-01-16 10:00:00', null],
            ['fld_ahm_edu', 'ws_personal', null, 'Ijazah & Sertifikasi', 'emerald', 'folder', '2026-01-16 10:05:00', '2026-01-16 10:05:00', null],
            ['fld_ahm_fin', 'ws_personal', null, 'Keuangan & Asuransi Pribadi', 'blue', 'folder', '2026-01-16 10:10:00', '2026-01-16 10:10:00', null],

            // Sekolah
            ['fld_sch_cur', 'ws_school', null, 'Kurikulum Merdeka 2026', 'indigo', 'folder', '2026-02-05 09:00:00', '2026-02-05 09:00:00', null],
            ['fld_sch_tea', 'ws_school', null, 'Data Guru & Pegawai', 'emerald', 'folder', '2026-02-05 09:15:00', '2026-02-05 09:15:00', null],
        ];

        foreach ($folders as $f) {
            $stmtFolder->execute($f);
        }

        // 4. Buat contoh berkas fisik demo di penyimpanan privat
        self::createSampleFiles();

        // 5. Seeding Dokumen
        $stmtDoc = $db->prepare("
            INSERT INTO documents (
                id, workspace_id, folder_id, name, original_name, mime_type, 
                extension, size_bytes, storage_path, uploaded_by, owner_name, 
                is_favorite, is_shared, shared_permission, created_at, updated_at, deleted_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $docs = [
            // PT ABC Solusindo Documents
            [
                'doc_abc_01', 'ws_abc', 'fld_abc_leg', 'Surat_Perjanjian_Kerjasama_2026.pdf',
                'Surat_Perjanjian_Kerjasama_2026.pdf', 'application/pdf', 'pdf',
                2457600, 'sample_kontrak.pdf', 'usr_ahmad_01', 'Ahmad Suryanto',
                1, 1, 'edit', '2026-03-01 10:15:00', '2026-03-24 14:30:00', null
            ],
            [
                'doc_abc_02', 'ws_abc', 'fld_abc_fin', 'Laporan_Keuangan_Kuartal_I_2026.xlsx',
                'Laporan_Keuangan_Kuartal_I_2026.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xlsx',
                1887436, 'sample_laporan.xlsx', 'usr_siti_02', 'Siti Rahmawati',
                1, 0, 'view', '2026-03-10 11:00:00', '2026-03-23 09:45:00', null
            ],
            [
                'doc_abc_03', 'ws_abc', 'fld_abc_leg', 'Akta_Pendirian_PT_ABC_Solusindo.pdf',
                'Akta_Pendirian_PT_ABC_Solusindo.pdf', 'application/pdf', 'pdf',
                4404019, 'sample_kontrak.pdf', 'usr_ahmad_01', 'Ahmad Suryanto',
                0, 1, 'view', '2026-01-18 11:20:00', '2026-01-18 11:20:00', null
            ],
            [
                'doc_abc_04', 'ws_abc', 'fld_abc_hr', 'SOP_Karyawan_Work_From_Anywhere.pdf',
                'SOP_Karyawan_Work_From_Anywhere.pdf', 'application/pdf', 'pdf',
                1024000, 'sample_kontrak.pdf', 'usr_budi_03', 'Budi Santoso',
                0, 1, 'view', '2026-02-05 14:00:00', '2026-02-12 16:00:00', null
            ],
            [
                'doc_abc_05', 'ws_abc', 'fld_abc_adm', 'Desain_Branding_dan_Logo_ArsipKu.png',
                'Desain_Branding_dan_Logo_ArsipKu.png', 'image/png', 'png',
                3670016, 'sample_logo.png', 'usr_ahmad_01', 'Ahmad Suryanto',
                1, 0, 'view', '2026-02-20 15:30:00', '2026-03-20 10:10:00', null
            ],
            [
                'doc_abc_06', 'ws_abc', 'fld_abc_sk', 'Proposal_Tender_Digitalisasi_Arsip_2026.pdf',
                'Proposal_Tender_Digitalisasi_Arsip_2026.pdf', 'application/pdf', 'pdf',
                5347737, 'sample_kontrak.pdf', 'usr_ahmad_01', 'Ahmad Suryanto',
                0, 1, 'edit', '2026-03-15 13:20:00', '2026-03-22 17:15:00', null
            ],
            [
                'doc_abc_07', 'ws_abc', 'fld_abc_inv', 'Invoice_Vendor_Cloud_Hosting_AWS_032026.pdf',
                'Invoice_Vendor_Cloud_Hosting_AWS_032026.pdf', 'application/pdf', 'pdf',
                430080, 'sample_kontrak.pdf', 'usr_siti_02', 'Siti Rahmawati',
                0, 0, 'view', '2026-03-18 09:10:00', '2026-03-18 09:10:00', null
            ],
            [
                'doc_abc_08', 'ws_abc', null, 'Catatan_Rapat_Strategis_Q2.txt',
                'Catatan_Rapat_Strategis_Q2.txt', 'text/plain', 'txt',
                12288, 'sample_catatan.txt', 'usr_ahmad_01', 'Ahmad Suryanto',
                0, 0, 'view', '2026-03-22 16:45:00', '2026-03-22 16:45:00', null
            ],
            // Sampah PT ABC
            [
                'doc_abc_trash_01', 'ws_abc', 'fld_abc_hr', 'Draft_Kontrak_Kerja_Magang_Lama.docx',
                'Draft_Kontrak_Kerja_Magang_Lama.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'docx',
                524288, 'sample_kontrak.pdf', 'usr_budi_03', 'Budi Santoso',
                0, 0, 'view', '2026-01-25 10:00:00', '2026-03-10 11:00:00', '2026-03-15 08:30:00'
            ],
            [
                'doc_abc_trash_02', 'ws_abc', 'fld_abc_adm', 'Brosur_Event_Dibatalkan_2025.png',
                'Brosur_Event_Dibatalkan_2025.png', 'image/png', 'png',
                1468006, 'sample_logo.png', 'usr_ahmad_01', 'Ahmad Suryanto',
                0, 0, 'view', '2025-12-10 09:00:00', '2026-01-05 14:00:00', '2026-02-01 10:00:00'
            ],

            // Personal Ahmad Documents
            [
                'doc_ahm_01', 'ws_personal', 'fld_ahm_id', 'KTP_Ahmad_Suryanto.jpg',
                'KTP_Ahmad_Suryanto.jpg', 'image/jpeg', 'jpg',
                1258291, 'sample_ktp.jpg', 'usr_ahmad_01', 'Ahmad Suryanto',
                1, 0, 'view', '2026-01-16 10:15:00', '2026-01-16 10:15:00', null
            ],
            [
                'doc_ahm_02', 'ws_personal', 'fld_ahm_id', 'Kartu_Keluarga_Ahmad.pdf',
                'Kartu_Keluarga_Ahmad.pdf', 'application/pdf', 'pdf',
                912384, 'sample_kontrak.pdf', 'usr_ahmad_01', 'Ahmad Suryanto',
                0, 0, 'view', '2026-01-16 10:20:00', '2026-01-16 10:20:00', null
            ],
            [
                'doc_ahm_03', 'ws_personal', 'fld_ahm_edu', 'Ijazah_S1_Teknik_Informatika_ITB.pdf',
                'Ijazah_S1_Teknik_Informatika_ITB.pdf', 'application/pdf', 'pdf',
                3250585, 'sample_kontrak.pdf', 'usr_ahmad_01', 'Ahmad Suryanto',
                1, 1, 'view', '2026-01-16 10:25:00', '2026-01-16 10:25:00', null
            ],
            [
                'doc_ahm_04', 'ws_personal', 'fld_ahm_fin', 'Polis_Asuransi_Kesehatan_2026.pdf',
                'Polis_Asuransi_Kesehatan_2026.pdf', 'application/pdf', 'pdf',
                2831155, 'sample_kontrak.pdf', 'usr_ahmad_01', 'Ahmad Suryanto',
                0, 0, 'view', '2026-01-20 14:00:00', '2026-01-20 14:00:00', null
            ],

            // Sekolah Documents
            [
                'doc_sch_01', 'ws_school', 'fld_sch_cur', 'Silabus_Kurikulum_Merdeka_2026.pdf',
                'Silabus_Kurikulum_Merdeka_2026.pdf', 'application/pdf', 'pdf',
                3984588, 'sample_kontrak.pdf', 'usr_ahmad_01', 'Ahmad Suryanto',
                1, 1, 'view', '2026-02-05 09:30:00', '2026-02-05 09:30:00', null
            ],
            [
                'doc_sch_02', 'ws_school', 'fld_sch_tea', 'Data_Guru_dan_Staf_Pengajar.xlsx',
                'Data_Guru_dan_Staf_Pengajar.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xlsx',
                2202009, 'sample_laporan.xlsx', 'usr_ahmad_01', 'Ahmad Suryanto',
                0, 0, 'view', '2026-02-06 10:00:00', '2026-02-06 10:00:00', null
            ],
        ];

        foreach ($docs as $d) {
            $stmtDoc->execute($d);
        }

        // 6. Seeding Anggota
        $stmtMember = $db->prepare("INSERT INTO members (id, workspace_id, name, email, avatar, role, status, joined_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $members = [
            ['mem_1', 'ws_abc', 'Ahmad Suryanto', 'ahmad.suryanto@email.com', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop&q=80', 'Owner', 'active', '2026-01-10 09:00:00'],
            ['mem_2', 'ws_abc', 'Siti Rahmawati', 'siti.rahma@abcsolusindo.co.id', 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=150&auto=format&fit=crop&q=80', 'Admin', 'active', '2026-01-12 11:30:00'],
            ['mem_3', 'ws_abc', 'Budi Santoso', 'budi.santoso@abcsolusindo.co.id', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&auto=format&fit=crop&q=80', 'Editor', 'active', '2026-01-15 14:20:00'],
            ['mem_4', 'ws_abc', 'Dewi Lestari', 'dewi.lestari@abcsolusindo.co.id', 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=150&auto=format&fit=crop&q=80', 'Viewer', 'active', '2026-02-01 09:15:00'],
            ['mem_5', 'ws_abc', 'Rendra Pratama', 'rendra.pratama@mitra.co.id', 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150&auto=format&fit=crop&q=80', 'Viewer', 'invited', '2026-03-20 16:00:00'],
        ];

        foreach ($members as $m) {
            $stmtMember->execute($m);
        }

        // 7. Seeding Aktivitas
        $stmtAct = $db->prepare("INSERT INTO activities (id, workspace_id, user_name, user_avatar, action_type, description, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $activities = [
            ['act_1', 'ws_abc', 'Ahmad Suryanto', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop&q=80', 'upload', 'Mengunggah dokumen "Surat_Perjanjian_Kerjasama_2026.pdf"', '2026-03-24 14:30:00'],
            ['act_2', 'ws_abc', 'Siti Rahmawati', 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=150&auto=format&fit=crop&q=80', 'upload', 'Mengunggah "Laporan_Keuangan_Kuartal_I_2026.xlsx"', '2026-03-23 09:45:00'],
            ['act_3', 'ws_abc', 'Ahmad Suryanto', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop&q=80', 'rename', 'Mengubah nama dokumen menjadi "Catatan_Rapat_Strategis_Q2.txt"', '2026-03-22 16:45:00'],
            ['act_4', 'ws_abc', 'Budi Santoso', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&auto=format&fit=crop&q=80', 'folder_create', 'Membuat folder baru "Invoice 2026"', '2026-03-18 09:00:00'],
            ['act_5', 'ws_abc', 'Ahmad Suryanto', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop&q=80', 'share', 'Membagikan "Proposal_Tender_Digitalisasi_Arsip_2026.pdf"', '2026-03-15 13:20:00'],
        ];

        foreach ($activities as $a) {
            $stmtAct->execute($a);
        }
    }

    /**
     * Mengosongkan seluruh data (dokumen, folder, log aktivitas, members lain)
     * dan hanya menyisakan 1 workspace serta 1 user aktif (Owner).
     */
    public static function resetToClean(string $name, string $username, string $password): array
    {
        $db = self::getConnection();
        $name = trim($name);
        $username = strtolower(trim($username));
        if ($name === '' || mb_strlen($name) > 150) {
            throw new InvalidArgumentException('Nama akun harus berisi 1-150 karakter.');
        }
        if (!preg_match('/^[a-z0-9._-]{3,64}$/', $username)) {
            throw new InvalidArgumentException('Username harus 3-64 karakter: huruf kecil, angka, titik, garis bawah, atau tanda hubung.');
        }
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Password harus minimal 8 karakter.');
        }

        $email = $username . '@arsipku.local';
        $user = [
            'id' => 'usr_owner_01',
            'username' => $username,
            'name' => $name,
            'email' => $email,
            'role' => 'Owner',
            'avatar' => '',
        ];
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        if ($passwordHash === false) {
            throw new RuntimeException('Password tidak dapat diamankan.');
        }

        $now = date('Y-m-d H:i:s');

        $wsId = 'ws_main';
        $wsName = 'Workspace ' . $name;
        $wsSlug = 'workspace-utama';
        $limitBytes = 50 * 1024 * 1024 * 1024; // 50 GB

        $deletedFileCount = 0;
        $uploadFiles = [];
        foreach ([UPLOAD_PATH, DOCUMENT_UPLOAD_PATH] as $uploadDirectory) {
            if (!is_dir($uploadDirectory)) {
                continue;
            }
            $files = new FilesystemIterator($uploadDirectory, FilesystemIterator::SKIP_DOTS);
            foreach ($files as $file) {
                if ($file->isLink() || $file->isDir() || !$file->isFile()) {
                    throw new RuntimeException('Isi direktori upload bukan berkas biasa: ' . $file->getFilename());
                }
                $uploadFiles[] = $file->getPathname();
            }
        }

        $db->beginTransaction();
        try {
            $db->exec('DELETE FROM documents');
            $db->exec('DELETE FROM folders');
            $db->exec('DELETE FROM activities');
            $db->exec('DELETE FROM members');
            $db->exec('DELETE FROM users');
            $db->exec('DELETE FROM workspaces');

            $stmtWs = $db->prepare("
                INSERT INTO workspaces (id, name, slug, type, storage_limit_bytes, plan_name, created_at)
                VALUES (?, ?, ?, 'company', ?, 'Enterprise Business', ?)
            ");
            $stmtWs->execute([$wsId, $wsName, $wsSlug, $limitBytes, $now]);

            $stmtUser = $db->prepare("
                INSERT INTO users (id, username, name, email, password_hash, created_at)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmtUser->execute([
                $user['id'],
                $user['username'],
                $user['name'],
                $user['email'],
                $passwordHash,
                $now,
            ]);

            $stmtMem = $db->prepare("
                INSERT INTO members (id, workspace_id, name, email, avatar, role, status, joined_at)
                VALUES (?, ?, ?, ?, ?, 'Owner', 'active', ?)
            ");
            $stmtMem->execute([
                'mem_owner_01',
                $wsId,
                $user['name'],
                $user['email'],
                $user['avatar'],
                $now
            ]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        foreach ($uploadFiles as $uploadFile) {
            if (!unlink($uploadFile)) {
                throw new RuntimeException('Gagal menghapus berkas upload: ' . basename($uploadFile));
            }
            $deletedFileCount++;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION['authenticated_user_id'], $_SESSION['authenticated_user']);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['active_workspace'] = $wsSlug;
        }

        return [
            'workspace' => ['id' => $wsId, 'name' => $wsName, 'slug' => $wsSlug],
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'name' => $user['name'],
                'email' => $user['email'],
            ],
            'deleted_files' => $deletedFileCount
        ];
    }

    private static function handleConnectionError(PDOException $e): void
    {
        $isCli = (php_sapi_name() === 'cli');
        $isApi = (isset($_SERVER['REQUEST_URI']) && str_contains($_SERVER['REQUEST_URI'], '/api/')) ||
                 (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        if ($isCli) {
            fwrite(STDERR, "\n" . str_repeat('=', 60) . "\n");
            fwrite(STDERR, " [ARSIPKU] KONEKSI DATABASE MYSQL GAGAL\n");
            fwrite(STDERR, str_repeat('=', 60) . "\n");
            fwrite(STDERR, "Pesan Error : " . $e->getMessage() . "\n");
            fwrite(STDERR, "Target Host : " . DB_HOST . ":" . DB_PORT . "\n");
            fwrite(STDERR, "Database    : " . DB_NAME . "\n");
            fwrite(STDERR, "Username    : " . DB_USER . "\n");
            fwrite(STDERR, "Password    : " . (empty(DB_PASS) ? '(kosong)' : '******') . "\n");
            fwrite(STDERR, str_repeat('-', 60) . "\n");
            fwrite(STDERR, "Panduan:\n");
            fwrite(STDERR, "1. Pastikan server MySQL (XAMPP / Laragon / MySQL Service) sudah berjalan.\n");
            fwrite(STDERR, "2. Sesuaikan DB_USER dan DB_PASS di file .env atau src/Config.php.\n");
            fwrite(STDERR, str_repeat('=', 60) . "\n\n");
            exit(1);
        }

        if ($isApi) {
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode([
                'success' => false,
                'message' => 'Koneksi ke database MySQL gagal: ' . $e->getMessage(),
                'hint' => 'Pastikan server MySQL berjalan dan periksa kredensial DB_USER serta DB_PASS di berkas .env atau src/Config.php.'
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (!headers_sent()) {
            http_response_code(500);
        }
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Koneksi Database Gagal - ArsipKu</title>
            <style>
                * { box-sizing: border-box; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                    background: #0f172a;
                    color: #f8fafc;
                    padding: 2rem 1rem;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                    margin: 0;
                }
                .card {
                    background: #1e293b;
                    border: 1px solid #334155;
                    border-radius: 14px;
                    max-width: 620px;
                    width: 100%;
                    padding: 2.25rem;
                    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5), 0 8px 10px -6px rgba(0, 0, 0, 0.5);
                }
                .badge {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    background: rgba(239, 68, 68, 0.15);
                    color: #f87171;
                    padding: 4px 10px;
                    border-radius: 9999px;
                    font-size: 0.8rem;
                    font-weight: 600;
                    margin-bottom: 1rem;
                }
                h1 {
                    font-size: 1.5rem;
                    font-weight: 700;
                    color: #ffffff;
                    margin: 0 0 0.5rem 0;
                }
                p.desc {
                    color: #94a3b8;
                    font-size: 0.95rem;
                    line-height: 1.5;
                    margin: 0 0 1.25rem 0;
                }
                .error-box {
                    background: #0f172a;
                    border-left: 4px solid #ef4444;
                    border-radius: 6px;
                    padding: 12px 14px;
                    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
                    font-size: 0.85rem;
                    color: #fca5a5;
                    word-break: break-all;
                    margin-bottom: 1.5rem;
                }
                .config-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 1.5rem;
                    font-size: 0.875rem;
                }
                .config-table tr {
                    border-bottom: 1px solid #334155;
                }
                .config-table td {
                    padding: 8px 10px;
                }
                .config-table td:first-child {
                    color: #94a3b8;
                    width: 130px;
                    font-weight: 500;
                }
                .config-table td:last-child {
                    color: #f1f5f9;
                    font-family: monospace;
                }
                .instruction-box {
                    background: rgba(15, 23, 42, 0.6);
                    border: 1px solid #334155;
                    border-radius: 8px;
                    padding: 1rem 1.25rem;
                }
                .instruction-box h2 {
                    font-size: 0.95rem;
                    color: #38bdf8;
                    margin: 0 0 0.5rem 0;
                }
                .instruction-box ol {
                    margin: 0;
                    padding-left: 1.25rem;
                    color: #cbd5e1;
                    font-size: 0.9rem;
                    line-height: 1.6;
                }
                .instruction-box code {
                    background: #1e293b;
                    color: #e2e8f0;
                    padding: 2px 6px;
                    border-radius: 4px;
                    font-size: 0.85em;
                }
            </style>
        </head>
        <body>
            <div class="card">
                <div class="badge">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    Database Error
                </div>
                <h1>Koneksi ke MySQL Gagal</h1>
                <p class="desc">Sistem tidak dapat terhubung ke server database MySQL. Silakan periksa konfigurasi di bawah ini:</p>

                <div class="error-box">
                    <?= htmlspecialchars($e->getMessage()) ?>
                </div>

                <table class="config-table">
                    <tr><td>Host : Port</td><td><?= htmlspecialchars(DB_HOST) ?> : <?= htmlspecialchars((string)DB_PORT) ?></td></tr>
                    <tr><td>Nama Database</td><td><?= htmlspecialchars(DB_NAME) ?></td></tr>
                    <tr><td>Username</td><td><?= htmlspecialchars(DB_USER) ?></td></tr>
                    <tr><td>Password</td><td><?= empty(DB_PASS) ? '<span style="color:#64748b">(kosong)</span>' : '••••••••' ?></td></tr>
                </table>

                <div class="instruction-box">
                    <h2>Langkah Penyesuaian Kredensial:</h2>
                    <ol>
                        <li>Pastikan aplikasi server MySQL Anda (<strong>XAMPP</strong> / <strong>Laragon</strong> / MySQL Service) sudah dalam status <strong>Start / Running</strong>.</li>
                        <li>Buka berkas <code>.env</code> atau <code>src/Config.php</code> di folder root proyek.</li>
                        <li>Sesuaikan nilai <code>DB_USER</code> (misal: <code>root</code>) dan <code>DB_PASS</code> (isi password Anda jika ada, atau kosongkan jika tanpa password).</li>
                        <li>Simpan berkas lalu muat ulang (refresh) halaman ini. Tabel dan database akan otomatis diinisialisasi!</li>
                    </ol>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

    private static function createSampleFiles(): void
    {
        // 1. Buat PDF contoh
        $samplePdfPath = DOCUMENT_UPLOAD_PATH . DIRECTORY_SEPARATOR . 'sample_kontrak.pdf';
        if (!file_exists($samplePdfPath)) {
            $pdfContent = "%PDF-1.4\n" .
                "1 0 obj <</Type /Catalog /Pages 2 0 R>> endobj\n" .
                "2 0 obj <</Type /Pages /Kids [3 0 R] /Count 1>> endobj\n" .
                "3 0 obj <</Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj\n" .
                "4 0 obj <</Length 285>> stream\n" .
                "BT\n" .
                "/F1 20 Tf\n" .
                "50 720 Td (ARSIPKU - SISTEM ARSIP DIGITAL SAAS) Tj\n" .
                "/F1 14 Tf\n" .
                "0 -40 Td (SURAT PERJANJIAN KERJASAMA & DOKUMEN RESMI) Tj\n" .
                "/F1 10 Tf\n" .
                "0 -30 Td (Workspace: PT ABC Solusindo) Tj\n" .
                "0 -20 Td (Status: Terverifikasi & Terenkripsi) Tj\n" .
                "0 -20 Td (Tanggal Efektif: 2026-03-01) Tj\n" .
                "0 -40 Td (Dokumen ini dihasilkan secara otomatis oleh sistem ArsipKu.) Tj\n" .
                "ET\n" .
                "endstream\n" .
                "endobj\n" .
                "5 0 obj <</Type /Font /Subtype /Type1 /BaseFont /Helvetica>> endobj\n" .
                "xref\n" .
                "0 6\n" .
                "0000000000 65535 f \n" .
                "0000000009 00000 n \n" .
                "0000000058 00000 n \n" .
                "0000000115 00000 n \n" .
                "0000000251 00000 n \n" .
                "0000000588 00000 n \n" .
                "trailer <</Size 6 /Root 1 0 R>>\n" .
                "startxref\n" .
                "665\n" .
                "%%EOF";
            file_put_contents($samplePdfPath, $pdfContent);
        }

        // 2. Buat berkas teks contoh
        $sampleTxtPath = DOCUMENT_UPLOAD_PATH . DIRECTORY_SEPARATOR . 'sample_catatan.txt';
        if (!file_exists($sampleTxtPath)) {
            $txt = "ARSIPKU - NOTULENSI RAPAT STRATEGIS Q2 2026\n" .
                   "============================================\n" .
                   "Tanggal: 22 Maret 2026\n" .
                   "Peserta: Ahmad Suryanto, Siti Rahmawati, Budi Santoso\n\n" .
                   "Poin Keputusan:\n" .
                   "1. Migrasi seluruh arsip fisik divisi ke ArsipKu selesai akhir bulan.\n" .
                   "2. Penerapan role-based access control untuk setiap folder sensitif.\n" .
                   "3. Backup otomatis ke storage cadangan setiap pukul 02:00 WIB.\n";
            file_put_contents($sampleTxtPath, $txt);
        }

        // 3. Buat gambar logo contoh
        $sampleLogoPath = DOCUMENT_UPLOAD_PATH . DIRECTORY_SEPARATOR . 'sample_logo.png';
        if (!file_exists($sampleLogoPath)) {
            $tinyPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
            file_put_contents($sampleLogoPath, $tinyPng);
        }

        // 4. Buat gambar KTP contoh
        $sampleKtpPath = DOCUMENT_UPLOAD_PATH . DIRECTORY_SEPARATOR . 'sample_ktp.jpg';
        if (!file_exists($sampleKtpPath)) {
            $tinyJpg = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=');
            file_put_contents($sampleKtpPath, $tinyJpg);
        }
    }
}
