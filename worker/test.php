<?php
class Test extends Worker{
	public function __construct(){
		parent::__construct();
	}

	//测试
	public function test($data){
		$do = isset($data->do) ? $data->do: 'null';
		$time = isset($data->time) ? $data->time: 'null';
		$this->logger(__CLASS__, __METHOD__ , '['.$do.':'.$time.']');

		//返回true必须加
		return true;
	}
}

