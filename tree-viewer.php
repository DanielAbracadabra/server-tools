<?php
// This is a file listing script.
// Very good if you have blocked autoindex at apache and you need to list files

header('Content-Type: text/html; charset=utf-8');

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$current_host = $_SERVER['HTTP_HOST']; 

$current_script_path = dirname($_SERVER['SCRIPT_NAME']);
$base_url = $protocol . "://" . $current_host . rtrim(str_replace('\\', '/', $current_script_path), '/');
$root_dir = realpath(__DIR__);

// Blacklist. Put here names of folders and files and they will be blocked from listing.
$blacklist = [
    'secret_folder',        // Example Folder
    'confidential_data.xml',// Example File
    'viewer.php'            // This Script
];

function get_file_emoji($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $archives = ['zip', 'rar', 'tar', 'gz', '7z', 'bz2', 'xz'];
    $apks     = ['apk', 'apks', 'xapk'];
    $scripts  = ['sh', 'bash', 'py', 'pl', 'php', 'js', 'json', 'xml', 'xsl', 'xslt'];
    $images   = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'ico', 'svg'];
    $docs     = ['txt', 'md', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];
    
    if (in_array($ext, $archives)) return '📦';
    if (in_array($ext, $apks))     return '🤖';
    if (in_array($ext, $scripts))  return '💻';
    if (in_array($ext, $images))   return '🖼️';
    if (in_array($ext, $docs))     return '📝';
    return '📄';
}

function render_interactive_manager($dir, $base_url, $root_dir, $blacklist) {
    if (!is_dir($dir)) return;

    $directory = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
    $iterator  = new RecursiveIteratorIterator($directory, RecursiveIteratorIterator::SELF_FIRST);

    echo '<div class="folder-container root-folder">';
    echo '<div class="folder-header active" onclick="toggleFolder(this)">📁 ' . htmlspecialchars(basename($root_dir)) . ' <span class="arrow">▼</span></div>';
    echo '<div class="folder-content">';

    $current_depth = 0;
    $opened_divs = 0;

    foreach ($iterator as $path => $object) {
        $depth = $iterator->getDepth();
        $name  = $object->getFilename();

        if ($name === 'tree-viewer.php' || in_array($name, $blacklist)) {
            continue;
        }

        $path_parts = explode(DIRECTORY_SEPARATOR, realpath($object->getRealPath()));
        if (array_intersect($path_parts, $blacklist)) {
            continue;
        }

        if ($depth > $current_depth) {
            $opened_divs++;
        } elseif ($depth < $current_depth) {
            $diff = $current_depth - $depth;
            echo str_repeat('</div></div>', $diff);
            $opened_divs -= $diff;
        }

        $current_depth = $depth;

        $item_real_path = realpath($object->getRealPath());
        $relative_path = substr($item_real_path, strlen($root_dir));
        $relative_path = ltrim(str_replace('\\', '/', $relative_path), '/');
        
        $web_link = $base_url . '/' . $relative_path;

        if ($object->isDir()) {
            echo '<div class="folder-container">';
            echo '<div class="folder-header" onclick="toggleFolder(this)">📁 <span class="dir-name">' . htmlspecialchars($name) . '</span> <span class="arrow">▶</span></div>';
            echo '<div class="folder-content" style="display: none;">';
        } else {
            $emoji = get_file_emoji($name);
            $size  = round($object->getSize() / 1024, 2);
            echo '<div class="file-item">' . $emoji . ' <a href="' . htmlspecialchars($web_link) . '" target="_blank" class="file-link">' . htmlspecialchars($name) . '</a> <span class="file-size">(' . $size . ' KB)</span></div>';
        }
    }

    echo str_repeat('</div></div>', $opened_divs + 1);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Tree Autoindex</title>
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; 
            background-color: #f5f9fc; 
            color: #333333; 
            padding: 25px; 
            margin: 0; 
        }
        .container { 
            max-width: 900px; 
            margin: 0 auto; 
            background: #ffffff; 
            padding: 25px; 
            border-radius: 8px; 
            border: 1px solid #d0e1fd; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.05); 
        }
        h1 { 
            color: #0056b3; 
            border-bottom: 2px solid #b3d7ff; 
            padding-bottom: 12px; 
            text-align: center; 
            font-size: 20px; 
            margin-top: 0; 
            font-weight: 600; 
        }
        .folder-container { margin-top: 5px; margin-bottom: 5px; }
        .folder-header {
            background: #eef5fc; padding: 8px 14px; border-radius: 6px; border: 1px solid #d6e6f2;
            cursor: pointer; font-family: monospace; font-weight: bold; display: inline-flex;
            align-items: center; user-select: none; transition: background 0.2s;
        }
        .folder-header:hover { background: #dceaf7; }
        .dir-name { color: #0076d6; margin-right: 8px; }
        .arrow { font-size: 10px; color: #0076d6; margin-left: 5px; }
        .folder-content { margin-left: 20px; border-left: 1px dashed #b3d7ff; padding-left: 15px; }
        .file-item { margin: 6px 0; font-size: 13px; font-family: monospace; display: flex; align-items: center; padding-left: 5px; }
        .file-link { color: #111111; text-decoration: none; margin-left: 5px; border-bottom: 1px dashed transparent; transition: color 0.2s, border-color 0.2s; }
        .file-link:hover { color: #0076d6; border-bottom-color: #0076d6; }
        .file-size { color: #6c757d; font-size: 11px; margin-left: 6px; }
        .root-folder > .folder-header { background: #b3d7ff; color: #0056b3; border-color: #0056b3; font-size: 14px; }
    </style>
    <script>
        function toggleFolder(header) {
            var content = header.nextElementSibling;
            var arrow = header.querySelector('.arrow');
            if (content.style.display === "none") {
                content.style.display = "block";
                header.classList.add('active');
                if (arrow) arrow.innerHTML = "▼";
            } else {
                content.style.display = "none";
                header.classList.remove('active');
                if (arrow) arrow.innerHTML = "▶";
            }
        }
    </script>
</head>
<body>
    <div class="container">
        <h1>Tree Autoindex</h1>
        <?php render_interactive_manager($root_dir, $base_url, $root_dir, $blacklist); ?>
    </div>
</body>
</html>
