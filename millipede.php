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

$mode = isset($argv[1]) ? $argv[1] : '';

//定义根目录
define("ROOT", dirname(__FILE__)."/");  


//主队列
define('QUEUE_KEY', 'millipede:queue');

//安全队列：异常中断时候保存未完成的队列信息，重启队列后写回到主队列
define('QUEUE_KEY_SECURE', 'millipede:queue:secure');

//读取worker的注册信息
$worker_register = require './inc/register.php';

require './inc/constants.php';

require './inc/redis.php';

require './inc/mysql.php';

require './inc/memcached.php';

//worker的父类
require './worker.php';

//redis长连接
$GLOBALS['redis'] = $redis = getRedisConnect($_redis_config, true);

$GLOBALS['worker_list'] = array();

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

		//worker的类名
		$worker = isset($queue_data->worker) ? $queue_data->worker : '';
		//方法名称
		$method = isset($queue_data->method) ? $queue_data->method : '';
		//数据
		$data = isset($queue_data->data) ? $queue_data->data : array();
		if($worker == '' || $method == ''){
			continue;
		}

		$is_error = 0;

		//判断worker类是否注册
		if(!array_key_exists($worker, $worker_register)) {
			$is_error = 1;
			queue_logger($log_file, 'worker:'.$worker.' has not registered');
		} else {
			//判断worker的方法是否已经注册
			if(!array_key_exists($method, $worker_register[$worker])){
				$is_error = 1;
				queue_logger($log_file, 'method:'.$worker.'->'.$method.' has not registered');
			} else {
				$worker_class = load_class($worker);
				if(!$worker_class){
					$is_error = 1;
					queue_logger($log_file, 'worker:'.$worker.' not exist');
				} else {
					if(!method_exists($worker_class, $method)){
						$is_error = 1;
						queue_logger($log_file, 'method:'.$worker.'->'.$method.' not exist');
					}
				}
			}
		}

		if($is_error == 1){
			if($redis->lrem(QUEUE_KEY_SECURE, $rs, 1)) {
				queue_logger($log_file, 'remove:'.$rs);
			}
			continue;
		}


		if($worker_class->$method($data)){
			$redis->lrem(QUEUE_KEY_SECURE, $rs, 1);
			queue_logger($log_file, 'job complete:'.$rs);
		} else {
			//没有完成任务，可能是方法异常，也可能是没有返回true
			queue_logger($log_file, 'job not complete:'.$rs);
		}
	}
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

function shut_down(){
	$error = error_get_last();
	queue_logger('./logs/'.date("Ymd").'.log', 'millipede queue shutdown...'.json_encode($error));
		
}

