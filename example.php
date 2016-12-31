<?php
error_reporting(E_ERROR | E_PARSE);
require_once('lib/meekrodb.2.3.class.php');

DB::$user = 'root';
DB::$password = '';
DB::$dbName = 'fbchatbot';
DB::$host = 'localhost'; 
DB::$encoding = 'utf8mb4_unicode_ci'; 

DB::$error_handler = 'sql_error_handler';
 
function sql_error_handler($params) {
  echo "Error: " . $params['error'] . "<br>\n";
  echo "Query: " . $params['query'] . "<br>\n";
  die; // don't want to keep going if a query broke
}


global $apiurl, $graphapiurl, $page_access_token, $profiledata;
$profiledata = array();

$page_access_token="ADD_YOUR_FB_PAGE_TOKEN_HERE";

$apiurl = "https://graph.facebook.com/v2.6/me/messages?access_token=$page_access_token";

$graphapiurl = "https://graph.facebook.com/v2.6/";

if($_REQUEST['hub_verify_token'] == "SimpleBot12345"){exit($_REQUEST['hub_challenge']);}
if($_REQUEST['chatbotsetup'] == "12345"){setup_bot(); exit();}
if($_REQUEST['chatbotsetupreset'] == "12345"){setup_bot_reset(); exit();}


$input = json_decode(file_get_contents("php://input"), true, 512, JSON_BIGINT_AS_STRING);

$fp = fopen("logfbdata.txt","a");
if( $fp == false ){ echo "file creation failed";}
else{fwrite($fp,print_r($input, true)); fclose($fp);}

if(array_key_exists('entry', $input)){fn_process_fbdata($input);}



//#####################################
function fn_process_fbdata($input){
    foreach ($input['entry'] as $k=>$v) {

        foreach ($v['messaging'] as $k2=>$v2) {
            
           if(array_key_exists('postback', $v2)){
                 fn_command_processpostback($v2['sender']['id'], $v2['postback']['payload']);
           }
            
           if(array_key_exists('message', $v2)){
             if(array_key_exists('text', $v2['message']) && !array_key_exists('app_id', $v2["message"])){ 
                 if(array_key_exists('quick_reply', $v2['message'])){ 
                  fn_command_processquickreply($v2['sender']['id'], $v2['message']['text'], $v2['message']['quick_reply']['payload']);
                 }
                 else{
                 fn_command_processtext($v2['sender']['id'], $v2['message']['text']);
                 }
             }
               
             if(array_key_exists('attachments', $v2['message'])){ 
               foreach ($v2['message']['attachments'] as $k3=>$v3) {
                    if($v3['type'] == 'image' && !array_key_exists('app_id', $v2["message"])){
                        fn_command_processimage($v2['sender']['id'], $v3['payload']['url']);
                    }
                    if($v3['type'] == 'location' && !array_key_exists('app_id', $v2["message"])){
                        fn_command_processlocation($v2['sender']['id'], $v3);
                    }
                    if($v3['type'] == 'audio' && !array_key_exists('app_id', $v2["message"])){
                        fn_command_processaudio($v2['sender']['id'], $v3['payload']['url']);
                    }
                    if($v3['type'] == 'video' && !array_key_exists('app_id', $v2["message"])){
                        fn_command_processvideo($v2['sender']['id'], $v3['payload']['url']);
                    }
                    if($v3['type'] == 'file' && !array_key_exists('app_id', $v2["message"])){
                        fn_command_processfile($v2['sender']['id'], $v3['payload']['url']);
                    }
               }
             }   
           }
           

        }
    }

}

