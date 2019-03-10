<?php
/**
 * Created by Harry Liu.
 * Date: 2019/3/5
 * Time: 14:20
 * Email: L3478830@163.com
 */

require './vendor/autoload.php';
require 'comm.php';
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use mikehaertl\shellcommand\Command;
/*
 *  info message
 */
if (isGet()){
    echo "<H1 style='text-align: center'>HUB Bot</H1>";
    echo '<hr style="height:1px;margin-top:30px;border:none;border-top:2px ridge #dfe2e4;" \/>';
    echo "<div style='text-align: center;margin-top:30px;'>
                  <span>
                        <a style='text-decoration:none;color: #2f2f2f;' href='https://github.com/ser163/hubbot'>GitHUB</a>
                  </span>
            <div>";
    echo "<div style='text-align: center;margin-top:2px;'>
                  <span>
                        <a style='text-decoration:none;color: #2f2f2f;' href='https://www.ser163.cn'>ser163</a>
                  </span>
            <div>";
    exit();
}
// create a log channel
$logfile=env('logfile','bot.log');
$log = new Logger('name');
$log->pushHandler(new StreamHandler($logfile, Logger::INFO));

//获取配置文件
$secret_key=env('secret_key','1522');
//config目录路径
$def_config_dir=dirname(__FILE__).DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR;
$configpath=env('config_dir',$def_config_dir);
//检查目录是否可以读写
if(!file_exists($configpath)){
    $log->addError($configpath.' Directory does not exist');
    die($configpath.' Directory does not exist');
}
if(!is_readable($configpath)){
    $log->addError($configpath.' This directory requires read permissions');
    die($configpath.' This directory requires read permissions');
}
//template目录路径
$def_template_dir=dirname(__FILE__).DIRECTORY_SEPARATOR.'template'.DIRECTORY_SEPARATOR;
$templatepath=env('template_dir',$def_template_dir);

if(!file_exists($templatepath)){
    $log->addError($templatepath.' Directory does not exist');
    die($templatepath.' Directory does not exist');
}
if(!is_readable($templatepath)){
    $log->addError($templatepath.' This directory requires read permissions');
    die($templatepath.' This directory requires read permissions');
}

//cmd目录路径 此目录要有可写权限
$def_cmd_dir=dirname(__FILE__).DIRECTORY_SEPARATOR.'cmd'.DIRECTORY_SEPARATOR;
$cmdpath=env('cmd_dir',$def_cmd_dir);

//检查cmd目录权限
if(!is_writable($cmdpath) ){
    $log->addError($cmdpath.' No writable permissions');
    die($cmdpath.' No writable permissions');
}
$mine = $_SERVER['CONTENT_TYPE'];
$log->addInfo('mine type:'.$mine);
//获取github push过来的json数据
if($mine=="application/x-www-form-urlencoded"){
    $poststr = json_encode($_POST["payload"]);
}else{
    $poststr = file_get_contents('php://input');
}
$jsonstr = json_decode($poststr, true);
if (empty($jsonstr)){
    $log->addInfo('Json String Abnormal data');
    exit();
}
$log->addInfo('------------------------------------------------');
$log->addInfo(json_encode($jsonstr));
//获取资源库名字
$repo_name=$jsonstr['repository']['full_name'];
$log->addInfo('repo_name:'.$repo_name);
//获取加密
$git_secret = $_SERVER['HTTP_X_HUB_SIGNATURE'];
$log->addInfo('hash串为:'.$git_secret);
if (!$git_secret) {
    return http_response_code(403);
}
//获取签名
list($algo, $hash) = explode('=', $git_secret, 2);
$hashstr = hash_hmac($algo, $poststr, $secret_key);
$log->addInfo('hashstr:'.$hashstr);
$log->addInfo('hash:'.$hash);

if ($hash === $hashstr) {
    //发送json到服务5750端口
    $ser_mode =env('mode','singe');
    $silence =env('silence','true');
    //非沉默模式将执行
    if(!$silence){
        if (!in_array($ser_mode,['singe','clan']) ){
            $log->addError('mode only:singe,clan');
            echo 'mode only:singe,clan!!!';
            return http_response_code(400);
        }
        if ($ser_mode=='singe'){
            $server_addr=env('boot_server','127.0.0.1');
            $server_port=env('boot_server_port',5750);
            $ret=send_json($server_addr,$server_port,$poststr);
            if ($ret){
                $log->addInfo('send_boot_ok');
                echo 'send_boot_ok';
            }else{
                $log->addError('send_boot_fail');
                echo 'send_boot_fail';
            }
        }else{
            //获取族群服务器数量
            $clans=env('clans_count',2);
            //族群数量必须大于2
            if ($clans >= 2){
                $clans_list=clans_get_list($count);
                //循环发送到对应服务器
                for($i = 0; $i < count($clans_list); $i++) {
                    //判断是否配置定义对应库
                    if ($clans_list[$i]['repo']==$repo_name){
                        $server_addr=$clans_list[$i]['server'];
                        $server_port=$clans_list[$i]['port'];
                        $ret=send_json($server_addr,$server_port,$poststr);
                        if ($ret){
                            $log->addInfo('send_boot_ok');
                            echo 'send_boot_ok';
                        }else{
                            $log->addError('send_boot_fail');
                            echo 'send_boot_fail';
                        }
                    }
                }
            }else{
                $log->addError('The number of clans must be greater than 2');
                echo 'The number of clans must be greater than 2 !!!';
                return http_response_code(400);
            }
        }
    }else{
        //沉默模式下,本机不向外界通讯,自己单独执行命令
        $jsonfile=$configpath.str_replace('/','_',$repo_name).'.json';
        if(!file_exists($jsonfile)){
            $log->addError($jsonfile.' File Not Found:');
            echo "Error:".$jsonfile.' File Not Found';
            exit();
        }
        $jsonset=json_decode(file_get_contents($jsonfile));
        //获取是否使用模版
        $script_temple=$jsonset->script->temple;
        //获取是否使用模版
        $script_tpl_file=$jsonset->script->tpl;
        //获取脚本命令
        $script_cmd=$jsonset->script->command;
        //获取命令参数
        $script_arg=$jsonset->script->arg;
        $cwd = null;
        //启用模版
        if($script_temple){
            //渲染模版
            $render_str=render($templatepath,$script_tpl_file,$jsonstr);
            $log->addInfo('render:'.$render_str);
            if(!strstr($script_cmd,DIRECTORY_SEPARATOR)){
                $script_cmd = $cmdpath.$script_cmd;
                $log->addInfo('sciript:'.$script_cmd);
                $cwd = $cmdpath;
            }
            //写入渲染的脚本
            $log->addInfo('sciript begin write .');
            write_cmd($script_cmd,$render_str);
            $log->addInfo('sciript write end.');
        }
        //执行自定义的命令
        if(is_null($cwd)){
            $command = new Command($script_cmd);
        }else{
            $command = new Command(
                array(
                    'command' => $script_cmd,
                    'procCwd' =>$cwd
                )
            );
        }
        $log->addInfo('run cmd.'.$script_cmd);
        $command->addArg($script_arg, null, false);
        $log->addInfo('begin run command.'.$script_cmd);
        if ($command->execute()) {
            $log->addInfo('command run');
            echo $command->getOutput();
        } else {
            echo $command->getError();
            $exitCode = $command->getExitCode();
        }
    }
}else{
    echo 'fail';
}

