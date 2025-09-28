<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$action = $_GET['action'] ?? '';
$platform = $_GET['platform'] ?? '';
$projectType = $_GET['project_type'] ?? 'mod';
$query = $_GET['query'] ?? '';
$id = $_GET['id'] ?? '';
$page = (int)($_GET['page'] ?? 1);

$apiKey = 'YOUR_CURSEFORGE_API_KEY_HERE'; // CurseForge用 (Spigetは不要)

function fetchApi($url, $headers = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode >= 400) {
        return ['error' => 'APIリクエストが失敗しました (HTTP ' . $httpCode . ')'];
    }
    return json_decode($response, true);
}

if ($action === 'search') {
    if ($platform === 'modrinth') {
        $offset = ($page - 1) * 10;
        $url = "https://api.modrinth.com/v2/search?query=" . urlencode($query) . "&limit=10&offset=" . $offset . '&facets=' . urlencode('[["project_type:' . $projectType . '"]]');
        $data = fetchApi($url);
        if (isset($data['error'])) {
            echo json_encode(['error' => $data['error']]);
            exit;
        }
        $projects = array_map(function($p) {
            return [
                'id' => $p['project_id'],
                'title' => $p['title'],
                'description' => $p['description'],
                'author' => $p['author'],
                'icon' => $p['icon_url'] ?? ''
            ];
        }, $data['hits'] ?? []);
        echo json_encode(['projects' => $projects, 'total' => $data['total_hits'] ?? 0]);
    } elseif ($platform === 'curseforge') {
        if (empty($apiKey) || $apiKey === 'YOUR_CURSEFORGE_API_KEY_HERE') {
            echo json_encode(['error' => 'CurseForge APIキーが設定されていません']);
            exit;
        }
        $classId = match($projectType) {
            'plugin' => 5,
            'resourcepack' => 12,
            default => 6
        };
        $url = "https://api.curseforge.com/v1/mods/search?gameId=432&searchFilter=" . urlencode($query) . "&pageSize=10&page=$page&classId=$classId";
        $data = fetchApi($url, ['x-api-key: ' . $apiKey]);
        if (isset($data['error'])) {
            echo json_encode(['error' => $data['error']]);
            exit;
        }
        $projects = array_map(function($p) {
            return [
                'id' => $p['id'],
                'title' => $p['name'],
                'description' => $p['summary'],
                'author' => $p['authors'][0]['name'] ?? '不明',
                'icon' => $p['logo']['url'] ?? ''
            ];
        }, $data['data'] ?? []);
        echo json_encode(['projects' => $projects, 'total' => $data['pagination']['totalCount'] ?? 0]);
    } elseif ($platform === 'spiget') {
        $offset = ($page - 1) * 10;
        $url = "https://api.spiget.org/v2/search/resources/" . urlencode($query) . "?size=10&page=$page&fields=name,tag,author,icon.data";
        $data = fetchApi($url);
        if (isset($data['error']) || !is_array($data)) {
            echo json_encode(['error' => 'Spiget APIエラー: ' . ($data['error'] ?? '無効なレスポンス')]);
            exit;
        }
        $projects = array_map(function($p) {
            return [
                'id' => $p['id'],
                'title' => $p['name'],
                'description' => $p['tag'],
                'author' => $p['author']['id'] ?? '1',
                'icon' => "data:image/png;base64," . $p['icon']['data']
            ];
        }, $data ?? []);
        echo json_encode(['projects' => $projects, 'total' => count($data)]); // Spigetは総数非対応、簡易的に
    } else {
        echo json_encode(['error' => '無効なプラットフォーム']);
        exit;
    }
} elseif ($action === 'project') {
    if ($platform === 'modrinth') {
        $projectUrl = "https://api.modrinth.com/v2/project/$id";
        $projectData = fetchApi($projectUrl);
        if (isset($projectData['error'])) {
            echo json_encode(['error' => $projectData['error']]);
            exit;
        }
        $versionsUrl = "https://api.modrinth.com/v2/project/$id/version?limit=100";
        $versionsData = fetchApi($versionsUrl);
        if (isset($versionsData['error'])) {
            echo json_encode(['error' => $versionsData['error']]);
            exit;
        }
        
        $versions = array_map(function($v) {
            return [
                'name' => $v['name'],
                'versionNumber' => $v['version_number'],
                'versionType' => ucfirst($v['version_type']),
                'fileName' => $v['files'][0]['filename'] ?? '不明',
                'gameVersions' => $v['game_versions'] ?? [],
                'date' => date('Y-m-d', strtotime($v['date_published'])),
                'downloadUrl' => $v['files'][0]['url'] ?? ''
            ];
        }, $versionsData ?? []);
        
        echo json_encode([
            'title' => $projectData['title'],
            'description' => $projectData['description'],
            'author' => $projectData['author']['user'] ?? $projectData['author'] ?? '不明',
            'icon' => $projectData['icon_url'] ?? '',
            'versions' => $versions
        ]);
    versions
        ]);
    } elseif ($platform === 'curseforge') {
        if (empty($apiKey) || $apiKey === 'YOUR_CURSEFORGE_API_KEY_HERE') {
            echo json_encode(['error' => 'CurseForge APIキーが設定されていません']);
            exit;
        }
        $projectUrl = "https://api.curseforge.com/v1/mods/$id";
        $projectData = fetchApi($projectUrl, ['x-api-key: ' . $apiKey]);
        if (isset($projectData['error'])) {
            echo json_encode(['error' => $projectData['error']]);
            exit;
        }
        $proj = $projectData['data'];
        
        $filesUrl = "https://api.curseforge.com/v1/mods/$id/files?pageSize=100";
        $filesData = fetchApi($filesUrl, ['x-api-key: ' . $apiKey]);
        if (isset($filesData['error'])) {
            echo json_encode(['error' => $filesData['error']]);
            exit;
        }
        
        $versions = array_map(function($f) {
            return [
                'name' => $f['displayName'],
                'versionNumber' => $f['fileName'],
                'versionType' => ucfirst($f['releaseType'] == 1 ? 'release' : ($f['releaseType'] == 2 ? 'beta' : 'alpha')),
                'fileName' => $f['fileName'] ?? '不明',
                'gameVersions' => $f['gameVersions'] ?? [],
                'date' => date('Y-m-d', strtotime($f['fileDate'])),
                'downloadUrl' => $f['downloadUrl'] ?? ''
            ];
        }, $filesData['data'] ?? []);
        
        echo json_encode([
            'title' => $proj['name'],
            'description' => $proj['summary'],
            'author' => $proj['authors'][0]['name'] ?? '不明',
            'icon' => $proj['logo']['url'] ?? '',
            'versions' => $versions
        ]);
    } elseif ($platform === 'spiget') {
        $projectUrl = "https://api.spiget.org/v2/resources/$id";
        $projectData = fetchApi($projectUrl);
        if (isset($projectData['error']) || !is_array($projectData)) {
            echo json_encode(['error' => 'Spiget APIエラー: ' . ($projectData['error'] ?? '無効なレスポンス')]);
            exit;
        }
        $versionsData = $projectData['versions'] ?? [];
        
        $versions = array_map(function($v) {
            return [
                'name' => $v['name'],
                'versionNumber' => $v['version'],
                'versionType' => 'Release', // Spigetはタイプ区別なし、Releaseとして扱う
                'fileName' => $v['file']['name'] ?? '不明',
                'gameVersions' => $v['file']['requiredPermissions'] ?? [], // 対応バージョンはpermissionsから推定
                'date' => date('Y-m-d', strtotime($v['date'] ?? 'now')),
                'downloadUrl' => $v['file']['url'] ?? ''
            ];
        }, $versionsData ?? []);
        
        echo json_encode([
            'title' => $projectData['name'],
            'description' => $projectData['tag'],
            'author' => $projectData['author']['id'] ?? '不明',
            'icon' => "data:image/png;base64," . $projectData['icon']['data'],
            'versions' => $versions
        ]);
    } else {
        echo json_encode(['error' => '無効なプラットフォーム']);
        exit;
    }
} else {
    echo json_encode(['error' => '無効なアクション']);
}
?>
