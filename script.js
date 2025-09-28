let currentProjectId = null;
let currentPlatform = null;
let searchPage = 1; // 検索ページ
let searchTotalPages = 0;
let versions = []; // 全バージョン保持 (最大100件)
let currentSearchQuery = '';
let currentProjectType = '';
let isFiltering = false; // フィルタリング中フラグ

// 検索実行
function searchProjects(page = 1) {
    const platform = document.getElementById('platform').value;
    const projectType = document.getElementById('projectType').value;
    const query = document.getElementById('searchQuery').value.trim();
    if (!query) {
        alert('検索キーワードを入力してください');
        return;
    }
    
    currentPlatform = platform;
    currentProjectType = projectType;
    currentSearchQuery = query;
    searchPage = page;
    const resultsDiv = document.getElementById('results');
    const projectDetail = document.getElementById('projectDetail');
    resultsDiv.innerHTML = '<p>検索中...</p>';
    
    // 詳細ページを非表示にし、検索結果を表示
    projectDetail.style.display = 'none';
    resultsDiv.style.display = 'block';
    
    fetch(`api.php?action=search&platform=${platform}&project_type=${projectType}&query=${encodeURIComponent(query)}&page=${page}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                resultsDiv.innerHTML = `<p>エラー: ${data.error}</p>`;
                return;
            }
            const projects = data.projects || data;
            const total = data.total || 0;
            displayResults(projects, total);
            renderSearchPagination(total);
            resultsDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
        })
        .catch(error => {
            console.error('Fetch error:', error);
            resultsDiv.innerHTML = `<p>エラーが発生しました: ${error.message}</p>`;
        });
}

// 検索結果表示
function displayResults(projects, total) {
    const resultsDiv = document.getElementById('results');
    if (!projects || projects.length === 0) {
        resultsDiv.innerHTML = '<p>結果が見つかりませんでした。</p>';
        return;
    }
    
    let html = `<h2>検索結果 (${total}件)</h2>`;
    projects.forEach(project => {
        html += `
            <div class="project-item" onclick="showDetail('${project.id}')">
                <img src="${project.icon || ''}" alt="アイコン" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNTAiIGhlaWdodD0iNTAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjUwIiBoZWlnaHQ9IjUwIiBmaWxsPSIjY2NjIi8+PC9zdmc+';"/>
                <h3>${project.title}</h3>
                <p>${project.description ? project.description.substring(0, 100) + '...' : ''}</p>
                <p><strong>作者:</strong> ${project.author || '不明'}</p>
            </div>
        `;
    });
    resultsDiv.innerHTML = html;
}

// 検索ページネーション描画
function renderSearchPagination(total) {
    const pagination = document.getElementById('searchPagination');
    const pageSize = 10;
    searchTotalPages = Math.ceil(total / pageSize);
    if (searchTotalPages <= 1) {
        pagination.style.display = 'none';
        return;
    }
    pagination.style.display = 'flex';
    let html = '';
    if (searchPage > 1) html += `<button onclick="searchProjects(${searchPage - 1})">前</button>`;
    for (let i = 1; i <= searchTotalPages; i++) {
        html += `<button class="${i === searchPage ? 'active' : ''}" onclick="searchProjects(${i})">${i}</button>`;
    }
    if (searchPage < searchTotalPages) html += `<button onclick="searchProjects(${searchPage + 1})">次</button>`;
    pagination.innerHTML = html;
}

// プロジェクト詳細表示
function showDetail(projectId) {
    currentProjectId = projectId;
    document.getElementById('results').style.display = 'none';
    document.getElementById('searchPagination').style.display = 'none';
    const projectDetail = document.getElementById('projectDetail');
    projectDetail.style.display = 'block';
    
    // フィルタリング中フラグをリセット
    isFiltering = false;
    // チェックボックスをリセット（全選択）
    document.getElementById('filterRelease').checked = true;
    document.getElementById('filterBeta').checked = true;
    document.getElementById('filterAlpha').checked = true;
    
    fetch(`api.php?action=project&platform=${currentPlatform}&id=${projectId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                alert(`エラー: ${data.error}`);
                return;
            }
            document.getElementById('projectTitle').textContent = data.title;
            document.getElementById('projectIcon').src = data.icon || 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgZmlsbD0iI2NjYyIvPjwvc3ZnPg==';
            document.getElementById('projectDescription').textContent = data.description;
            const authorText = Array.isArray(data.author) ? (data.author[0] || '不明') : (data.author || '不明');
            document.getElementById('projectAuthor').textContent = authorText;
            
            versions = data.versions || []; // 全バージョンを保持（最大100件）
            displayVersions(versions);
            // バージョン一覧ページネーションは非表示
            document.getElementById('versionPagination').style.display = 'none';
            projectDetail.scrollIntoView({ behavior: 'smooth', block: 'start' });
        })
        .catch(error => {
            console.error('Fetch error:', error);
            alert(`エラーが発生しました: ${error.message}`);
        });
}

// バージョン表示
function displayVersions(pageVersions) {
    const versionList = document.getElementById('versionList');
    let html = '';
    pageVersions.forEach(version => {
        html += `
            <div class="version-item">
                <span class="version-type">${version.versionType}</span>
                <span class="version-number">${version.versionNumber || '不明'}</span>
                <span class="file-name">${version.fileName || '不明'}</span>
                <span class="game-versions">${Array.isArray(version.gameVersions) ? version.gameVersions.join(', ') : (version.gameVersions || '不明')}</span>
                <a href="${version.downloadUrl || '#'}" ${version.downloadUrl ? 'download' : 'style="pointer-events: none; opacity: 0.5;"'}>ダウンロード</a>
            </div>
        `;
    });
    versionList.innerHTML = html || '<p>該当するバージョンがありません。</p>';
}

// バージョンリストのフィルタリング
function filterVersions() {
    const showRelease = document.getElementById('filterRelease').checked;
    const showBeta = document.getElementById('filterBeta').checked;
    const showAlpha = document.getElementById('filterAlpha').checked;
    
    // 少なくとも1つチェックされているか確認
    if (!showRelease && !showBeta && !showAlpha) {
        alert('少なくとも1つのバージョンを選択してください。');
        document.getElementById('filterRelease').checked = true;
    }
    
    // フィルタリング実行
    isFiltering = true;
    const filteredVersions = versions.filter(version => {
        return (version.versionType === 'Release' && showRelease) ||
               (version.versionType === 'Beta' && showBeta) ||
               (version.versionType === 'Alpha' && showAlpha);
    });
    
    displayVersions(filteredVersions);
    // フィルタ中もページネーションは非表示
    document.getElementById('versionPagination').style.display = 'none';
    if (filteredVersions.length === 0) {
        document.getElementById('versionList').innerHTML = '<p>該当するバージョンがありません。</p>';
    }
}

// 詳細を隠す
function hideDetail() {
    document.getElementById('projectDetail').style.display = 'none';
    document.getElementById('results').style.display = 'block';
    // 検索ページネーションを再表示
    document.getElementById('searchPagination').style.display = searchTotalPages > 1 ? 'flex' : 'none';
    document.getElementById('results').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// ページロード時にフォーカス
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('searchQuery').focus();
});
