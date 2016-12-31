<?php

// ACTION 1: Add your FB page token within the quotes below

$page_access_token="EAAQj2eBsWk8BAPk23dSLVBVPVmj5PwzMLSQVHYcqEwxOeJ4hZCwQWG4HTN3ZA6MZAxQp0VT6JoE38D9ujZAQCGcV7APvpZBkRc0PZAPwZCEJWqZCt3p23ETwaf3bvWdU5WkW6ovJsLs9tpqz0ZBEJTb3gDjZBKaKZCskfEPjI8gF446EAZDZD";

// ACTION 2:
// visit this link from your browser:
// http://localhost/windows_curl.php?curlcall=12345

// If the result is like this: 
// Array ( [success] => 1 )
// It means you have successfully completed this step

// DO Not Edit below this line.

$subscribeurl = "https://graph.facebook.com/v2.6/me/subscribed_apps?access_token=$page_access_token";
if($_REQUEST['curlcall'] == "12345"){curl_tofb($subscribeurl); exit();}


//######################################
function curl_tofb($apiurl)
{

$ch = curl_init($apiurl);
curl_setopt($ch, CURLOPT_POST, 1);    
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
$jresult = json_decode($result, true);
print_r($jresult);    
//return $jresult;    
}
//######################################
