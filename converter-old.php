<?php
$root_dir = "/data/data/com.termux/files/home/apache/files";
$message = "";
$message_type = "";
$compiled_url = "";

function renderXploreTree($dir, $root_base) {
    $ffs = scandir($dir);
    unset($ffs[array_search('.', $ffs, true)], $ffs[array_search('..', $ffs, true)]);

    if (count($ffs) < 1) return;

    // Скрываем подпапки по умолчанию (стиль display: none), кроме самого корня
    $style = ($dir === $root_base) ? '' : 'style="display:none;"';
    echo '<ul ' . $style . '>';
    
    foreach ($ffs as $ff) {
        // Жестко игнорируем тяжелый системный мусор и скрытые папки кэша
        if ($ff === '.' || $ff === 'cache' || $ff === 'vendor' || $ff === 'node_modules' || strpos($ff, '.') === 0) continue;

        $current_path = $dir . '/' . $ff;
        $relative_path = str_replace($root_base . '/', '', $current_path);

        if (is_dir($current_path)) {
            // Узел папки: добавляем класс-триггер для JS и стрелочку
            echo '<li class="folder-node"><span class="folder-toggle">▶ 📁 ' . htmlspecialchars($ff) . '</span>';
            renderXploreTree($current_path, $root_base);
            echo '</li>';
        } else {
            $ext = pathinfo($ff, PATHINFO_EXTENSION);
            if ($ext === 'txt') {
                echo '<li class="file-node">';
                echo '<form method="POST" action="" style="display:inline;">';
                echo '<input type="hidden" name="target_file_path" value="' . htmlspecialchars($relative_path) . '">';
                echo '📄 <button type="submit" name="compile_tree_file" class="tree-compile-btn">' . htmlspecialchars($ff) . '</button>';
                echo '</form>';
                echo '</li>';
            }
        }
    }
    echo '</ul>';
}

