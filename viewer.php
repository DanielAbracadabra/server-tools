<?php
// Non-Tree autoindex script.
// This script indexes folder that is in $root_dir.
// By default, it is /data/data/com.termux/apache/files/assets

header('Content-Type: text/html; charset=utf-8');

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$current_host = $_SERVER['HTTP_HOST']; 

$current_script_path = dirname($_SERVER['SCRIPT_NAME']);
$base_url = $protocol . "://" . $current_host . rtrim(str_replace('\\', '/', $current_script_path), '/');
$root_dir = '/data/data/com.termux/files/home/apache/files/assets';

// Blacklist. Put here names of files and folders that you want to hide.
$blacklist = ['secret_folder', 'confidential_data.xml', '.git', '.htaccess', 'tree-viewer.php'];

function get_file_emoji($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (in_array($ext, ['zip', 'rar', 'tar', 'gz', '7z', 'bz2', 'xz'])) return '📦';
    if (in_array($ext, ['apk', 'apks', 'xapk']))     return '🤖';
    if (in_array($ext, ['sh', 'bash', 'py', 'pl', 'php', 'js', 'json', 'xml', 'css']))  return '💻';
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'ico', 'svg']))   return '🖼️';
    if (in_array($ext, ['txt', 'md', 'pdf', 'doc', 'docx', 'xls', 'xlsx']))     return '📝';
    return '📄';
}

$files_structure = [];
if (is_dir($root_dir)) {
    $directory = new RecursiveDirectoryIterator($root_dir, RecursiveDirectoryIterator::SKIP_DOTS);
    $iterator  = new RecursiveIteratorIterator($directory, RecursiveIteratorIterator::SELF_FIRST);

    foreach ($iterator as $path => $object) {
        $name = $object->getFilename();
        if ($name === 'viewer.php' || in_array($name, $blacklist)) continue;

        $path_parts = explode(DIRECTORY_SEPARATOR, realpath($object->getRealPath()));
        if (array_intersect($path_parts, $blacklist)) continue;

        $item_real_path = realpath($object->getRealPath());
        $relative_path = substr($item_real_path, strlen($root_dir));
        $relative_path = ltrim(str_replace('\\', '/', $relative_path), '/');
        
        $parent_dir = dirname($relative_path);
        if ($parent_dir === '.') $parent_dir = 'root';
        $parent_dir = str_replace('\\', '/', $parent_dir);

        $web_link = $base_url . '/' . $relative_path;

        $files_structure[] = [
            'name' => $name,
            'is_dir' => $object->isDir(),
            'parent' => $parent_dir,
            'rel_path' => $relative_path,
            'link' => $web_link,
            'size' => $object->isDir() ? 0 : round($object->getSize() / 1024, 2),
            'emoji' => $object->isDir() ? '📁' : get_file_emoji($name)
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Non-Tree Autoindex</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f5f9fc; color: #333333; padding: 15px; margin: 0; }
        .container { max-width: 1000px; margin: 0 auto; background: #ffffff; padding: 20px; border-radius: 12px; border: 1px solid #d0e1fd; box-shadow: 0 4px 15px rgba(0,0,0,0.04); }
        h1 { color: #0056b3; text-align: center; font-size: 18px; margin-top: 0; margin-bottom: 15px; font-weight: 600; border-bottom: 2px solid #b3d7ff; padding-bottom: 10px; }
        
        .breadcrumb { background: #eef5fc; padding: 10px 15px; border-radius: 8px; border: 1px solid #d6e6f2; margin-bottom: 15px; font-family: monospace; font-size: 13px; display: flex; align-items: center; }
        .bc-item { color: #0076d6; font-weight: bold; cursor: pointer; }
        .bc-item:hover { text-decoration: underline; }
        .bc-separator { margin: 0 8px; color: #b3d7ff; }

        .file-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 12px; margin-top: 10px; }
        .card { background: #ffffff; border: 1px solid #e2eefc; border-radius: 10px; padding: 12px; text-align: center; cursor: pointer; transition: transform 0.15s, background 0.15s, border-color 0.15s; user-select: none; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        .card:hover { background: #eef5fc; border-color: #b3d7ff; transform: translateY(-2px); }
        .card-icon { font-size: 32px; margin-bottom: 8px; display: block; }
        .card-name { font-size: 12px; font-weight: 500; color: #111111; word-break: break-all; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; height: 32px; line-height: 1.3; }
        .card-size { font-size: 10px; color: #777777; margin-top: 4px; display: block; font-family: monospace; }
        
        .back-card { border-style: dashed; border-color: #b3d7ff; background: #fafcfe; }
        .back-card .card-icon { color: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Non-Tree Autoindex</h1>
        <div class="breadcrumb" id="breadcrumb"></div>
        <div class="file-grid" id="file-grid"></div>
    </div>

    <script>
        const fsData = <?php echo json_encode($files_structure); ?>;
        let currentFolder = 'root';

        function renderManager() {
            const grid = document.getElementById('file-grid');
            const bc = document.getElementById('breadcrumb');
            grid.innerHTML = '';
            
            let bcHtml = `<span class="bc-item" onclick="navigate('root')">📂 assets</span>`;
            if (currentFolder !== 'root') {
                const parts = currentFolder.split('/');
                let accumPath = '';
                parts.forEach(part => {
                    accumPath += (accumPath ? '/' : '') + part;
                    const capturePath = accumPath;
                    bcHtml += `<span class="bc-separator">▶</span><span class="bc-item" onclick="navigate('${capturePath}')">${part}</span>`;
                });
            }
            bc.innerHTML = bcHtml;

            if (currentFolder !== 'root') {
                let parentFolder = currentFolder.substring(0, currentFolder.lastIndexOf('/'));
                if (!parentFolder) parentFolder = 'root';
                
                const backCard = document.createElement('div');
                backCard.className = 'card back-card';
                backCard.onclick = () => navigate(parentFolder);
                backCard.innerHTML = `<span class="card-icon">⬅</span><span class="card-name">Назад</span><span class="card-size">..</span>`;
                grid.appendChild(backCard);
            }

            const currentItems = fsData.filter(item => item.parent === currentFolder);

            currentItems.forEach(item => {
                const card = document.createElement('div');
                card.className = 'card';
                
                if (item.is_dir) {
                    card.onclick = () => navigate(item.rel_path);
                    card.innerHTML = `<span class="card-icon">${item.emoji}</span><span class="card-name">${item.name}</span><span class="card-size">Папка</span>`;
                } else {
                    card.onclick = () => window.open(item.link, '_blank');
                    card.innerHTML = `<span class="card-icon">${item.emoji}</span><span class="card-name">${item.name}</span><span class="card-size">${item.size} KB</span>`;
                }
                grid.appendChild(card);
            });

            if (grid.innerHTML === '') {
                grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: #777; font-size: 13px; padding: 20px;">Папка пуста</div>';
            }
        }

        function navigate(folderPath) {
            currentFolder = folderPath;
            renderManager();
        }

        renderManager();
    </script>
</body>
</html>
