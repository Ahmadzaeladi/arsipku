<?php
/**
 * ArsipKu - SaaS Digital Archive Management System
 * Main Entry Point & Native Router
 * PHP Native, No Framework
 */

declare(strict_types=1);

require_once __DIR__ . '/src/Config.php';
require_once __DIR__ . '/src/Helpers.php';
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Services/AuthService.php';

$page = $_GET['page'] ?? 'dashboard';
$authService = new AuthService();

if ($page === 'logout') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Metode tidak diizinkan.');
    }
    if (!$authService->isSameOriginRequest()
        || !$authService->verifiesCsrf($_POST['_csrf'] ?? null)) {
        http_response_code(403);
        exit('Permintaan tidak valid.');
    }
    $authService->logout();
    header('Location: index.php?page=login', true, 303);
    exit;
}

$authenticatedUser = $authService->current();
if ($page === 'join') {
    require_once __DIR__ . '/src/Services/MemberService.php';
    $csrfToken = $authService->csrfToken();
    $joinToken = (string) ($_GET['token'] ?? '');
    $joinError = null;
    $joinInfo = null;
    if ($joinToken !== '') {
        try {
            $joinInfo = (new MemberService())->getInviteLinkInfo($joinToken);
        } catch (InvalidArgumentException $e) {
            $joinError = $e->getMessage();
        } catch (MemberConflictException $e) {
            $joinError = $e->getMessage();
        } catch (Throwable $e) {
            error_log('Invite link lookup failure: ' . $e->getMessage());
            $joinError = 'Undangan tidak dapat dimuat saat ini.';
        }
    } else {
        $joinError = 'Token link undangan tidak tersedia.';
    }
    require __DIR__ . '/views/pages/join.php';
    exit;
}

if (!$authenticatedUser) {
    $csrfToken = $authService->csrfToken();
    $loginError = null;
    $inviteToken = (string) ($_GET['invite'] ?? $_POST['invite'] ?? '');
    if ($page === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$authService->isSameOriginRequest()
            || !$authService->verifiesCsrf($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            $loginError = 'Permintaan tidak valid. Muat ulang halaman lalu coba kembali.';
        } else {
            try {
                $authService->register(
                    (string) ($_POST['name'] ?? ''),
                    (string) ($_POST['email'] ?? ''),
                    (string) ($_POST['password'] ?? ''),
                    (string) ($_POST['password_confirmation'] ?? '')
                );
                $next = $inviteToken !== ''
                    ? 'index.php?' . http_build_query(['page' => 'join', 'token' => $inviteToken], '', '&', PHP_QUERY_RFC3986)
                    : 'index.php';
                header('Location: ' . $next, true, 303);
                exit;
            } catch (InvalidArgumentException $e) {
                http_response_code(422);
                $loginError = $e->getMessage();
            } catch (RuntimeException $e) {
                http_response_code(409);
                $loginError = $e->getMessage();
            } catch (Throwable $e) {
                error_log('Registration failure: ' . $e->getMessage());
                http_response_code(500);
                $loginError = 'Registrasi tidak dapat diproses saat ini. Coba lagi nanti.';
            }
        }
    }
    if ($page === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$authService->isSameOriginRequest()
            || !$authService->verifiesCsrf($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            $loginError = 'Permintaan tidak valid. Muat ulang halaman lalu coba kembali.';
        } elseif ($authService->login(
            (string) ($_POST['username'] ?? $_POST['email'] ?? ''),
            (string) ($_POST['password'] ?? '')
        )) {
            $next = $inviteToken !== ''
                ? 'index.php?' . http_build_query(['page' => 'join', 'token' => $inviteToken], '', '&', PHP_QUERY_RFC3986)
                : 'index.php';
            header('Location: ' . $next, true, 303);
            exit;
        } else {
            http_response_code(401);
            $loginError = 'Username atau password salah.';
        }
    }
    if ($page === 'register') {
        require __DIR__ . '/views/pages/register.php';
    } else {
        require __DIR__ . '/views/pages/login.php';
    }
    exit;
}

if ($page === 'login' || $page === 'register') {
    $inviteToken = (string) ($_GET['invite'] ?? '');
    $next = $inviteToken !== ''
        ? 'index.php?' . http_build_query(['page' => 'join', 'token' => $inviteToken], '', '&', PHP_QUERY_RFC3986)
        : 'index.php';
    header('Location: ' . $next, true, 303);
    exit;
}

$csrfToken = $authService->csrfToken();

require_once __DIR__ . '/src/Services/WorkspaceService.php';
require_once __DIR__ . '/src/Services/DocumentService.php';
require_once __DIR__ . '/src/Services/FolderService.php';
require_once __DIR__ . '/src/Services/MemberService.php';

// Initialize Services
$workspaceService = new WorkspaceService();
$documentService = new DocumentService();
$folderService = new FolderService();
$memberService = new MemberService();

