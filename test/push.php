<?php

require dirname(__FILE__).'/../inc/redis.php';

$redis = getRedisConnect($_redis_config);

define('QUEUE_KEY', 'millipede:queue');

$queue_data = array(
	'worker' => 'test',
	'method' => 'test',
	'from' => 'test',
	'data' => array(
		'do' => 'test',
		'time' => date('Y-m-d H:i:s')
	)
);

if($redis->lpush(QUEUE_KEY, json_encode($queue_data))) {
	echo '消息推送成功...';
}


