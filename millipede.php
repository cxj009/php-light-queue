<?php
/**
* @file millipede.php
* @brief 监听redis队列的变化并分发任务
* @author cxj009
* @version 1.0
* @date 2015-12-12
*/
set_time_limit(0);

//socket流的超时时间
ini_set('default_socket_timeout', -1);

set_error_handler('error_handle', E_ALL);

register_shutdown_function('shut_down');

queue_logger('./logs/'.date("Ymd").'.log', 'millipede queue start...');

$queue_id = isset($argv[1]) ? (int)$argv[1] : 0;

//定义根目录
define("ROOT", dirname(__FILE__)."/");  

//主队列
define('QUEUE_KEY', 'millipede:queue:'.$queue_id);

//安全队列：异常中断时候保存未完成的队列信息，重启队列后写回到主队列
define('QUEUE_KEY_SECURE', 'millipede:queue:secure:'.$queue_id);

//worker的注册信息
require './inc/register.php';
require './inc/constants.php';
require './inc/redis.php';
require './inc/mysql.php';
require './inc/memcached.php';
//worker的父类
require './worker.php';

//redis长连接
$GLOBALS['redis'] = $redis = getRedisConnect($_redis_config, true);

$GLOBALS['worker_list'] = array();

//记录队列ID
//$redis->hset('millipede:queue:ids', 'queue:'.$queue_id, 1);

$secure_data = $redis->lrange(QUEUE_KEY_SECURE, 0, -1);
//把之前异常的队列信息再次放入到队列中
if(is_array($secure_data)){
	foreach($secure_data as $value){
		$redis->rpoplpush(QUEUE_KEY_SECURE, QUEUE_KEY);
	}
}

//阻塞队列
while(true){
	$rs = $redis->brpoplpush(QUEUE_KEY, QUEUE_KEY_SECURE, 0);
	if($rs != '' ){
		//日志文件
		$log_file = './logs/'.date('Ymd').'.log';

		$queue_data = json_decode($rs);

		if(!is_object($queue_data)){
			//非法数据
			queue_logger($log_file, 'illegal queue data:'.$rs);
			continue;
		}

		queue_logger($log_file, 'job start:'.$rs);

		//worker的类型
		$type = isset($queue_data->type) ? $queue_data->type: 'normal';
		//worker的类名
		$worker = isset($queue_data->worker) ? $queue_data->worker : '';
		//方法名称
		$method = isset($queue_data->method) ? $queue_data->method : '';
		//数据
		$data = isset($queue_data->data) ? $queue_data->data : array();
		if($worker == ''){
			continue;
		}
		$is_error = 0;
		//调用外部PHP脚本
		if($type == 'cli'){
			if(!worker_cli_execute($worker, $data, $worker_cli_register, $log_file)){
				$is_error = 1;
			} else {
				$redis->lrem(QUEUE_KEY_SECURE, $rs, 1);
				queue_logger($log_file, 'cli job running:'.$rs);
			}
		} else {
			if($worker_class = worker_execute($worker, $method, $worker_register, $log_file)) {
				if($worker_class->$method($data)){
					$redis->lrem(QUEUE_KEY_SECURE, $rs, 1);
					queue_logger($log_file, 'job complete:'.$rs);
				} else {
					//没有完成任务，可能是方法异常，也可能是没有返回true
					$is_error = 1;
				}
			} else {
				$is_error = 1;
			}
		}
		if($is_error == 1) {
			queue_logger($log_file, 'error:job not complete:'.$rs);
		}
	}
}

function worker_cli_execute($worker, $data, $worker_cli_register, $log_file){
	if(!array_key_exists($worker, $worker_cli_register)) {
		queue_logger($log_file, 'cli_worker:'.$worker.' has not registered');
		return false;
	}
	$worker_file = './worker/cli/'.$worker.'.php';
	if(!file_exists($worker_file)){
		queue_logger($log_file, 'cli_worker:'.$worker.' file has not exist');
		return false;
	}

	//判断当前cli cmd进程数，如果大于指定最高值，则等待
	while(!get_cli_worker_count()){
		queue_logger('./logs/'.date("Ymd").'.log', 'proc too many...');
		sleep(1);
	}	

	$cmd = 'nohup php '.$worker_file.' \''.json_encode($data).'\'  > /dev/null 2>&1 &';
	exec($cmd);
	queue_logger($log_file, 'cli cmd:'.$cmd);
	return true;
}

function worker_execute($worker, $method, $worker_register, $log_file){
	//判断worker类是否注册
	if(!array_key_exists($worker, $worker_register)) {
		queue_logger($log_file, 'worker:'.$worker.' has not registered');
		return false;
	} 
	//判断worker的方法是否已经注册
	if(!array_key_exists($method, $worker_register[$worker])){
		queue_logger($log_file, 'method:'.$worker.'->'.$method.' has not registered');
		return false;
	}
	$worker_class = load_class($worker);
	if(!$worker_class){
		queue_logger($log_file, 'worker:'.$worker.' not exist');
		return false;
	}
	if(!method_exists($worker_class, $method)){
		queue_logger($log_file, 'method:'.$worker.'->'.$method.' not exist');
		return false;
	}
	return $worker_class;
}

//日志记录器
function queue_logger($log_file, $message){
	$data = array(date('Y-m-d H:i:s'), $message);
	$line = implode("\t", $data);
	file_put_contents($log_file, $line."\n", FILE_APPEND);
}

//自动加载worker类文件
function __autoload($classname){
	$file = './worker/'.strtolower($classname).'.php';
	if(file_exists($file)){
		require_once $file;
	}
}

//加载类
function load_class($classname){
	if(isset($GLOBALS['worker_list'][$classname])){
		return $GLOBALS['worker_list'][$classname];
	}
	if(!class_exists($classname)){
		return false;
	}
	$class = $GLOBALS['worker_list'][$classname] = new $classname();

	return $class;
}

function error_handle($errno, $errstr, $errfile, $errline){
	//错误日志
	$error_log_file = ROOT.'logs/php_error_log';
	$line = date('Y-m-d H:i:s')."\t";
 	$line .= 'Custom error: ['.$errno.']'. $errstr;
 	$line .= ' Error on line '.$errline.' in '.$errfile;
	file_put_contents($error_log_file, $line."\n", FILE_APPEND);
}

//获取CLI模式worker进程数
function get_cli_worker_count(){
	$num = 0;
	$cmd = 'ps -ef | grep -v "grep" | grep -r "worker/cli/.*.php" | wc -l';
	$num = exec($cmd);
	if($num >= MAX_CLI_WORKER_NUM){
		return false;
	}

	return true;
}

//进程终止
function shut_down(){
	$error = error_get_last();
	queue_logger('./logs/'.date("Ymd").'.log', 'millipede queue shutdown...'.json_encode($error));
		
}

