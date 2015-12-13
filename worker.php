<?php
/**
* @file worker.php
* @brief worker的父类
* @author cxj009
* @version 1.0
* @date 2015-12-12
*/
class Worker{

	protected static $db = false;

	protected static $redis = false;

	protected static $memcached = false;

	private $log_dir = './logs/';

	function __construct(){
		if($GLOBALS['db_config']){
			$db_config = $GLOBALS['db_config'];	
			self::$db = new mysqli($db_config['host'], $db_config['user'], $db_config['pswd'], $db_config['db']);
			self::$db->query('set names '.$db_config['charset'].';');
			$this->logger(__CLASS__, __METHOD__, 'db connect ..');
		}
		if($GLOBALS['redis']){
			self::$redis = $GLOBALS['redis'];
		}
		if($GLOBALS['memcached_config']){
			$memcached_config = $GLOBALS['memcached_config'];	
			//创建一个memcache对象
			self::$memcached = new Memcached;
			self::$memcached->addServer($memcached_config['host'], $memcached_config['port']) or die ("Could not connect"); 
			self::$memcached->setOption(Memcached::OPT_BINARY_PROTOCOL, true); //使用binary二进制协议
        		if($memcached_config['user'] && $memcached_config['password']){
				self::$memcached->setSaslAuthData($memcached_config['user'], $memcached_config['password']);
        		}
		}
	}	

	//日志记录
	public function logger($worker, $method='', $msg){
		$data = array(
			date('Y-m-d H:i:s'),
			$msg
		);
		$line = $worker."\t".$method."\t".implode("\t", $data)."\n";
		$logfile = $this->log_dir.'worker_'.date('Ymd').'.log';
		file_put_contents($logfile, $line, FILE_APPEND);
	}
}



