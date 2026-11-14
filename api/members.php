<?php
/**
 * ArsipKu - Workspace members, invitations, and share links API.
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Helpers.php';
require_once __DIR__ . '/../src/Services/AuthService.php';
require_once __DIR__ . '/../src/Services/MemberService.php';
require_once __DIR__ . '/../src/Services/WorkspaceService.php';

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = $_REQUEST['action'] ?? 'list';
    $memberService = new MemberService();

    if ($method === 'GET' && $action === 'link_info') {
        $token = (string) ($_GET['token'] ?? '');
        jsonResponse(['success' => true, 'data' => $memberService->getInviteLinkInfo($token)]);
    }

    $auth = new AuthService();
    if (!$auth->current()) {
        jsonResponse(['success' => false, 'message' => 'Sesi tidak aktif. Silakan login kembali.'], 401);
    }
    if ($method !== 'GET' && !$auth->isSameOriginRequest()) {
        jsonResponse(['success' => false, 'message' => 'Permintaan lintas origin ditolak.'], 403);
    }

    $data = [];
    if ($method !== 'GET') {
        $rawInput = file_get_contents('php://input');
        $decoded = json_decode($rawInput ?: '', true);
        $data = is_array($decoded) ? $decoded : $_POST;
        $action = (string) ($data['action'] ?? $action);
        if (!$auth->verifiesCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($data['_csrf'] ?? null))) {
            jsonResponse(['success' => false, 'message' => 'Token keamanan tidak valid. Muat ulang halaman.'], 419);
        }
    }

    if ($action === 'my_invitations' && $method === 'GET') {
        jsonResponse(['success' => true, 'data' => $memberService->getMyInvitations()]);
    }

    if ($action === 'respond_invitation' && $method === 'POST') {
        $decision = $data['decision'] ?? '';
        if (!in_array($decision, ['accept', 'decline'], true)) {
            jsonResponse(['success' => false, 'message' => 'Pilihan undangan tidak valid.'], 422);
        }
        $result = $memberService->respondToInvitation(
            (string) ($data['id'] ?? ''),
            $decision === 'accept'
        );
        jsonResponse([
            'success' => true,
            'data' => $result,
            'message' => $result['status'] === 'accepted' ? 'Undangan diterima.' : 'Undangan ditolak.',
        ]);
    }

    if ($action === 'join_link' && $method === 'POST') {
        $result = $memberService->joinViaLink((string) ($data['token'] ?? ''));
        jsonResponse([
            'success' => true,
            'data' => $result,
            'message' => $result['status'] === 'pending'
                ? 'Permintaan bergabung dikirim. Menunggu persetujuan Owner/Admin.'
                : 'Anda berhasil bergabung ke workspace.',
        ]);
    }

    $workspaceService = new WorkspaceService();
    $currentWs = $workspaceService->getCurrentWorkspace();
    $workspaceId = (string) $currentWs['id'];

    if ($method === 'GET' && $action === 'list') {
        jsonResponse(['success' => true, 'data' => $memberService->getMembers($workspaceId)]);
    }
    if ($method === 'GET' && $action === 'workspace_invitations') {
        jsonResponse(['success' => true, 'data' => $memberService->getWorkspaceInvitations($workspaceId)]);
    }
    if ($method === 'GET' && $action === 'invite_links') {
        jsonResponse(['success' => true, 'data' => $memberService->getInviteLinks($workspaceId)]);
    }
    if ($method !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'Metode tidak diizinkan.'], 405);
    }

    if ($action === 'invite') {
        $invited = $memberService->inviteByEmail(
            $workspaceId,
            (string) ($data['email'] ?? ''),
            (string) ($data['role'] ?? 'Viewer')
        );
        jsonResponse([
            'success' => true,
            'message' => "Undangan untuk {$invited['email']} dikirim.",
            'data' => $invited,
        ]);
    }
    if ($action === 'create_link') {
        $approvalRequired = $data['approval_required'] ?? true;
        if (!is_bool($approvalRequired) && !in_array($approvalRequired, [0, 1, '0', '1'], true)) {
            jsonResponse(['success' => false, 'message' => 'Pengaturan persetujuan harus bernilai true atau false.'], 422);
        }
        $link = $memberService->createInviteLink(
            $workspaceId,
            (string) ($data['role'] ?? 'Viewer'),
            $approvalRequired === true || $approvalRequired === 1 || $approvalRequired === '1',
            (int) ($data['expires_in_days'] ?? 7),
            isset($data['max_uses']) && $data['max_uses'] !== '' ? (int) $data['max_uses'] : null
        );
        jsonResponse(['success' => true, 'message' => 'Link undangan berhasil dibuat.', 'data' => $link]);
    }
    if ($action === 'revoke_link') {
        $memberService->revokeInviteLink($workspaceId, (string) ($data['id'] ?? ''));
        jsonResponse(['success' => true, 'message' => 'Link undangan dinonaktifkan.']);
    }
    if ($action === 'resolve_invitation') {
        $decision = $data['decision'] ?? '';
        if (!in_array($decision, ['accept', 'reject'], true)) {
            jsonResponse(['success' => false, 'message' => 'Pilihan persetujuan tidak valid.'], 422);
        }
        $result = $memberService->resolveWorkspaceInvitation(
            $workspaceId,
            (string) ($data['id'] ?? ''),
            $decision === 'accept'
        );
        jsonResponse([
            'success' => true,
            'data' => $result,
            'message' => $result['status'] === 'accepted'
                ? 'Permintaan disetujui dan anggota ditambahkan.'
                : 'Permintaan bergabung ditolak.',
        ]);
    }
    if ($action === 'cancel_invitation') {
        $memberService->cancelEmailInvitation($workspaceId, (string) ($data['id'] ?? ''));
        jsonResponse(['success' => true, 'message' => 'Undangan dibatalkan.']);
    }
    if ($action === 'update_role') {
        if (!$memberService->updateRole(
            (string) ($data['id'] ?? ''),
            $workspaceId,
            (string) ($data['role'] ?? '')
        )) {
            jsonResponse(['success' => false, 'message' => 'Anggota tidak ditemukan atau role tidak berubah.'], 404);
        }
        jsonResponse(['success' => true, 'message' => 'Peran anggota berhasil diperbarui.']);
    }
    if ($action === 'remove') {
        if (!$memberService->remove((string) ($data['id'] ?? ''), $workspaceId)) {
            jsonResponse(['success' => false, 'message' => 'Anggota tidak ditemukan atau tidak dapat dihapus.'], 404);
        }
        jsonResponse(['success' => true, 'message' => 'Anggota berhasil dihapus dari workspace.']);
    }
    if ($action === 'leave') {
        $memberService->leaveWorkspace($workspaceId);
        $nextWorkspace = $workspaceService->getCurrentWorkspace();
        jsonResponse([
            'success' => true,
            'message' => 'Anda berhasil keluar dari workspace.',
            'data' => ['workspace_slug' => $nextWorkspace['slug']],
        ]);
    }

    jsonResponse(['success' => false, 'message' => 'Aksi anggota tidak valid.'], 400);
} catch (MemberAuthorizationException $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 403);
} catch (MemberConflictException $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 409);
} catch (InvalidArgumentException $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
} catch (Throwable $e) {
    error_log('Members API failure: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Permintaan anggota tidak dapat diproses saat ini.'], 500);
}
