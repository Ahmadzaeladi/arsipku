<?php
/**
 * Focused integration tests for recipient-scoped document sharing.
 * Requires the MySQL schema and the document sharing migration.
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rootDir = dirname(__DIR__);
require_once $rootDir . '/src/Config.php';
require_once $rootDir . '/src/Database.php';
require_once $rootDir . '/src/Services/AuthService.php';
require_once $rootDir . '/src/Services/DocumentService.php';
require_once $rootDir . '/src/Services/MemberService.php';

$db = Database::getConnection();
$oldSession = $_SESSION;
$oldGet = $_GET;
$createdUsers = [];
$createdWorkspaces = [];
$createdFile = null;
$checks = 0;

$assert = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
    $checks++;
    echo '[PASS] ' . $message . "\n";
};

$createAccount = static function (string $label) use (&$createdUsers, &$createdWorkspaces): array {
    $email = strtolower(str_replace(' ', '-', $label)) . '-' . bin2hex(random_bytes(5)) . '@example.com';
    $session = (new AuthService())->register($label, $email, 'test-password-123', 'test-password-123');
    $createdUsers[] = $session['user']['id'];
    foreach ($session['workspaces'] as $workspace) {
        $createdWorkspaces[] = $workspace['id'];
    }
    return ['id' => $session['user']['id'], 'email' => $email];
};

$switchUser = static function (array $user, string $workspaceSlug): void {
    $_SESSION['authenticated_user_id'] = $user['id'];
    $_SESSION['active_workspace'] = $workspaceSlug;
    unset($_SESSION['authenticated_user']);
    (new AuthService())->current();
};

$inviteAndJoin = static function (
    array $owner,
    string $workspaceId,
    string $workspaceSlug,
    array $member
) use ($switchUser): void {
    $switchUser($owner, $workspaceSlug);
    $members = new MemberService();
    $invite = $members->inviteByEmail($workspaceId, $member['email'], 'Editor');
    $switchUser($member, $workspaceSlug);
    $members->respondToInvitation($invite['id'], true);
};

try {
    $owner = $createAccount('Share owner');
    $ownerWorkspace = (new AuthService())->sessionData()['workspaces'][0];
    $createdWorkspaces[] = $ownerWorkspace['id'];
    $recipient = $createAccount('Share recipient');
    $otherMember = $createAccount('Other member');
    $inviteAndJoin($owner, $ownerWorkspace['id'], $ownerWorkspace['slug'], $recipient);
    $inviteAndJoin($owner, $ownerWorkspace['id'], $ownerWorkspace['slug'], $otherMember);

    $switchUser($owner, $ownerWorkspace['slug']);
    $storageName = 'share-test-' . bin2hex(random_bytes(8)) . '.txt';
    $createdFile = DOCUMENT_UPLOAD_PATH . DIRECTORY_SEPARATOR . $storageName;
    if (file_put_contents($createdFile, 'private document sharing test') === false) {
        throw new RuntimeException('Unable to create the private test document.');
    }

    $documents = new DocumentService();
    $document = $documents->create([
        'workspace_id' => $ownerWorkspace['id'],
        'folder_id' => null,
        'name' => $storageName,
        'original_name' => $storageName,
        'mime_type' => 'text/plain',
        'extension' => 'txt',
        'size_bytes' => filesize($createdFile),
        'storage_path' => $storageName,
    ]);
    $assert(
        $documents->resolveStoredDocument($storageName) === realpath($createdFile)
            && $documents->resolveStoredDocument('..' . DIRECTORY_SEPARATOR . $storageName) === null,
        'private storage resolver accepts only direct files inside configured upload roots'
    );

    $documents->saveSharing($document['id'], $ownerWorkspace['id'], 'selected', [$recipient['id']], 'view');
    $switchUser($recipient, $ownerWorkspace['slug']);
    $recipientDoc = $documents->getById($document['id'], $ownerWorkspace['id']);
    $assert($recipientDoc !== null && $recipientDoc['recipient_permission'] === 'view', 'selected recipient can view the shared document');
    $assert(count($documents->getSharedWithMe($ownerWorkspace['id'])) === 1, 'shared-with-me lists explicit recipient grants');
    $assert($recipientDoc['can_edit'] === false && $recipientDoc['can_delete'] === false, 'view permission does not expose edit or delete capabilities');
    try {
        $documents->rename($document['id'], $ownerWorkspace['id'], 'should-not-rename.txt');
        $assert(false, 'view permission cannot rename a restricted document');
    } catch (DocumentAuthorizationException $e) {
        $assert(true, 'view permission cannot rename a restricted document');
    }

    $switchUser($otherMember, $ownerWorkspace['slug']);
    $assert($documents->getById($document['id'], $ownerWorkspace['id']) === null, 'non-recipient cannot open a restricted document');
    $assert(count($documents->getSharedWithMe($ownerWorkspace['id'])) === 0, 'non-recipient does not see the document in shared-with-me');

    $switchUser($owner, $ownerWorkspace['slug']);
    $documents->saveSharing($document['id'], $ownerWorkspace['id'], 'selected', [$recipient['id']], 'edit');
    $switchUser($recipient, $ownerWorkspace['slug']);
    $assert($documents->getById($document['id'], $ownerWorkspace['id'])['can_edit'] === true, 'edit permission exposes rename and move capability');
    $documents->rename($document['id'], $ownerWorkspace['id'], 'renamed-share-test.txt');

    $switchUser($owner, $ownerWorkspace['slug']);
    $documents->saveSharing($document['id'], $ownerWorkspace['id'], 'workspace', [], 'view');
    $switchUser($otherMember, $ownerWorkspace['slug']);
    $assert($documents->getById($document['id'], $ownerWorkspace['id']) !== null, 'workspace-wide scope restores access to every active member');
    $assert(count($documents->getSharedWithMe($ownerWorkspace['id'])) === 0, 'workspace-wide visibility is not reported as a personal grant');

    $switchUser($owner, $ownerWorkspace['slug']);
    $documents->saveSharing(
        $document['id'],
        $ownerWorkspace['id'],
        'selected',
        [$recipient['id'], $otherMember['id']],
        'view'
    );
    $members = new MemberService();
    $memberRows = $members->getMembers($ownerWorkspace['id']);
    foreach ($memberRows as $row) {
        if (strtolower($row['email']) === strtolower($otherMember['email'])) {
            $members->remove($row['id'], $ownerWorkspace['id']);
            break;
        }
    }
    $stmt = $db->prepare('SELECT COUNT(*) FROM document_shares WHERE workspace_id = ? AND shared_with_user_id = ?');
    $stmt->execute([$ownerWorkspace['id'], $otherMember['id']]);
    $assert((int) $stmt->fetchColumn() === 0, 'removing a member revokes their document grants');

    echo "Completed {$checks} document sharing checks.\n";
} finally {
    if ($createdFile !== null && is_file($createdFile)) {
        unlink($createdFile);
    }
    if ($createdWorkspaces) {
        $placeholders = implode(',', array_fill(0, count($createdWorkspaces), '?'));
        $stmt = $db->prepare("DELETE FROM workspaces WHERE id IN ({$placeholders})");
        $stmt->execute($createdWorkspaces);
    }
    if ($createdUsers) {
        $placeholders = implode(',', array_fill(0, count($createdUsers), '?'));
        $stmt = $db->prepare("DELETE FROM users WHERE id IN ({$placeholders})");
        $stmt->execute($createdUsers);
    }
    $_SESSION = $oldSession;
    $_GET = $oldGet;
}
