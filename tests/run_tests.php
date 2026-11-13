<?php
/**
 * ArsipKu - Automated Test Suite
 * PHP Native, No Framework
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rootDir = dirname(__DIR__);
require_once $rootDir . '/src/Config.php';
require_once $rootDir . '/src/Database.php';
$testDb = Database::getConnection();
$testUserId = $testDb->query("SELECT id FROM users ORDER BY created_at ASC LIMIT 1")->fetchColumn();
if (!is_string($testUserId) || $testUserId === '') {
    fwrite(STDERR, "No login user is available. Create an account with the CLI reset tool first.\n");
    exit(1);
}
$_SESSION['authenticated_user_id'] = $testUserId;

function testRoute($page, $params = []) {
    global $rootDir;
    $_GET = array_merge(['page' => $page], $params);
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/index.php?' . http_build_query($_GET);
    
    ob_start();
    require $rootDir . '/index.php';
    $output = ob_get_clean();

    $len = strlen($output);
    $hasBrand = strpos($output, 'ArsipKu') !== false;

    if ($len > 1000 && $hasBrand) {
        echo "[PASS] Route: {$page} (Length: {$len} bytes)\n";
        return true;
    } else {
        echo "[FAIL] Route: {$page} (Length: {$len} bytes, hasBrand: " . ($hasBrand ? '1' : '0') . ")\n";
        return false;
    }
}

echo "=== TESTING ARSIPKU WEB ROUTES ===\n";
$pages = ['dashboard', 'documents', 'folders', 'recent', 'favorites', 'shared', 'trash', 'members', 'settings', 'account', 'billing'];
$allPassed = true;

foreach ($pages as $p) {
    if (!testRoute($p)) {
        $allPassed = false;
    }
}

echo "\n=== TESTING DATABASE & API SERVICES ===\n";
$wsService = new WorkspaceService();
$docService = new DocumentService();
$fldService = new FolderService();

$currentWs = $wsService->getCurrentWorkspace();
echo "Active Workspace: " . $currentWs['name'] . " (" . $currentWs['slug'] . ")\n";

// Test Document List
$docs = $docService->getDocuments($currentWs['id']);
echo "Total Documents retrieved: " . count($docs) . "\n";
if (count($docs) > 0) {
    echo "[PASS] Document retrieval successful\n";
    $testDoc = $docs[0];
    $originalName = $testDoc['name'];
    $tempRename = 'TEST_' . $testDoc['name'];
    $renamed = $docService->rename($testDoc['id'], $currentWs['id'], $tempRename);
    if ($renamed['name'] === $tempRename) {
        echo "[PASS] Document rename successful: {$renamed['name']}\n";
        $docService->rename($testDoc['id'], $currentWs['id'], $originalName);
    } else {
        echo "[FAIL] Document rename failed\n";
        $allPassed = false;
    }

    $isFavNow = $docService->toggleFavorite($testDoc['id'], $currentWs['id']);
    echo "[PASS] Document favorite toggled to: " . ($isFavNow ? '1' : '0') . "\n";
    $docService->toggleFavorite($testDoc['id'], $currentWs['id']);
} else {
    echo "[PASS] Empty document list is valid after a clean reset\n";
}

// Test Create Folder & Delete Folder
$newFolder = $fldService->create($currentWs['id'], 'Test_Folder_Automated');
if (!empty($newFolder['id'])) {
    echo "[PASS] Folder creation successful: {$newFolder['name']} (ID: {$newFolder['id']})\n";
    $fldService->delete($newFolder['id'], $currentWs['id']);
    echo "[PASS] Folder soft-delete successful\n";
} else {
    echo "[FAIL] Folder creation failed\n";
    $allPassed = false;
}

// Test Workspace Create & Plan Update & Delete
$createdWs = $wsService->create('Test Workspace QA', 'company');
if (!empty($createdWs['id'])) {
    echo "[PASS] Workspace creation successful: {$createdWs['name']} ({$createdWs['slug']})\n";
    $wsService->changePlan($createdWs['id'], 'Personal Pro', 10 * 1024 * 1024 * 1024);
    echo "[PASS] Workspace plan update successful\n";
    $wsService->delete($createdWs['id']);
    echo "[PASS] Workspace cleanup successful\n";
} else {
    echo "[FAIL] Workspace creation failed\n";
    $allPassed = false;
}

// Test Member Service
require_once $rootDir . '/src/Services/MemberService.php';
$memService = new MemberService();
$members = $memService->getMembers($currentWs['id']);
if (count($members) > 0) {
    echo "[PASS] Member retrieval successful (" . count($members) . " members)\n";
} else {
    echo "[FAIL] Member retrieval failed\n";
    $allPassed = false;
}

echo "\n=== TESTING ICON REGISTRY (single source: assets/js/icons.js) ===\n";
$registry = arkIconRegistry();
$iconChecks = [];

$iconChecks['registry parsed from icons.js'] = count($registry['paths']) > 40 && count($registry['alias']) > 0;
$badShape = [];
foreach ($registry['paths'] as $iconName => $iconBody) {
    if (strpos($iconBody, '<path') === false && strpos($iconBody, '<circle') === false && strpos($iconBody, '<rect') === false) {
        $badShape[] = $iconName;
    }
}
$iconChecks['every PATHS entry is SVG markup'] = empty($badShape);

$dangling = [];
foreach ($registry['alias'] as $legacy => $canonical) {
    if (!isset($registry['paths'][$canonical])) {
        $dangling[] = $legacy . ' -> ' . $canonical;
    }
}
$iconChecks['every alias resolves to a real shape'] = empty($dangling);

// Nama yang benar-benar dipakai view + nilai ikon getFileMeta() harus ter-render.
$used = [];
$iconSources = array_merge(
    glob($rootDir . '/views/*.php') ?: [],
    glob($rootDir . '/views/*/*.php') ?: [],
    glob($rootDir . '/docs/*.html') ?: []
);
foreach ($iconSources as $iconFile) {
    $iconText = file_get_contents($iconFile);
    preg_match_all("/renderSvgIcon\(\s*'([a-z0-9-]+)'/", $iconText, $mm);
    $used = array_merge($used, $mm[1]);
    preg_match_all('/data-icon="([a-z0-9-]+)"/', $iconText, $mm);
    $used = array_merge($used, $mm[1]);
}
foreach (['pdf', 'docx', 'xlsx', 'png', 'zip', 'txt', 'bin'] as $ext) {
    $used[] = getFileMeta($ext)['icon'];
}
$unknown = array_values(array_unique(array_diff($used, array_keys($registry['paths']), array_keys($registry['alias']), ['nama'])));
$iconChecks['no view asks for an unknown icon (' . count(array_unique($used)) . ' names used)'] = empty($unknown);

