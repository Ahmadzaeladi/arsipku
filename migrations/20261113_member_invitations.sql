-- Apply once to an existing ArsipKu database to enable member invitations.
CREATE TABLE IF NOT EXISTS `workspace_invite_links` (
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

CREATE TABLE IF NOT EXISTS `member_invitations` (
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
