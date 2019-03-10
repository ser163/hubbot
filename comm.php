<?php
/**
 * Created by Harry Liu.
 * Date: 2019/3/5
 * Time: 16:55
 * Email: L3478830@163.com
 */
//加在配置文件
$dotenv = Dotenv\Dotenv::create(__DIR__);
$dotenv->load();

//定义加密iv
define ("IV",getenv('iv'));
define ('CIPHER',getenv('cipher'));

function isGet(){
    return $_SERVER['REQUEST_METHOD'] == 'GET' ? true : false;
}

/**
 * Return the length of the given string.
 *
 * @param  string  $value
 * @return int
 */
function length($value)
{
    return mb_strlen($value);
}

/**
 * Returns the portion of string specified by the start and length parameters.
 *
 * @param  string  $string
 * @param  int  $start
 * @param  int|null  $length
 * @return string
 */
function substr_new($string, $start, $length = null)
{
    return mb_substr($string, $start, $length, 'UTF-8');
}

/**
 * Determine if a given string starts with a given substring.
 *
 * @param  string  $haystack
 * @param  string|array  $needles
 * @return bool
 */
function startsWith($haystack, $needles)
{
    foreach ((array) $needles as $needle) {
        if ($needle != '' && mb_strpos($haystack, $needle) === 0) {
            return true;
        }
    }

    return false;
}

/**
 * Determine if a given string ends with a given substring.
 *
 * @param  string  $haystack
 * @param  string|array  $needles
 * @return bool
 */
function endsWith($haystack, $needles)
{
    foreach ((array) $needles as $needle) {
        if ((string) $needle === substr_new($haystack, -length($needle))) {
            return true;
        }
    }

    return false;
}

if (! function_exists('value')) {
    /**
     * Return the default value of the given value.
     *
     * @param  mixed  $value
     * @return mixed
     */
    function value($value)
    {
        return $value instanceof Closure ? $value() : $value;
    }
}

if (! function_exists('env')) {
    /**
     * Gets the value of an environment variable. Supports boolean, empty and null.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    function env($key, $default = null)
    {
        $value = getenv($key);
        if ($value === false) {
            return value($default);
        }
        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return;
        }
        if (startsWith($value, '"') && endsWith($value, '"')) {
            return substr($value, 1, -1);
        }
        return $value;
    }
}
/*
 * 加密函数
 */
function ssl_encry($plaintext,$key='5ae1b8a17bad4da4fdac796f64c16ecd'){
    if (in_array(CIPHER, openssl_get_cipher_methods()))
    {
        $ciphertext = openssl_encrypt($plaintext, CIPHER, $key, $options=OPENSSL_RAW_DATA,IV);
        return base64_encode($ciphertext);
    }
}
/*
 * 解密函数
 */
function ssl_decri($encstr,$key='5ae1b8a17bad4da4fdac796f64c16ecd'){
    $de64_str=base64_decode($encstr);
    if (in_array(CIPHER, openssl_get_cipher_methods())) {
        $output = openssl_decrypt($de64_str, CIPHER, $key, $options=OPENSSL_RAW_DATA,$iv=IV);
        return $output;
    }
}
/*
 *  发送数据到bootServer
 */
function send_json($server,$port,$json_str){
    $client = new swoole_client(SWOOLE_SOCK_TCP);
    $env_key=env('aes_key','5ae1b8a17bad4da4fdac796f64c16ecd');
    $secret_key=env('secret_key','1522');
    if (!$client->connect($server, $port, -1))
    {
        exit("connect failed. Error: {$client->errCode}\n");
    }
    $return_str=$client->recv();
    if (ssl_decri($return_str)==$secret_key){
        $client->send(ssl_encry($json_str,$env_key));
        $client->close();
        return true;
    }else{
        $client->close();
        return false;
    }
}

/*
 *  获取clans 服务器的配置
 */
function clans_get_list($count){
    $clan_list=[];
    for ($clan=0; $clan<= (int)$count - 1; $clan++){
        $repo = env('clans_repo_name_'.strval($clan));
        $server_name=env('clans_server_'.strval($clan),'127.0.0.1');
        $port =env('clans_server_port_'.strval($clan),5750);
        $clans = [
            "index" => $clan,
            "repo" => $repo,
            "server" => $server_name,
            "port" => $port
        ];
        array_push($clan_list,$clans);
    }
    return $clan_list;
}
/*
 *  渲染模版
 */
function render($tplpath,$tplfile,$val){
    $loader = new \Twig\Loader\FilesystemLoader($tplpath);
    $twig = new \Twig\Environment($loader);
    return $twig->render($tplfile, ["github"=>$val]);
}

/*
 *  写入文件,并设置权限
 */

function write_cmd($filename,$val){
    $cmdfilefd = fopen($filename, "w") or die($filename." Cannot write to file!");
    fwrite($cmdfilefd,$val);
    fclose($cmdfilefd);
    chmod($filename,0755);
}