//#####################################
function fn_command_processpostback($senderid, $cmdtext)
{
global $apiurl, $graphapiurl, $page_access_token, $profiledata;

if(count($profiledata) == 0)
{    
    $profiledata = DB::queryFirstRow("select * from fbprofile WHERE fid = $senderid");

    if(is_null($profiledata))
    {
        $profiledata = send_curl_cmd('', $graphapiurl.$senderid.'?access_token='.$page_access_token);
        $profiledata['fid'] = $senderid;
        $profiledata['firstseen'] = time();
        DB::insert('fbprofile', $profiledata);
    }
}
    
if($cmdtext == "Get Started!"){
    send_text_message($senderid, "Hi ".$profiledata["first_name"]."!, How can i help you today?");    
}
elseif($cmdtext == "Bot_Help"){
    send_text_message($senderid, "These are the available commands for Help");    
} 
elseif($cmdtext == "Bot_Orders"){
    send_text_message($senderid, "These are Your previous orders");    
} 
elseif($cmdtext == "Bot_Cart"){
    send_text_message($senderid, "These are the items in your cart.");    
}     
else{
    send_text_message($senderid, "Ok. Got it: ".$cmdtext);
} 
    
}
//#####################################
function fn_command_processlocation($senderid, $data)
{

$j  = $data['title']."\r\n";
$j .= "Latitude: ".$data['payload']["coordinates"]["lat"]."\r\n";    
$j .= "Longitude: ".$data['payload']["coordinates"]["long"]."\r\n";    

send_text_message($senderid, $j);  
}
//#####################################
function fn_command_processtext($senderid, $cmdtext)
{
global $apiurl, $graphapiurl, $page_access_token, $profiledata;

if(count($profiledata) == 0)
{    
    $profiledata = DB::queryFirstRow("select * from fbprofile WHERE fid = $senderid");

    if(is_null($profiledata))
    {
        $profiledata = send_curl_cmd('', $graphapiurl.$senderid.'?access_token='.$page_access_token);
        $profiledata['fid'] = $senderid;
        $profiledata['firstseen'] = time();
        DB::insert('fbprofile', $profiledata);
    }
}
    
fn_command_sentiments($senderid, $cmdtext);
    
if($cmdtext == "Hi"){
    send_text_message($senderid, "Hi ".$profiledata["first_name"]."! ");  
}
elseif($cmdtext == "send quickreplytext"){
    sendtemplate_quickreplytext($senderid);
}   
elseif($cmdtext == "send quickreplyimage"){
    sendtemplate_quickreplyimage($senderid);
}  
elseif($cmdtext == "send quickreplytemplate"){
    sendtemplate_quickreplytemplate($senderid);
}      
elseif($cmdtext == "send button template"){
    sendtemplate_btn($senderid);
} 
elseif($cmdtext == "send generic template"){
    sendtemplate_generic($senderid);
} 
elseif($cmdtext == "send templated carousel"){
    sendtemplate_carousel($senderid);
}       
elseif($cmdtext == "send image"){
    sendfile_tofb($senderid, "image", "https://18c4585d.ngrok.io/files/sampleimage.png");   
} 
elseif($cmdtext == "send audio"){
    sendfile_tofb($senderid, "audio", "https://18c4585d.ngrok.io/files/sampleaudio.mp3");   
} 
elseif($cmdtext == "send video"){
    sendfile_tofb($senderid, "video", "http://www.sample-videos.com/video/mp4/720/big_buck_bunny_720p_1mb.mp4");   
} 
elseif($cmdtext == "send receipt"){
    sendfile_tofb($senderid, "file", "https://18c4585d.ngrok.io/files/samplefile.txt");   
}     
elseif($cmdtext == "name?"){
    send_text_message($senderid, "My name is Chatbot!");    
}
else{
    send_text_message($senderid, "Hmm.. Still learning: ".$cmdtext);
}  
    
}
//#####################################
function fn_command_processquickreply($senderid, $replytext, $cmdtext)
{
global $apiurl, $graphapiurl, $page_access_token, $profiledata;

if(count($profiledata) == 0)
{    
    $profiledata = DB::queryFirstRow("select * from fbprofile WHERE fid = $senderid");

    if(is_null($profiledata))
    {
        $profiledata = send_curl_cmd('', $graphapiurl.$senderid.'?access_token='.$page_access_token);
        $profiledata['fid'] = $senderid;
        $profiledata['firstseen'] = time();
        DB::insert('fbprofile', $profiledata);
    }
}


send_text_message($senderid, "Ok ".$profiledata["first_name"]."! \r\n".$replytext.': '.$cmdtext);  
    
    
}
//######################################
function sendtemplate_quickreplytext($senderid)
{
global $apiurl, $graphapiurl, $page_access_token;
    
$reply[] = array("content_type" => "text", "title"=> "Pepperoni", "payload" => "Bot_Order_Pepperoni");
$reply[] = array("content_type" => "text", "title"=> "Mushroom", "payload" => "Bot_Order_Mushroom");
$reply[] = array("content_type" => "text", "title"=> "Onion", "payload" => "Bot_Order_Onion");
    
$sendmsg = new stdClass();
$sendmsg->recipient->id = $senderid;
$sendmsg->message->text = 'Pick your Pizza Topping below!';
$sendmsg->message->quick_replies = $reply;    

$res = send_curl_data_tofb($sendmsg);
    
$fp = fopen("logfbdata.txt","a");
if( $fp == false ){ echo "file creation failed";}
else{fwrite($fp,print_r($res, true)); fclose($fp);}
}
//######################################
function sendtemplate_quickreplyimage($senderid)
{
global $apiurl, $graphapiurl, $page_access_token;
send_text_message($senderid, "Please select a topping for your pizza");
    
$reply[] = array("content_type" => "text", "title"=> "Pepperoni", "payload" => "Bot_Order_Pepperoni");
$reply[] = array("content_type" => "text", "title"=> "Mushroom", "payload" => "Bot_Order_Mushroom");
$reply[] = array("content_type" => "text", "title"=> "Onion", "payload" => "Bot_Order_Onion");
    
$sendmsg = new stdClass();
$sendmsg->recipient->id = $senderid;
$sendmsg->message->attachment->type = 'image';
$sendmsg->message->attachment->payload->url = 'https://18c4585d.ngrok.io/files/pizza1.jpg';   
$sendmsg->message->quick_replies = $reply;    

$res = send_curl_data_tofb($sendmsg);
    
$fp = fopen("logfbdata.txt","a");
if( $fp == false ){ echo "file creation failed";}
else{fwrite($fp,print_r($res, true)); fclose($fp);}
}
//######################################
function sendtemplate_quickreplytemplate($senderid)
{
global $apiurl, $graphapiurl, $page_access_token;

send_text_message($senderid, "Please select an option to proceed:");
    
$reply[] = array("content_type" => "text", "title"=> "Cancel", "payload" => "Bot_Order_Cancel");
$reply[] = array("content_type" => "text", "title"=> "StartOver", "payload" => "Bot_Order_StartOver");


$buttons[] = array("type" => "postback", "title"=> "Buy Now", "payload" => "Bot_Order_32");
$buttons[] = array("type" => "postback", "title"=> "Save for Later", "payload" => "Bot_Order_Save_32");
$buttons[] = array("type" => "phone_number", "title"=> "Contact Seller", "payload" => "+60162393100");

$elements[] = array("title" => "Awesome Product #1",
                    "image_url" => "https://18c4585d.ngrok.io/files/i1.jpg",  'buttons' => $buttons);    
    
    
$sendmsg = new stdClass();
$sendmsg->recipient->id = $senderid;
$sendmsg->message->attachment->type = 'template';
$sendmsg->message->attachment->payload->template_type = 'generic';
$sendmsg->message->attachment->payload->elements = $elements;  
$sendmsg->message->quick_replies = $reply;    

$res = send_curl_data_tofb($sendmsg);
    
$fp = fopen("logfbdata.txt","a");
if( $fp == false ){ echo "file creation failed";}
else{fwrite($fp,print_r($res, true)); fclose($fp);}
}
//######################################
function sendtemplate_btn($senderid)
{
global $apiurl, $graphapiurl, $page_access_token;
    
$elements[] = array("type" => "postback", "title"=> "In Chat Window", "payload" => "Bot_Chat_Order");
$elements[] = array("type" => "web_url", "title"=> "On Website", "url" => "http://google.com");
$elements[] = array("type" => "phone_number", "title"=> "Over Phone", "payload" => "+60162393100");
    
$sendmsg = new stdClass();
$sendmsg->recipient->id = $senderid;
$sendmsg->message->attachment->type = 'template';
$sendmsg->message->attachment->payload->template_type = 'button';
$sendmsg->message->attachment->payload->text = 'How do you want to place your order?';
$sendmsg->message->attachment->payload->buttons = $elements;    

$res = send_curl_data_tofb($sendmsg);
    
$fp = fopen("logfbdata.txt","a");
if( $fp == false ){ echo "file creation failed";}
else{fwrite($fp,print_r($res, true)); fclose($fp);}
}
//######################################
function sendtemplate_generic($senderid)
{
global $apiurl, $graphapiurl, $page_access_token;
    
$buttons[] = array("type" => "postback", "title"=> "Buy Now", "payload" => "Bot_Order_32");
$buttons[] = array("type" => "postback", "title"=> "Save for Later", "payload" => "Bot_Order_Save_32");
$buttons[] = array("type" => "phone_number", "title"=> "Contact Seller", "payload" => "+60162393100");

$elements[] = array("title" => "Awesome Product #1", "subtitle"=> "It has these great qualities, would be useful!", 
                    "image_url" => "https://18c4585d.ngrok.io/files/i1.jpg", "item_url" => "http://google.com/", 'buttons' => $buttons);    
    
$sendmsg = new stdClass();
$sendmsg->recipient->id = $senderid;
$sendmsg->message->attachment->type = 'template';
$sendmsg->message->attachment->payload->template_type = 'generic';
$sendmsg->message->attachment->payload->elements = $elements;

$res = send_curl_data_tofb($sendmsg);
    
$fp = fopen("logfbdata.txt","a");
if( $fp == false ){ echo "file creation failed";}
else{fwrite($fp,print_r($res, true)); fclose($fp);}
}
//######################################
function sendtemplate_carousel($senderid)
{
global $apiurl, $graphapiurl, $page_access_token;
    
$buttons[] = array("type" => "postback", "title"=> "Buy Now", "payload" => "Bot_Order_32");
$buttons[] = array("type" => "postback", "title"=> "Save for Later", "payload" => "Bot_Order_Save_32");
$buttons[] = array("type" => "phone_number", "title"=> "Contact Seller", "payload" => "+60162393100");

$elements[] = array("title" => "Awesome Product #1", "subtitle"=> "It has these great qualities, would be useful!", 
                    "image_url" => "https://18c4585d.ngrok.io/files/i1.jpg", "item_url" => "http://google.com/", 'buttons' => $buttons);    
$elements[] = array("title" => "Awesome Product #2", "subtitle"=> "It has these great qualities, would be useful!", 
                    "image_url" => "https://18c4585d.ngrok.io/files/i2.jpg", "item_url" => "http://google.com/", 'buttons' => $buttons);    
$elements[] = array("title" => "Awesome Product #3", "subtitle"=> "It has these great qualities, would be useful!", 
                    "image_url" => "https://18c4585d.ngrok.io/files/i3.jpg", "item_url" => "http://google.com/", 'buttons' => $buttons);    
$elements[] = array("title" => "Awesome Product #4", "subtitle"=> "It has these great qualities, would be useful!", 
                    "image_url" => "https://18c4585d.ngrok.io/files/i4.jpg", "item_url" => "http://google.com/", 'buttons' => $buttons);    
                
$sendmsg = new stdClass();
$sendmsg->recipient->id = $senderid;
$sendmsg->message->attachment->type = 'template';
$sendmsg->message->attachment->payload->template_type = 'generic';
$sendmsg->message->attachment->payload->elements = $elements;

$res = send_curl_data_tofb($sendmsg);
    
$fp = fopen("logfbdata.txt","a");
if( $fp == false ){ echo "file creation failed";}
else{fwrite($fp,print_r($res, true)); fclose($fp);}
}
//######################################
function sendfile_tofb($senderid, $filetype, $fileurl)
{
global $apiurl, $graphapiurl, $page_access_token;
$sendmsg = new stdClass();
$sendmsg->recipient->id = $senderid;
$sendmsg->message->attachment->type = $filetype;
$sendmsg->message->attachment->payload->url = $fileurl;    

$res = send_curl_data_tofb($sendmsg);
    
$fp = fopen("logfbdata.txt","a");
if( $fp == false ){ echo "file creation failed";}
else{fwrite($fp,print_r($res, true)); fclose($fp);}
}
//######################################
function send_curl_data_tofb($sendmsg, $fburl, $dowhat = 1)
{
global $apiurl;
if($fburl == "") {$fburl = $apiurl;}
$jsonDataEncoded = json_encode($sendmsg);

$ch = curl_init($fburl);
if($dowhat == 2)
{ 
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");    
}
else
{
curl_setopt($ch, CURLOPT_POST, 1);
}


    
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded); //Attach our encoded JSON string to the POST fields.
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
$jresult = json_decode($result, true);
return $jresult;    
}
//######################################
function setup_bot()
{
global $apiurl, $graphapiurl, $page_access_token;
$sendmsg = new stdClass();
$sendmsg->setting_type = "greeting";
$sendmsg->greeting->text = "Welcome to our Awesome Page. Our helpful chatbot will guide you through the process.";
$res = send_curl_data_tofb($sendmsg, $graphapiurl.'/me/thread_settings?access_token='.$page_access_token);

    print_r($res);

$sendmsg = new stdClass();
$sendmsg->setting_type = "call_to_actions";
$sendmsg->thread_state = "new_thread";
$sendmsg->call_to_actions[] = array("payload" => "Get Started!");
$res = send_curl_data_tofb($sendmsg, $graphapiurl.'/me/thread_settings?access_token='.$page_access_token);

print_r($res);
    
$sendmsg = new stdClass();
$sendmsg->setting_type = "call_to_actions";
$sendmsg->thread_state = "existing_thread";
$elements[] = array("type" => "postback", "title"=> "Help", "payload" => "Bot_Help");
$elements[] = array("type" => "postback", "title"=> "Show Cart", "payload" => "Bot_Cart");    
$elements[] = array("type" => "postback", "title"=> "My Orders", "payload" => "Bot_Orders");     
$elements[] = array("type" => "web_url", "title"=> "Visit Website", "url" => "http://google.com/");      
$sendmsg->call_to_actions = $elements;
$res = send_curl_data_tofb($sendmsg, $graphapiurl.'/me/thread_settings?access_token='.$page_access_token);
$jsonDataEncoded = json_encode($sendmsg);

    
 
print_r($res);
    
}
//######################################
function setup_bot_reset()
{
global $apiurl, $graphapiurl, $page_access_token;
$sendmsg = new stdClass();
$sendmsg->setting_type = "greeting";
$sendmsg->greeting->text = " ";
$res = send_curl_data_tofb($sendmsg, $graphapiurl.'/me/thread_settings?access_token='.$page_access_token, 1);

print_r($res);


$sendmsg = new stdClass();
$sendmsg->setting_type = "call_to_actions";
$sendmsg->thread_state = "new_thread";
$res = send_curl_data_tofb($sendmsg, $graphapiurl.'/me/thread_settings?access_token='.$page_access_token, 2);

print_r($res);

    
$sendmsg = new stdClass();
$sendmsg->setting_type = "call_to_actions";
$sendmsg->thread_state = "existing_thread";
$res = send_curl_data_tofb($sendmsg, $graphapiurl.'/me/thread_settings?access_token='.$page_access_token, 2);
$jsonDataEncoded = json_encode($sendmsg);

print_r($res);

}
//#####################################
function send_curl_cmd($data, $url){

//Encode the array into JSON.
if($data != ""){$jsonDataEncoded = json_encode($data);}

$ch = curl_init($url);
if($data != ""){curl_setopt($ch, CURLOPT_POST, 1);curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);} //Attach our encoded JSON string to the POST fields.
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
$jresult = json_decode($result, true);

    
return $jresult;
}

