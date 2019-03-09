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

//获取配置文件
$secret_key=env('secret_key','1522');
$logfile=env('logfile','bot.log');

//config目录路径
$def_config_dir=dirname(__FILE__).DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR;
$configpath=env('config_dir',$def_config_dir);

//template目录路径
$def_template_dir=dirname(__FILE__).DIRECTORY_SEPARATOR.'template'.DIRECTORY_SEPARATOR;
$templatepath=env('template_dir',$def_template_dir);

//cmd目录路径
$def_cmd_dir=dirname(__FILE__).DIRECTORY_SEPARATOR.'cmd'.DIRECTORY_SEPARATOR;
$cmdpath=env('cmd_dir',$def_cmd_dir);

// create a log channel
$log = new Logger('name');
$log->pushHandler(new StreamHandler($logfile, Logger::INFO));
//获取github push过来的json数据
$poststr = file_get_contents('php://input');
$jsonstr = json_decode($poststr, true);
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
        $jsonfile=$configpath.str_repeat('/','_',$repo_name).'.json';
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
        //启用模版
        if($script_temple){
            //渲染模版
            $render_str=render($templatepath,$script_tpl_file,$jsonstr);
            if(!strstr($script_cmd,DIRECTORY_SEPARATOR)){
                $script_cmd = $cmdpath.$script_cmd;
            }
            //写入渲染的脚本
            write_cmd($script_cmd,$render_str);
        }
        //执行自定义的命令
        $command = new Command($script_cmd);
        $command->addArg($script_arg, null, false);
        if ($command->execute()) {
            echo $command->getOutput();
        } else {
            echo $command->getError();
            $exitCode = $command->getExitCode();
        }
    }
}else{
    echo 'fail';
}

