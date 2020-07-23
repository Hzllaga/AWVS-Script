<?php
error_reporting(0);

@$filename = $argv[0];
@$mode = $argv[1];
@$target = $argv[2];
$awvs_url = "https://localhost:3433";
$awvs_user = "admin@admin.com";
$awvs_password = "admin888";
$auth = login();
if (!$auth) {
    print_r("Login failed, check username and password.");
    die();
}

function showLogo()
{
    print_r("
    ___ _       ___    ____________                
   /   | |     / / |  / / ___/ ___/_________ _____ 
  / /| | | /| / /| | / /\__ \\__ \/ ___/ __ `/ __ \
 / ___ | |/ |/ / | |/ /___/ /__/ / /__/ /_/ / / / /
/_/  |_|__/|__/  |___//____/____/\___/\__,_/_/ /_/  v13\n");
}

function request($url, $method, $isLogin, $data)
{
    global $awvs_url, $auth;
    $header = array("Content-type:application/json; charset=utf8;", "X-Auth:$auth", "Cookie: ui_session=$auth");
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "$awvs_url$url");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "$method");
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, $isLogin);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $response = curl_exec($ch);
    $header = getHeader($response);
    if ($isLogin) {
        return $header['x-auth'];
    }
    curl_close($ch);
    $response = json_decode($response, true);
    return $response;
}

function getHeader($response)
{
    $headers = array();
    $header_text = substr($response, 0, strpos($response, "\r\n\r\n"));
    foreach (explode("\r\n", $header_text) as $i => $line)
        if ($i === 0)
            $headers['http_code'] = $line;
        else {
            list ($key, $value) = explode(': ', $line);
            $headers[strtolower($key)] = $value;
        }
    return $headers;
}

function login()
{
    global $awvs_user, $awvs_password;
    $password = hash('sha256', $awvs_password);
    $payload = json_encode(array("email" => $awvs_user, "password" => $password, "remember_me" => false, "logout_previous" => true));
    return request("/api/v1/me/login", "POST", true, $payload);
}

function getTargets()
{
    return request("/api/v1/targets?l=100", "GET", false, "")['targets'];
}

function addTarget($url)
{
    print_r("Add target $url\n");
    $payload = json_encode(array("address" => $url, "description" => "", "criticality" => "10"));
    return request("/api/v1/targets", "POST", false, $payload)['target_id'];
}

function scanTarget($target_id)
{
    $payload = json_encode(array("target_id" => $target_id, "profile_id" => "11111111-1111-1111-1111-111111111111", "schedule" => array("disable" => false, "start_date" => null, "time_sensitive" => false), "ui_session_id" => "81ae275a0a97d1a09880801a533a0ff1"));
    request("/api/v1/scans", "POST", false, $payload);
}

function patchTarget($target_id)
{
    global $argv;
    $speed = "";
    $host = "";
    $port = "";
    $payload = "";
    foreach ($argv as $item) {
        if (strpos($item, "--speed") !== false) {
            $speed = explode("=", $item)['1'];
        }
        if (strpos($item, "--proxy") !== false) {
            $host = explode(":", explode("=", $item)['1'])[0];
            $port = explode(":", explode("=", $item)['1'])[1];
        }
    }
    switch ($speed) {
        case "1":
            $speed = "sequential";
            break;
        case "2":
            $speed = "slow";
            break;
        case "4":
            $speed = "fast";
            break;
        default:
            $speed = "moderate";
            break;
    }
    if ($host && $port) {
        $payload = json_encode(array("scan_speed" => $speed, "proxy" => array("enabled" => true, "protocol" => "http", "address" => "$host", "port" => "$port")));
    } else {
        if ($speed === "fast") {
            return;
        } else {
            $payload = json_encode(array("scan_speed" => $speed));
        }
    }
    request("/api/v1/targets/$target_id/configuration", "PATCH", false, $payload);
}

function delTarget($target_id)
{
    request("/api/v1/targets/$target_id", "DELETE", false, "");
}

showLogo();
if ($mode === "-u") {
    if (isset($target)) {
        $target_id = addTarget($target);
        patchTarget($target_id);
        scanTarget($target_id);
        print_r("Done!");
    }
} else if ($mode === "-f") {
    if (isset($target)) {
        $urlLists = preg_split('/\n|\r\n?/', file_get_contents($target));
        foreach ($urlLists as $url) {
            if ($url !== "") {
                $target_id = addTarget($url);
                patchTarget($target_id);
                scanTarget($target_id);
            }
        }
        print_r("Done!");
    } else {
        print_r("[*]Usage: php $filename -h");
    }
} else if ($mode === "-d") {
    while (true) {
        $targetLists = getTargets();
        if ($targetLists) {
            foreach ($targetLists as $target) {
                $address = $target['address'];
                print_r("Delete target $address\n");
                delTarget($target['target_id']);
            }
        } else {
            break;
        }
    }
    print_r("Done!");
} else if ($mode === "-h") {
    print_r("
MODE
    -u URL
        Scan with single target.
    -f File
        Scan with target list.
    -d
        Delete all targets.
    
OPTIONS
    --speed=speed
        Specify scan speed, 1(sequential) 2(slow) 3(moderate) 4(fast), default is 3.
    --proxy=host:port
        Specify scan proxy.

EXAMPLE
    php $filename -u example.com --speed=2
    php $filename -f domains.txt --speed=1 --proxy=127.0.0.1:9999
    php $filename -d
");
} else {
    print_r("[*]Usage: php $filename -h");
}