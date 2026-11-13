-- Apply once to an existing ArsipKu database to enable recipient-scoped document sharing.
CREATE TABLE IF NOT EXISTS `document_shares` (
    `document_id` VARCHAR(64) NOT NULL,
    `workspace_id` VARCHAR(64) NOT NULL,
    `shared_with_user_id` VARCHAR(64) NOT NULL,
    `shared_by_user_id` VARCHAR(64) NULL,
    `permission` VARCHAR(20) NOT NULL DEFAULT 'view' COMMENT 'view or edit',
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`document_id`, `shared_with_user_id`),
    INDEX `idx_document_shares_recipient` (`workspace_id`, `shared_with_user_id`),
    CONSTRAINT `fk_document_shares_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_document_shares_workspace` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_document_shares_recipient` FOREIGN KEY (`shared_with_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_document_shares_creator` FOREIGN KEY (`shared_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Preserve the existing workspace-wide behavior for documents already marked shared.
INSERT IGNORE INTO `document_shares`
    (`document_id`, `workspace_id`, `shared_with_user_id`, `shared_by_user_id`, `permission`, `created_at`)
SELECT d.id, d.workspace_id, u.id, NULL,
       CASE WHEN d.shared_permission = 'edit' THEN 'edit' ELSE 'view' END,
       d.updated_at
FROM documents d
INNER JOIN members m ON m.workspace_id = d.workspace_id AND m.status = 'active'
INNER JOIN users u ON LOWER(u.email) = LOWER(m.email)
WHERE d.is_shared = 1 AND u.id <> d.uploaded_by;
