<?php
/**
 * Focused tests for account profile and password changes.
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$outputBufferLevel = ob_get_level();
ob_start();

$rootDir = dirname(__DIR__);
require_once $rootDir . '/src/Config.php';
require_once $rootDir . '/src/Database.php';
require_once $rootDir . '/src/Services/AuthService.php';
require_once $rootDir . '/src/Services/WorkspaceService.php';

$db = Database::getConnection();
$oldSession = $_SESSION;
$createdUserId = null;
$createdWorkspaceIds = [];
$checks = 0;

$assert = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
    $checks++;
    echo '[PASS] ' . $message . "\n";
};

$expectInvalid = static function (callable $call, string $message) use ($assert): void {
    try {
        $call();
    } catch (InvalidArgumentException $e) {
        $assert(true, $message);
        return;
    }
    $assert(false, $message);
};

try {
    $auth = new AuthService();
    $email = 'profile-' . bin2hex(random_bytes(5)) . '@example.com';
    $session = $auth->register('Profile Test', $email, 'initial-password-123', 'initial-password-123');
    $createdUserId = $session['user']['id'];
    foreach ($session['workspaces'] as $workspace) {
        $createdWorkspaceIds[] = $workspace['id'];
    }

    $avatarPath = 'avatar_' . str_repeat('a', 32) . '.png';
    $profileResult = $auth->updateProfile('Updated Profile Name', $avatarPath);
    $assert(
        $profileResult['user']['name'] === 'Updated Profile Name'
            && $profileResult['user']['avatar'] === 'uploads/' . $avatarPath,
        'profile name and avatar are updated'
    );

    $stmt = $db->prepare('SELECT name, avatar FROM members WHERE LOWER(email) = LOWER(?)');
    $stmt->execute([$email]);
    $member = $stmt->fetch();
    $assert(
        $member && $member['name'] === 'Updated Profile Name' && $member['avatar'] === 'uploads/' . $avatarPath,
        'profile changes are reflected in workspace membership'
    );

    $expectInvalid(
        static function () use ($auth): void {
            $auth->changePassword('wrong-password', 'new-password-123', 'new-password-123');
        },
        'incorrect current password is rejected'
    );
    $expectInvalid(
        static function () use ($auth): void {
            $auth->changePassword('initial-password-123', 'new-password-123', 'different-password');
        },
        'password confirmation mismatch is rejected'
    );
    $auth->changePassword('initial-password-123', 'new-password-123', 'new-password-123');
    $assert($auth->login($email, 'new-password-123'), 'new password can be used to log in');

    $workspaceService = new WorkspaceService();
    $currentWorkspace = $workspaceService->getCurrentWorkspace();
    $logoPath = 'workspace_' . str_repeat('b', 32) . '.webp';
    $workspaceService->update($currentWorkspace['id'], [
        'name' => $currentWorkspace['name'],
        'type' => $currentWorkspace['type'],
        'logo_path' => $logoPath,
    ]);
    $assert(
        $workspaceService->getById($currentWorkspace['id'])['logo_path'] === $logoPath,
        'workspace logo path can be saved by its owner'
    );

    echo "Completed {$checks} account profile checks.\n";
} finally {
    if ($createdWorkspaceIds) {
        $placeholders = implode(',', array_fill(0, count($createdWorkspaceIds), '?'));
        $stmt = $db->prepare('DELETE FROM workspaces WHERE id IN (' . $placeholders . ')');
        $stmt->execute($createdWorkspaceIds);
    }
    if ($createdUserId !== null) {
        $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$createdUserId]);
    }
    $_SESSION = $oldSession;
    if (ob_get_level() > $outputBufferLevel) {
        echo ob_get_clean();
    }
}
