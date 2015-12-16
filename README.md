# php-light-queue 简易队列
php-light-queue是用redis的list和php实现的简易阻塞消息队列，主要特点是简单和方便。

##主要文件和目录说明
1. millipede.php 队列client的主逻辑文件
2. millipede_run.sh 启动脚本
3. monitor.sh 进程监控脚本，建议加到crontab里: */5 * * * * cd ./millipede/ && sh monitor.sh;
4. inc/ 常用配置
5. inc/register.php worker的注册配置文件
6. worker/ 保存worker文件
7. worker/cli/ 存放外部脚本