// ⚡ ПОТОКОВЫЙ УЗЕЛ КОМПИЛЯЦИИ ИЗ СТРУКТУРЫ ДЕРЕВА
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['compile_tree_file'])) {
    $safe_relative = str_replace(['../', '..\\'], '', $_POST['target_file_path']);
    $file_path = $root_dir . '/' . $safe_relative;

    if (file_exists($file_path) && pathinfo($file_path, PATHINFO_EXTENSION) === 'txt') {
        $filename = pathinfo($file_path, PATHINFO_FILENAME);
        $file_dir = pathinfo($file_path, PATHINFO_DIRNAME);
        $output_file = $file_dir . '/' . $filename . '.html';

        $txt_content = file_get_contents($file_path);
        $safe_content = htmlspecialchars($txt_content, ENT_QUOTES, 'UTF-8');
        
        $lines = explode("\n", $safe_content);
        $html_paragraphs = "";
        foreach ($lines as $line) {
            if (trim($line) !== "") {
                $html_paragraphs .= "        <p>" . trim($line) . "</p>\n";
            }
        }

        $html_template = "<!DOCTYPE html>
<html lang='ru'>
<head>
    <meta charset='utf-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>" . htmlspecialchars($filename) . "</title>
    <style>
        body { background: #ffffff; color: #1f2328; font-family: -apple-system, BlinkMacSystemFont, arial; line-height: 1.6; margin: 0; padding: 30px; display: flex; justify-content: center; }
        .content-box { width: 100%; max-width: 800px; border: 1px solid #d0d7de; background: #f6f8fa; padding: 40px; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); box-sizing: border-box; }
        h1 { font-size: 26px; border-bottom: 1px solid #d0d7de; padding-bottom: 10px; margin-top: 0; color: #0969da; }
        p { font-size: 16px; margin: 12px 0; white-space: pre-wrap; }
    </style>
</head>
<body>
    <div class='content-box'>
        <h1>" . htmlspecialchars($filename) . "</h1>\n" . $html_paragraphs . "    </div>
</body>
</html>";

        if (file_put_contents($output_file, $html_template) !== false) {
            chmod($output_file, 0777);
            $message = "Done! File converted to html.";
            $message_type = "success";
            
            $web_link = str_replace($root_dir, '', $output_file);
            $compiled_url = ltrim($web_link, '/');
        } else {
            $message = "Critical error.";
            $message_type = "error";
        }
    } else {
        $message = "Error: File not found or not secure.";
        $message_type = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TXT to HTML Converter</title>
    <style>
        body { background: #ffffff; color: #1f2328; font-family: -apple-system, BlinkMacSystemFont, arial; margin: 0; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; box-sizing: border-box; touch-action: manipulation; }
        .compiler-panel { width: 100%; max-width: 700px; border: 1px solid #d0d7de; background: #f6f8fa; padding: 30px; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.04); }
        h2 { margin-top: 0; font-size: 22px; color: #0969da; border-bottom: 1px solid #d0d7de; padding-bottom: 10px; font-weight: bold; }
        .tree-container { background: #ffffff; border: 1px solid #d0d7de; border-radius: 6px; padding: 20px; max-height: 400px; overflow-y: auto; margin-bottom: 20px; box-sizing: border-box; }
        ul { list-style-type: none; padding-left: 18px; margin: 4px 0; }
        li { margin: 6px 0; font-size: 16px; position: relative; }
        .folder-node { color: #24292f; }
        .folder-toggle { cursor: pointer; font-weight: bold; padding: 2px 5px; border-radius: 4px; display: inline-block; transition: 0.1s; }
        .folder-toggle:hover { background: #eaeef2; color: #0969da; }
        .folder-node.expanded > .folder-toggle { color: #0969da; }
        .file-node { padding-left: 10px; }
        .tree-compile-btn { background: none; border: none; color: #24292f; font-family: arial; font-size: 15px; cursor: pointer; padding: 2px 6px; border-radius: 4px; font-weight: 500; text-align: left; }
        .tree-compile-btn:hover { background: #dafbe1; color: #1a7f37; font-weight: bold; }
        .alert { padding: 12px 15px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; font-weight: bold; }
        .alert-success { background: #dafbe1; color: #1a7f37; border: 1px solid #a2f5ba; }
        .alert-error { background: #ffebe9; color: #cf222e; border: 1px solid #ffc5c2; }
        .link-box { margin-top: 5px; background: #ffffff; border: 1px solid #dafbe1; padding: 15px; border-radius: 6px; text-align: center; font-size: 16px; box-shadow: 0 2px 6px rgba(26,127,55,0.05); }
        .link-box a { color: #1a7f37; text-decoration: none; font-weight: bold; border-bottom: 2px solid #a2f5ba; padding: 2px 4px; }
        .link-box a:hover { color: #115e29; background: #dafbe1; border-radius: 4px; }
        .instruction-text { font-size: 13px; color: #57606a; margin-bottom: 15px; line-height: 1.4; }
    </style>
</head>
<body>

<div class="compiler-panel">
    <h2>TXT to HTML Converter</h2>
    <p class="instruction-text">TXT to HTML Converter. Tap on folders to open them. Find any txt file and press on it to convert it to html.</p>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <?php if ($compiled_url): ?>
        <div class="link-box">
            <a href="/<?php echo htmlspecialchars($compiled_url); ?>" target="_blank">Open converted page</a>
        </div>
        <div style="margin-bottom: 20px;"></div>
    <?php endif; ?>

    <div class="tree-container">
        <div class="folder-node expanded" style="margin-bottom: 5px;">💾 <strong>root/</strong></div>
        <?php renderXploreTree($root_dir, $root_dir); ?>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const toggles = document.querySelectorAll(".folder-toggle");
    
    toggles.forEach(toggle => {
        toggle.addEventListener("click", function(e) {
            e.stopPropagation(); // Предотвращаем баг ложного срабатывания на верхних узлах
            
            const li = this.parentElement;
            const subUl = li.querySelector("ul");
            
            if (subUl) {
                if (subUl.style.display === "none") {
                    subUl.style.display = "block";
                    li.classList.add("expanded");
                    this.innerHTML = this.innerHTML.replace("▶", "▼");
                } else {
                    subUl.style.display = "none";
                    li.classList.remove("expanded");
                    this.innerHTML = this.innerHTML.replace("▼", "▶");
                }
            }
        });
    });
});
</script>

</body>
</html>
