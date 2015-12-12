curdir=$(pwd)
pid=`ps -ef | grep $curdir | grep 'millipede.php' | grep -v 'grep' | awk '{print $2}'`
echo $pid
if [ $pid ];then
	kill -9 $pid
fi

php $curdir/millipede.php &
