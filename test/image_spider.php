<?php
//从网页获取图片路径，push到redis的队列
$page_url = 'http://www.moko.cc/moko/post/1.html';

$str = file_get_contents($page_url);

preg_match_all('/src2="(http:\/\/.*jpg)" alt/', $str, $match);

require dirname(__FILE__).'/../inc/redis.php';

$redis = getRedisConnect($_redis_config);

define('QUEUE_KEY', 'millipede:queue:0');

foreach($match[1] as $pic_url){
	$queue_data = array(
		'type' => 'cli',
		'worker' => 'picspider',
		'from' => 'test',
		'data' => array(
			'picurl' => $pic_url,
			'time' => date('Y-m-d H:i:s')
		)
	);

	if($redis->lpush(QUEUE_KEY, json_encode($queue_data))) {
		echo '消息推送成功...',PHP_EOL;
	}
}


