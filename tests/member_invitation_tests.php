<?php
/**
 * Focused integration tests for registration and workspace invitations.
 * Requires the database schema and the member invitation migration.
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rootDir = dirname(__DIR__);
require_once $rootDir . '/src/Config.php';
require_once $rootDir . '/src/Database.php';
require_once $rootDir . '/src/Services/AuthService.php';
require_once $rootDir . '/src/Services/MemberService.php';

$db = Database::getConnection();
$oldSession = $_SESSION;
$createdUsers = [];
$createdWorkspaces = [];
$checks = 0;

$assert = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
    $checks++;
    echo '[PASS] ' . $message . "\n";
};

$createAccount = static function (string $label) use (&$createdUsers, &$createdWorkspaces): array {
    $auth = new AuthService();
    $email = strtolower(str_replace(' ', '-', $label)) . '-' . bin2hex(random_bytes(5)) . '@example.com';
    $session = $auth->register($label, $email, 'test-password-123', 'test-password-123');
    $createdUsers[] = $session['user']['id'];
    foreach ($session['workspaces'] as $workspace) {
        $createdWorkspaces[] = $workspace['id'];
    }
    return ['id' => $session['user']['id'], 'email' => $email, 'name' => $label];
};

$switchUser = static function (array $user): void {
    $_SESSION['authenticated_user_id'] = $user['id'];
    unset($_SESSION['authenticated_user']);
    (new AuthService())->current();
};

$expectConflict = static function (callable $call, string $message) use ($assert): void {
    try {
        $call();
    } catch (MemberConflictException $e) {
        $assert(true, $message);
        return;
    }
    $assert(false, $message);
};

try {
    $owner = $createAccount('Invite owner');
    $ownerWorkspace = (new AuthService())->sessionData()['workspaces'][0]['id'];
    $invitee = $createAccount('Email invitee');
    $decliner = $createAccount('Declining invitee');
    $linkMember = $createAccount('Link member');
    $approvalMember = $createAccount('Approval member');

    $switchUser($owner);
    $members = new MemberService();
    $emailInvite = $members->inviteByEmail($ownerWorkspace, $invitee['email'], 'Editor');
    $assert($emailInvite['status'] === 'pending', 'email invite starts pending');
    $expectConflict(
        static function () use ($members, $ownerWorkspace, $emailInvite): void {
            $members->resolveWorkspaceInvitation($ownerWorkspace, $emailInvite['id'], true);
        },
        'workspace owner cannot accept an email invitation on the recipient behalf'
    );

    $switchUser($invitee);
    $assert(count($members->getMyInvitations()) === 1, 'invited account sees an in-app notification');
    $stmt = $db->prepare("SELECT id FROM members WHERE workspace_id = ? AND email = ? AND status = 'active'");
    $stmt->execute([$ownerWorkspace, $invitee['email']]);
    $assert(!$stmt->fetch(), 'email invite does not grant access before acceptance');
    $members->respondToInvitation($emailInvite['id'], true);
    $stmt = $db->prepare("SELECT role FROM members WHERE workspace_id = ? AND email = ? AND status = 'active'");
    $stmt->execute([$ownerWorkspace, $invitee['email']]);
    $assert($stmt->fetchColumn() === 'Editor', 'accepting email invite applies inviter-selected role');

    $switchUser($owner);
    $declineInvite = $members->inviteByEmail($ownerWorkspace, $decliner['email'], 'Viewer');
    $switchUser($decliner);
    $members->respondToInvitation($declineInvite['id'], false);
    $stmt = $db->prepare("SELECT id FROM members WHERE workspace_id = ? AND email = ? AND status = 'active'");
    $stmt->execute([$ownerWorkspace, $decliner['email']]);
    $assert(!$stmt->fetch(), 'declining email invite does not add a member');

    $switchUser($owner);
    $autoLink = $members->createInviteLink($ownerWorkspace, 'Viewer', false, 7, 1);
    $switchUser($linkMember);
    $autoJoin = $members->joinViaLink($autoLink['token']);
    $assert($autoJoin['status'] === 'accepted' && $autoJoin['role'] === 'Viewer', 'share link immediately joins using its preset role');
    $expectConflict(
        static function () use ($members, $autoLink): void {
            $members->joinViaLink($autoLink['token']);
        },
        'share link cannot be reused by an existing member after its use limit'
    );

    $switchUser($owner);
    $approvalLink = $members->createInviteLink($ownerWorkspace, 'Admin', true);
    $switchUser($approvalMember);
    $pendingJoin = $members->joinViaLink($approvalLink['token']);
    $assert($pendingJoin['status'] === 'pending', 'approval link creates a pending join request');
    $stmt = $db->prepare("SELECT id FROM members WHERE workspace_id = ? AND email = ? AND status = 'active'");
    $stmt->execute([$ownerWorkspace, $approvalMember['email']]);
    $assert(!$stmt->fetch(), 'approval link grants no access before owner approval');
    $stmt = $db->prepare("SELECT id FROM member_invitations WHERE workspace_id = ? AND invitee_user_id = ? AND source = 'link' AND status = 'pending'");
    $stmt->execute([$ownerWorkspace, $approvalMember['id']]);
    $pendingRequestId = $stmt->fetchColumn();
    $expectConflict(
        static function () use ($members, $pendingRequestId): void {
            $members->respondToInvitation((string) $pendingRequestId, true);
        },
        'link requester cannot bypass owner approval'
    );

    $switchUser($owner);
    $requests = $members->getWorkspaceInvitations($ownerWorkspace);
    $requestId = null;
    foreach ($requests as $request) {
        if ($request['invitee_user_id'] === $approvalMember['id']) {
            $requestId = $request['id'];
            break;
        }
    }
    $assert(is_string($requestId), 'owner sees pending link request');
    $members->resolveWorkspaceInvitation($ownerWorkspace, $requestId, true);
    $stmt = $db->prepare("SELECT role FROM members WHERE workspace_id = ? AND email = ? AND status = 'active'");
    $stmt->execute([$ownerWorkspace, $approvalMember['email']]);
    $assert($stmt->fetchColumn() === 'Admin', 'owner approval grants the link-configured role');
    $stmt = $db->prepare("SELECT id FROM members WHERE workspace_id = ? AND email = ? AND status = 'active'");
    $stmt->execute([$ownerWorkspace, $approvalMember['email']]);
    $adminMemberId = (string) $stmt->fetchColumn();
    $switchUser($approvalMember);
    $expectConflict(
        static function () use ($members, $adminMemberId, $ownerWorkspace): void {
            $members->remove($adminMemberId, $ownerWorkspace);
        },
        'admin cannot remove themselves from a workspace'
    );

    $switchUser($linkMember);
    try {
        $members->createInviteLink($ownerWorkspace, 'Admin', false);
        $assert(false, 'non-admin cannot create a workspace invite link');
    } catch (MemberAuthorizationException $e) {
        $assert(true, 'non-admin cannot create a workspace invite link');
    }

    echo "Completed {$checks} invitation integration checks.\n";
} finally {
    if ($createdWorkspaces) {
        $placeholders = implode(',', array_fill(0, count($createdWorkspaces), '?'));
        $stmt = $db->prepare('DELETE FROM workspaces WHERE id IN (' . $placeholders . ')');
        $stmt->execute($createdWorkspaces);
    }
    if ($createdUsers) {
        $placeholders = implode(',', array_fill(0, count($createdUsers), '?'));
        $stmt = $db->prepare('DELETE FROM users WHERE id IN (' . $placeholders . ')');
        $stmt->execute($createdUsers);
    }
    $_SESSION = $oldSession;
}
