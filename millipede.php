<?php
//监听redis队列的变化并分发任务
set_time_limit(0);

ini_set('default_socket_timeout', -1);

set_error_handler('error_handle', E_ALL);

register_shutdown_function('shut_down');

queue_logger('./logs/'.date("Ymd").'.log', 'millipede queue start...');

$mode = isset($argv[1]) ? $argv[1] : '';

//定义根目录
define("ROOT", dirname(__FILE__)."/");  

//错误日志

//队列名称
define('QUEUE_KEY', 'millipede:queue');

define('QUEUE_KEY_SECURE', 'millipede:queue:secure');

require './inc/constants.php';

require './inc/redis.php';

require './inc/mysql.php';

require './inc/memcached.php';

//redis
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
		$queue_data = json_decode($rs);
		if(!is_object($queue_data)){
			continue;
		}
		//worker的类名
		$worker = isset($queue_data->worker) ? $queue_data->worker : '';
		//方法名称
		$method = isset($queue_data->method) ? $queue_data->method : '';
		//数据
		$data = isset($queue_data->data) ? $queue_data->data : array();
		if($worker == '' || $method == ''){
			continue;
		}

		$worker_class = load_class($worker);
		if(!$worker_class){
			queue_logger('./logs/'.date("Ymd").'.log', 'Worker:'.$worker.' not exist');
			continue;
		}

		if(method_exists($worker_class, $method)){
			if($worker_class->$method($data)){
				$redis->lrem(QUEUE_KEY_SECURE, $rs, 1);
			}
		} else {
			queue_logger('./logs/'.date("Ymd").'.log', 'Worker:'.$worker.' has not method:'.$method);
		}
		queue_logger('./logs/'.date("Ymd").'.log', $queue_data);
	}
}

//日志记录器
function queue_logger($log_file, $queue_data){
	$data = array(
		date('Y-m-d H:i:s'),	
		json_encode($queue_data),
	);
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

