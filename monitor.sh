curdir=$(pwd)
pid=`ps -ef | grep $curdir | grep 'millipede.php' | grep -v 'grep' | awk '{print $2}'`

if [ "x"$pid == "x" ];then
	sh millipede_run.sh
fi