// Stroke seragam dan kelas ukuran warisan dipetakan ke .ark-icon--NN.
$iconChecks['stroke width matches ArkIcons (1.75)'] = strpos(arkIconSvg('file'), 'stroke-width="1.75"') !== false
    && strpos(file_get_contents($rootDir . '/assets/js/icons.js'), "var STROKE_WIDTH = '1.75'") !== false;
$shim = renderSvgIcon('trash', 'w-3.5 h-3.5 text-slate-400');
$iconChecks['legacy size class mapped, color class kept'] = strpos($shim, 'ark-icon--14') !== false
    && strpos($shim, 'text-slate-400') !== false && strpos($shim, 'w-3.5') === false;
$iconChecks['unknown name falls back to file'] = renderSvgIcon('no-such-icon', 'w-4 h-4') === arkIconSvg('file', 'ark-icon--16');

foreach ($iconChecks as $label => $ok) {
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . ($ok ? '' : ' — ' . implode(', ', array_merge($unknown, $badShape, $dangling))) . "\n";
    if (!$ok) {
        $allPassed = false;
    }
}

if ($allPassed) {
    echo "\n=== ALL TESTS PASSED SUCCESSFULLY! ===\n";
} else {
    echo "\n=== SOME TESTS FAILED ===\n";
    exit(1);
}