//#####################################
function fn_command_sentiments($senderid, $cmdtext)
{
include("lib/emoji.php");     

$j = '';
$results = array();
preg_match_all('/./u', $cmdtext, $results);    
$htmlarr = $results[0];   
$emarr = array_keys($GLOBALS['emoji_maps']['names']);
$emojichars = array_intersect($emarr, $htmlarr);

if(count($emojichars) > 0)
{    
    foreach($emojichars as $k=>$v){
        $j .= $k.': '.$GLOBALS['emoji_maps']['names'][$v]."\r\n";
    }
    
     send_text_message($senderid, $j);
}    
  
}
//#####################################
function fn_command_processimage($senderid, $cmdtext)
{

if(strpos($cmdtext, ".png") !== false){
    send_text_message($senderid, "Its a PNG image");    
}
elseif(strpos($cmdtext, ".jpg") !== false){
    send_text_message($senderid, "Its a JPG image");    
}
elseif(strpos($cmdtext, ".gif") !== false){
    send_text_message($senderid, "Its a GIF image");    
}
else{
    send_text_message($senderid, "Hmm.. nice image");
}  
}
//#####################################
function fn_command_processaudio($senderid, $cmdtext)
{
send_text_message($senderid, "Hey! That's a nice Song!");
}
//#####################################
function fn_command_processvideo($senderid, $cmdtext)
{
send_text_message($senderid, "Hey! That's a nice Video!");
}
//#####################################
function fn_command_processfile($senderid, $cmdtext)
{
send_text_message($senderid, "Processing your Order details from this file.");
}
//#####################################
function send_text_message($senderid, $msg){
global $apiurl;

$sendmsg = new stdClass();
$sendmsg->recipient->id = $senderid;
$sendmsg->message->text = $msg;

//Encode the array into JSON.
$jsonDataEncoded = json_encode($sendmsg);

$ch = curl_init($apiurl);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded); //Attach our encoded JSON string to the POST fields.
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
$jresult = json_decode($result, true);


}
//#####################################


