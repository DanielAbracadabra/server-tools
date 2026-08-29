<?php
$root_dir = "/data/data/com.termux/files/home/apache/files";
# Change rootdir here if it does not match your documentroot
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=UTF-8');
    
    $raw_input = file_get_contents('php://input');
    $data = json_decode($raw_input, true);
    $search_query = isset($data['query']) ? trim($data['query']) : '';
    $results = [];

    if ($search_query !== '') {
        $directory = new RecursiveDirectoryIterator($root_dir);
        $iterator = new RecursiveIteratorIterator($directory);
        $lower_query = mb_strtolower($search_query, 'UTF-8');

        foreach ($iterator as $file) {
            if ($file->isDir()) continue;
            
            $filepath = $file->getPathname();
            if (preg_match('/(\/\.git|[\/\\\\]cache[\/\\\\]|[\/\\\\]vendor[\/\\\\]|[\/\\\\]node_modules[\/\\\\])/', $filepath)) {
                continue;
            }
            
            $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
            if (in_array($ext, ['php', 'html', 'js', 'css', 'txt', 'md', 'json', 'yaml'])) {
                $file_content = file_get_contents($filepath);
                if ($file_content === false) continue;

                if (!str_contains(mb_strtolower($file_content, 'UTF-8'), $lower_query)) {
                    continue;
                }

                $file_lines = explode("\n", $file_content);
                foreach ($file_lines as $line_num => $line_content) {
                    if (str_contains(mb_strtolower($line_content, 'UTF-8'), $lower_query)) {
                        $relative_path = str_replace($root_dir . '/', '', $filepath);
                        $results[] = [
                            'file' => htmlspecialchars($relative_path),
                            'line' => $line_num + 1,
                            'content' => htmlspecialchars(trim($line_content))
                        ];
                    }
                }
            }
        }
    }
    
    echo json_encode(['status' => 'success', 'results' => $results]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Search</title>
    <style>
        html, body {
            background: #ffffff;
            color: #1f2328;
            font-family: -apple-system, BlinkMacSystemFont, arial;
            margin: 0 !important;
            padding: 0 !important;
            width: 100vw;
            height: 100vh;
            overflow: hidden;
            box-sizing: border-box;
        }
        .search-panel {
            width: 100vw;
            height: 100vh;
            background: #f6f8fa;
            padding: 20px !important;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
        }
        h2 {
            margin-top: 0;
            color: #0969da;
            border-bottom: 1px solid #d0d7de;
            padding-bottom: 10px;
            font-size: 22px;
            margin-bottom: 15px;
        }
        .form-box {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        input[type="text"] {
            flex-grow: 1;
            padding: 12px;
            font-size: 15px;
            font-family: arial;
            border: 1px solid #d0d7de;
            border-radius: 6px;
            background: #ffffff;
            color: #1f2328;
            outline: none;
            box-sizing: border-box;
        }
        input[type="text"]:focus {
            border-color: #0969da;
            box-shadow: 0 0 0 3px rgba(9,105,218,0.15);
        }
        .btn-search {
            background: #1f2328;
            color: #ffffff;
            border: 1px solid rgba(27,31,36,0.15);
            padding: 0 25px;
            font-size: 14px;
            font-family: arial;
            font-weight: bold;
            border-radius: 6px;
            cursor: pointer;
            transition: 0.1s;
        }
        .btn-search:hover { background: #24292f; }
        
        .results-box {
            background: #ffffff;
            border: 1px solid #d0d7de;
            border-radius: 6px;
            padding: 15px;
            height: calc(100vh - 100px) !important;
            overflow-y: auto;
            box-sizing: border-box;
        }
        .result-item {
            padding: 10px;
            border-bottom: 1px solid #d0d7de;
            font-size: 14px;
        }
        .result-item:last-child { border-bottom: none; }
        .file-path { color: #0969da; font-weight: bold; text-decoration: none; }
        .file-path:hover { text-decoration: underline; }
        .line-num { color: #57606a; font-weight: bold; margin-right: 10px; }
        .code-snippet { background: #f6f8fa; padding: 6px; border-radius: 4px; display: block; margin-top: 5px; font-family: monospace; white-space: pre-wrap; overflow-x: auto; border: 1px solid #eaeef2; }
        .notify { color: #57606a; font-style: italic; text-align: center; padding: 20px; font-size: 15px; }
        
        @media (max-width: 768px) {
            .search-panel { padding: 12px !important; }
            h2 { font-size: 18px; margin-bottom: 10px; }
            .results-box { height: calc(100vh - 85px) !important; padding: 10px; }
            input[type="text"] { font-size: 14px; padding: 10px; }
            .btn-search { padding: 0 15px; font-size: 13px; }
            .result-item { font-size: 13px; }
        }
    </style>
</head>
<body>

<div class="search-panel">
    <h2>Search</h2>
    
    <div class="form-box">
        <input type="text" id="query-input" placeholder="Enter keyword (e.g. Bcrypt, album, css)..." required>
        <button onclick="executeAjaxSearch()" class="btn-search" id="search-button">Search</button>
    </div>

    <div class="results-box" id="results-container">
        <div class="notify">Enter your query above.</div>
    </div>
</div>

<script>
function executeAjaxSearch() {
    const queryInput = document.getElementById("query-input");
    const resultsContainer = document.getElementById("results-container");
    const searchBtn = document.getElementById("search-button");
    const query = queryInput.value.trim();

    if (query === "") return;

    searchBtn.disabled = true;
    searchBtn.innerText = "Searching...";
    resultsContainer.innerHTML = '<div class="notify">Searching... Please wait</div>';

    fetch("?ajax=1", {
        method: "POST",
        headers: { 
            "Content-Type": "application/json" 
        },
        body: JSON.stringify({ query: query })
    })
    .then(response => {
        if (!response.ok) throw new Error('Network response error');
        return response.json();
    })
    .then(data => {
        searchBtn.disabled = false;
        searchBtn.innerText = "Search";
        
        if (data.status === "success") {
            const results = data.results;
            
            if (results.length === 0) {
                resultsContainer.innerHTML = '<div class="notify">No matches found. 🕳️</div>';
                return;
            }

            let htmlOutput = `<div style="font-weight: bold; margin-bottom: 11px; color: #1a7f37;">Matches found: ${results.length}</div>`;
            
            results.forEach(res => {
                htmlOutput += `
                    <div class="result-item">
                        📄 <a href="/${res.file}" target="_blank" class="file-path">${res.file}</a>
                        <span class="line-num">[Line: ${res.line}]</span>
                        <span class="code-snippet">${res.content}</span>
                    </div>
                `;
            });
            
            resultsContainer.innerHTML = htmlOutput;
        }
    })
    .catch(error => {
        searchBtn.disabled = false;
        searchBtn.innerText = "Search";
        resultsContainer.innerHTML = '<div class="notify" style="color:#cf222e;">❌ Error executing request. Check logs.</div>';
    });
}

document.getElementById("query-input").addEventListener("keydown", function(e) {
    if (e.key === "Enter") {
        executeAjaxSearch();
    }
});
</script>

</body>
</html>
