<?php
$_redis_config = array(
	'socket_type' => 'tcp',
	'host' => '127.0.0.1',
	'password' => '', 
	'port' => 6379,
	'timeout' => 3
);

function getRedisConnect($config, $persistent = false){
	$redis = new Redis();
	if(!$persistent){
		$success = $redis->connect($config['host'], $config['port'], $config['timeout']);
	} else {
		$success = $redis->pconnect($config['host'], $config['port'] );
	}
	if ( ! $success) {
		throw new RuntimeException('Cache: Redis connection failed. Check your configuration.');
	}
	if (isset($config['password']) && ! $redis->auth($config['password'])) {
		throw new RuntimeException('Cache: Redis authentication failed.');
	}
	return $redis;
}


?>