// Current Workspace Context
$currentWs = $workspaceService->getCurrentWorkspace();
$allWorkspaces = $workspaceService->getAll();
$memberNotifications = $memberService->getMyInvitations();
$currentWorkspaceRole = $memberService->getCurrentRole((string) $currentWs['id']);
$wsStats = $workspaceService->getStats($currentWs['id']);
$flatFolders = $folderService->getAllFlat($currentWs['id']);

switch ($page) {
    case 'dashboard':
        $pageTitle = 'Beranda';
        $recentDocuments = $documentService->getDocuments($currentWs['id'], [
            'limit' => 10,
            'sort' => 'updated_desc'
        ]);
        $quickFolders = array_slice($folderService->getByWorkspace($currentWs['id']), 0, 4);
        $recentActivities = $documentService->getRecentActivities($currentWs['id'], 6);
        $viewFile = __DIR__ . '/views/pages/dashboard.php';
        break;

    case 'documents':
        $pageTitle = 'Semua Dokumen';
        $filters = [
            'q' => $_GET['q'] ?? null,
            'type' => $_GET['type'] ?? null,
            'date_range' => $_GET['date_range'] ?? null,
            'sort' => $_GET['sort'] ?? 'updated_desc',
            'folder_id' => $_GET['folder_id'] ?? null,
        ];
        $documents = $documentService->getDocuments($currentWs['id'], $filters);
        $matchingSearchFolders = !empty($filters['q'])
            ? $folderService->searchByName($currentWs['id'], (string) $filters['q'])
            : [];
        $viewFile = __DIR__ . '/views/pages/documents.php';
        break;

    case 'folders':
        $currentFolderId = $_GET['folder_id'] ?? null;
        if ($currentFolderId === 'root' || empty($currentFolderId)) {
            $currentFolderId = null;
        }

        $currentFolder = $currentFolderId ? $folderService->getById($currentFolderId, $currentWs['id']) : null;
        $breadcrumbs = $currentFolderId ? $folderService->getBreadcrumbs($currentWs['id'], $currentFolderId) : [];
        $folders = $folderService->getByWorkspace($currentWs['id'], $currentFolderId);
        
        $docFilters = ['folder_id' => $currentFolderId ?: 'root'];
        $documents = $documentService->getDocuments($currentWs['id'], $docFilters);

        $pageTitle = $currentFolder ? $currentFolder['name'] : 'Folder Saya';
        $viewFile = __DIR__ . '/views/pages/folders.php';
        break;

    case 'recent':
        $pageTitle = 'Dokumen Terbaru';
        $documents = $documentService->getDocuments($currentWs['id'], [
            'sort' => 'updated_desc',
            'limit' => 25
        ]);
        $viewFile = __DIR__ . '/views/pages/recent.php';
        break;

    case 'favorites':
        $pageTitle = 'Dokumen Favorit';
        $documents = $documentService->getDocuments($currentWs['id'], [
            'is_favorite' => 1,
            'sort' => 'updated_desc'
        ]);
        $viewFile = __DIR__ . '/views/pages/favorites.php';
        break;

    case 'shared':
        $pageTitle = 'Dibagikan kepada Saya';
        $documents = $documentService->getSharedWithMe($currentWs['id']);
        $viewFile = __DIR__ . '/views/pages/shared.php';
        break;

    case 'trash':
        $pageTitle = 'Tempat Sampah';
        $documents = $documentService->getDocuments($currentWs['id'], [
            'is_trash' => 1,
            'sort' => 'updated_desc'
        ]);
        $viewFile = __DIR__ . '/views/pages/trash.php';
        break;

    case 'members':
        $pageTitle = 'Anggota Workspace';
        $members = $memberService->getMembers($currentWs['id']);
        $pendingMemberInvitations = in_array($currentWorkspaceRole, ['Owner', 'Admin'], true)
            ? $memberService->getWorkspaceInvitations($currentWs['id'])
            : [];
        $workspaceInviteLinks = in_array($currentWorkspaceRole, ['Owner', 'Admin'], true)
            ? $memberService->getInviteLinks($currentWs['id'])
            : [];
        $viewFile = __DIR__ . '/views/pages/members.php';
        break;

    case 'settings':
        $pageTitle = 'Pengaturan Workspace';
        $viewFile = __DIR__ . '/views/pages/settings.php';
        break;

    case 'account':
        $pageTitle = 'Pengaturan Akun';
        $viewFile = __DIR__ . '/views/pages/account.php';
        break;

    case 'billing':
        $pageTitle = 'Paket & Penyimpanan';
        $viewFile = __DIR__ . '/views/pages/billing.php';
        break;

    default:
        $pageTitle = 'Halaman Tidak Ditemukan';
        $viewFile = __DIR__ . '/views/pages/dashboard.php';
        break;
}

// Render Master Layout
require __DIR__ . '/views/layouts/app.php';
