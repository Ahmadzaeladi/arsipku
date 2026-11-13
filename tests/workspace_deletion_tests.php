<?php
/**
 * Focused integration tests for owner-only workspace edits and deletion rules.
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
require_once $rootDir . '/src/Services/WorkspaceService.php';

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
    $email = strtolower($label) . '-' . bin2hex(random_bytes(5)) . '@example.com';
    $session = (new AuthService())->register($label, $email, 'test-password-123', 'test-password-123');
    $createdUsers[] = $session['user']['id'];
    foreach ($session['workspaces'] as $workspace) {
        $createdWorkspaces[] = $workspace['id'];
    }
    return ['id' => $session['user']['id'], 'email' => $email];
};

$switchUser = static function (array $user): void {
    $_SESSION['authenticated_user_id'] = $user['id'];
    unset($_SESSION['authenticated_user']);
    (new AuthService())->current();
};

$expectException = static function (string $class, callable $call, string $message) use ($assert): void {
    try {
        $call();
    } catch (Throwable $e) {
        $assert($e instanceof $class, $message);
        return;
    }
    $assert(false, $message);
};

try {
    $owner = $createAccount('workspace-owner');
    $ownerWorkspace = (new AuthService())->sessionData()['workspaces'][0]['id'];
    $member = $createAccount('workspace-member');

    $switchUser($owner);
    $workspaces = new WorkspaceService();
    $secondary = $workspaces->create('Temporary collaboration workspace');
    $createdWorkspaces[] = $secondary['id'];
    $members = new MemberService();
    $invitation = $members->inviteByEmail($secondary['id'], $member['email'], 'Viewer');
    $switchUser($member);
    $members->respondToInvitation($invitation['id'], true);

    $expectException(
        WorkspaceAuthorizationException::class,
        static function () use ($workspaces, $secondary): void {
            $workspaces->update($secondary['id'], ['name' => 'Member change', 'type' => 'school']);
        },
        'non-owner member cannot update workspace details'
    );

    $switchUser($owner);
    $assert(
        $workspaces->update($secondary['id'], ['name' => 'Updated workspace', 'type' => 'school'])
            && $workspaces->getById($secondary['id'])['name'] === 'Updated workspace',
        'owner can update workspace name and category'
    );

    $switchUser($member);
    $expectException(
        WorkspaceAuthorizationException::class,
        static function () use ($workspaces, $secondary): void {
            $workspaces->delete($secondary['id']);
        },
        'non-owner member cannot delete a workspace'
    );

    $switchUser($owner);
    $assert($workspaces->delete($secondary['id']), 'owner can delete workspace when another workspace remains');
    $expectException(
        WorkspaceConflictException::class,
        static function () use ($workspaces, $ownerWorkspace): void {
            $workspaces->delete($ownerWorkspace);
        },
        'owner cannot delete their last workspace'
    );

    echo "Completed {$checks} workspace deletion checks.\n";
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
