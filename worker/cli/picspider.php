<?php
//图片抓取worker的简单示例
//通过命令行参数获取需要处理的JSON数据：$argv[1]
//获取数据后再做进一步的图片下载处理
//命令行示例: nohup php ./worker/cli/picspider.php '{"picurl":"http:\/\/img3.moko.cc\/users\/0\/16\/5025\/post\/e1\/img3_cover_11073889.jpg","time":"2015-12-16 01:36:42"}'  > /dev/null 2>&1 &
$pic_dir = dirname(__FILE__).'/pics/';
if(!is_dir($pic_dir)){
	mkdir($pic_dir);
}
$data = isset($argv[1]) ? json_decode($argv[1]) : object;

if(is_object($data)){
	$pic_url = $data->picurl;
	$time = $data->time;

	$str = file_get_contents($pic_url);
	$pic_file = $dir.$time.'_'.uniqid().'.jpg';
	$fp = fopen($pic_dir.$pic_file, 'w');
	fwrite($fp, $str);
}




