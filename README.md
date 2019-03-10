# HUB Bot #



HUB Bot 是一个Github Webhook 转发程序,它是用[PHP](http://www.php.net/)编写.

* 部署方便,搭建好php环境,使用composer安装就能使用.
* 配置简单,简单配置即可以使用.
* 方便扩展,可以搭配[Boot Server](https://github.com/ser163/bootserver).便可随意扩展.
* GitHub 多仓库复用,只需要增加配置即可.

Hub Bot是Github Webhook的集中处理接口.

## 快速安装 ##

### 安装 ###

	git clone https://github.com/ser163/hubbot.git

### 安装依赖库 ###

	composer install
### 设置github的Webhook ###
	点击`Settings=>Webhooks=>Add webhook `进行添加hubbot.
	`Payload URL`中输入你的hook url. 如:`https://www
	.XXX.com/hubbot.php`(这里填写你的服务器路径.)
	`Secret`填写你的密匙.这个密匙要写在hubbot的配置文件中,请牢记它.

### 修改.env配置 ###

	cp .env.example .env
	vim .env
	secret_key=XXXXX (这里填写github的Secret)

### 修改config配置 ###

	cd config
	cp ser163_blog.json XXXX_ppp.json
	修改XXXX为你的用户名.ppp为你的仓库名称
```
{
  "script":{
	      "temple":true,
	      "tpl":"test.twh",
	      "command":"test.sh",
	      "arg":"arg1 arg2 arg3"
         }
}		
```
	`temple` 这个参数是启用模版,模版可以使用github传过来的变量.不使用模版,请设置为false.
	`tpl` 为模版的文件名,此文件在template下.`temple`为false时,此选项无效.
	`command` 这里填写模版生成的文件名,`temple`为false时,这里填写脚本路径如:/opt/bin/test.sh
	`arg` 为脚本执行参数,多个参数中间用空格分割.

### 修改模版 ###

	cd template
	vim XXX.twh (XXX为json参数中设置的文件名)

	{% set repname = github["repository"]["full_name"] %}
	echo %1 $1
	echo %2 $2
	echo {{ repname }}
	echo {{ github["repository"]["node_id"] }}

这里可以写具体的脚本命令,如果要引用github数据,请使用github数组.  
github为php中的关联数组,这个数组存储的就是github返回的hook数据.  
具体数值请参考 [github](https://developer.github.com/webhooks/#events)  
模版语法[twig](https://twig.symfony.com/),请按照twig语法进行模版编写.  
比如获取仓库名称:`github["repository"]["full_name"]`  
或者获取分支名称:`github['ref']`将得到`refs/heads/dev` dev就是分支名称.  

### 提交版本库 ###
    以上设置完毕,可以提交版本库.就可以执行命令了.

### 查看是否执行成功 ###

	通过github仓库的Settings=>Webhooks=>(https://xxx.com/hubbot.php)=>Recent Deliveries=>Response
	可以看到sh命令的执行反馈结果.

希望你使用愉快,搭配[Boot Server](https://github.com/ser163/bootserver/)一起使用,效果更佳.

### 常见问题 ###
	* cmd 文件夹必须有可读写权限.请先设置权限.
	* 如果要与多台服务器联动,请与Boot Server一起使用.

## Others ##

* [HUB Bot文档](https://www.ser163.cn/doc)
* [快速设置HUB Bot](https://www.ser163.cn/doc/HubBot/starthubbot.html)
* <https://github.com/ser163/hubbot>
* Email: <l3478830@163.com>
* QQ:81212836

[1]: https://github.com/ser163/bootserver        "Boot Server"

