<?php
/**
 * Focused integration tests for workspace role authorization.
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
require_once $rootDir . '/src/Services/FolderService.php';
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

$expectAuthorizationFailure = static function (callable $operation, string $message) use ($assert): void {
    try {
        $operation();
    } catch (DocumentAuthorizationException | FolderAuthorizationException | WorkspaceAuthorizationException $e) {
        $assert(true, $message);
        return;
    }
    $assert(false, $message);
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

$renderPage = static function (string $page) use ($rootDir): string {
    $_GET = ['page' => $page];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/index.php?' . http_build_query($_GET);
    ob_start();
    require $rootDir . '/index.php';
    return (string) ob_get_clean();
};

$inviteAndJoin = static function (
    array $owner,
    string $workspaceId,
    string $workspaceSlug,
    array $member,
    string $role
) use ($switchUser): void {
    $switchUser($owner, $workspaceSlug);
    $invitation = (new MemberService())->inviteByEmail($workspaceId, $member['email'], $role);
    $switchUser($member, $workspaceSlug);
    (new MemberService())->respondToInvitation($invitation['id'], true);
};

try {
    $owner = $createAccount('Role owner');
    $ownerWorkspace = (new AuthService())->sessionData()['workspaces'][0];
    $createdWorkspaces[] = $ownerWorkspace['id'];
    $admin = $createAccount('Role admin');
    $editor = $createAccount('Role editor');
    $viewer = $createAccount('Role viewer');

    $inviteAndJoin($owner, $ownerWorkspace['id'], $ownerWorkspace['slug'], $admin, 'Admin');
    $inviteAndJoin($owner, $ownerWorkspace['id'], $ownerWorkspace['slug'], $editor, 'Editor');
    $inviteAndJoin($owner, $ownerWorkspace['id'], $ownerWorkspace['slug'], $viewer, 'Viewer');

    $switchUser($editor, $ownerWorkspace['slug']);
    $documents = new DocumentService();
    $folders = new FolderService();
    $documents->assertCanUpload($ownerWorkspace['id']);
    $expectAuthorizationFailure(
        static function () use ($ownerWorkspace): void {
            (new WorkspaceService())->changePlan($ownerWorkspace['id'], 'Unauthorized editor plan', 2048);
        },
        'Editor cannot change workspace plan'
    );
    $editorFolder = $folders->create($ownerWorkspace['id'], 'Editor role folder');
    $assert(!empty($editorFolder['id']), 'Editor can create folders');

    $editorDocument = $documents->create([
        'workspace_id' => $ownerWorkspace['id'],
        'folder_id' => null,
        'name' => 'editor-role-test.txt',
        'original_name' => 'editor-role-test.txt',
        'mime_type' => 'text/plain',
        'extension' => 'txt',
        'size_bytes' => 0,
        'storage_path' => 'sample_role_editor.txt',
    ]);
    $documents->rename($editorDocument['id'], $ownerWorkspace['id'], 'editor-renamed.txt');
    $assert($documents->getById($editorDocument['id'], $ownerWorkspace['id'])['name'] === 'editor-renamed.txt', 'Editor can rename documents');
    $folders->rename($editorFolder['id'], $ownerWorkspace['id'], 'Editor renamed folder');
    $folders->delete($editorFolder['id'], $ownerWorkspace['id']);
    $assert(true, 'Editor can rename and delete folders');

    $switchUser($owner, $ownerWorkspace['slug']);
    $ownerDocument = (new DocumentService())->create([
        'workspace_id' => $ownerWorkspace['id'],
        'folder_id' => null,
        'name' => 'owner-role-test.txt',
        'original_name' => 'owner-role-test.txt',
        'mime_type' => 'text/plain',
        'extension' => 'txt',
        'size_bytes' => 0,
        'storage_path' => 'sample_role_owner.txt',
    ]);
    (new DocumentService())->saveSharing($ownerDocument['id'], $ownerWorkspace['id'], 'selected', [$viewer['id']], 'edit');

    $switchUser($admin, $ownerWorkspace['slug']);
    $adminDocuments = new DocumentService();
    $adminFolders = new FolderService();
    $adminDocuments->assertCanUpload($ownerWorkspace['id']);
    $adminFolder = $adminFolders->create($ownerWorkspace['id'], 'Admin role folder');
    $adminDocuments->rename($ownerDocument['id'], $ownerWorkspace['id'], 'admin-managed.txt');
    $adminFolders->delete($adminFolder['id'], $ownerWorkspace['id']);
    $assert(
        $adminDocuments->getById($ownerDocument['id'], $ownerWorkspace['id'])['name'] === 'admin-managed.txt',
        "Admin can manage another member's restricted document and folders"
    );
    $expectAuthorizationFailure(
        static function () use ($ownerWorkspace): void {
            (new WorkspaceService())->changePlan($ownerWorkspace['id'], 'Unauthorized admin plan', 2048);
        },
        'Admin cannot change workspace plan'
    );

    $switchUser($owner, $ownerWorkspace['slug']);
    $assert((new WorkspaceService())->changePlan($ownerWorkspace['id'], 'Role test plan', 1024), 'Owner can change workspace plan');
    $switchUser($viewer, $ownerWorkspace['slug']);
    $expectAuthorizationFailure(
        static function () use ($ownerWorkspace): void {
            (new WorkspaceService())->changePlan($ownerWorkspace['id'], 'Unauthorized plan', 2048);
        },
        'Viewer cannot change workspace plan'
    );

    $viewerDocuments = new DocumentService();
    $viewerFolders = new FolderService();
    $expectAuthorizationFailure(
        static function () use ($ownerWorkspace, $viewerDocuments): void {
            $viewerDocuments->assertCanUpload($ownerWorkspace['id']);
        },
        'Viewer cannot upload documents'
    );
    $expectAuthorizationFailure(
        static function () use ($ownerWorkspace, $viewerFolders): void {
            $viewerFolders->create($ownerWorkspace['id'], 'Viewer forbidden folder');
        },
        'Viewer cannot create folders'
    );
    $assert(
        $viewerDocuments->getById($ownerDocument['id'], $ownerWorkspace['id'])['can_edit'] === true,
        'Viewer with an explicit edit grant can edit a restricted document'
    );
    $viewerDocuments->rename($ownerDocument['id'], $ownerWorkspace['id'], 'viewer-granted-edit.txt');
    $expectAuthorizationFailure(
        static function () use ($ownerWorkspace, $ownerDocument, $viewerDocuments): void {
            $viewerDocuments->softDelete($ownerDocument['id'], $ownerWorkspace['id']);
        },
        "Viewer edit grant does not allow deleting another member's document"
    );
    $expectAuthorizationFailure(
        static function () use ($ownerWorkspace, $viewerDocuments): void {
            $viewerDocuments->emptyTrash($ownerWorkspace['id']);
        },
        'Viewer cannot empty the workspace trash'
    );
    $viewerDashboard = $renderPage('dashboard');
    $assert(
        strpos($viewerDashboard, 'id="modal-upload"') === false
            && strpos($viewerDashboard, 'id="modal-create-folder"') === false
            && strpos($viewerDashboard, "openModal('modal-upload')") === false
            && strpos($viewerDashboard, "openModal('modal-create-folder')") === false,
        'Viewer dashboard hides upload and folder creation controls'
    );
    $viewerBilling = $renderPage('billing');
    $assert(
        strpos($viewerBilling, 'Beralih ke paket ini') === false,
        'Viewer billing page hides plan change controls'
    );

    $assert(is_array($viewerFolders->getByWorkspace($ownerWorkspace['id'])), 'Viewer can read workspace folders');
    $switchUser($owner, $ownerWorkspace['slug']);
    $memberRows = (new MemberService())->getMembers($ownerWorkspace['id']);
    foreach ($memberRows as $memberRow) {
        if (strtolower($memberRow['email']) === strtolower($editor['email'])) {
            (new MemberService())->updateRole($memberRow['id'], $ownerWorkspace['id'], 'Viewer');
            break;
        }
    }

    $switchUser($editor, $ownerWorkspace['slug']);
    $downgradedDocuments = new DocumentService();
    $expectAuthorizationFailure(
        static function () use ($ownerWorkspace, $downgradedDocuments): void {
            $downgradedDocuments->assertCanUpload($ownerWorkspace['id']);
        },
        'A downgraded Editor cannot upload new documents'
    );
    $downgradedDocuments->rename($editorDocument['id'], $ownerWorkspace['id'], 'editor-owned-after-downgrade.txt');
    $assert(
        $downgradedDocuments->getById($editorDocument['id'], $ownerWorkspace['id'])['can_edit'] === true,
        'Uploader retains access to its own document after becoming a Viewer'
    );
    echo "Completed {$checks} workspace role authorization checks.\n";
} catch (Throwable $e) {
    if ($e instanceof DocumentAuthorizationException || $e instanceof FolderAuthorizationException || $e instanceof WorkspaceAuthorizationException) {
        fwrite(STDERR, 'Unexpected authorization failure: ' . $e->getMessage() . "\n");
    } else {
        fwrite(STDERR, $e->getMessage() . "\n");
    }
    throw $e;
} finally {
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
}
