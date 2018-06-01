<?php

namespace app\index\service;

use think\Config;
use app\index\service\OrderService;

class SwooleService {

    /**
     * swoole http-server 实例
     * @var null|swoole_http_server
     */
    private $server = null;

    /**
     * swoole 配置
     * @var array
     */
    private $setting = [];

    /**
     * @var array
     */
    private $app = null;

    /**
     * [__construct description]
     */
    public function __construct($setting) {
        $this->setting = $setting;
    }

    /**
     * 设置swoole进程名称
     */
    private function setProcessName($name) {
        if (function_exists('cli_set_process_title')) {
            @cli_set_process_title($name);
        } else {
            if (function_exists('swoole_set_process_name')) {
                @swoole_set_process_name($name);
            } else {
                trigger_error(__METHOD__ . " failed.require cli_set_process_title or swoole_set_process_name.");
            }
        }
    }

    /**
     * 运行服务
     */
    public function run() {
        $this->server = new \swoole_server($this->setting['host'], $this->setting['port']);
        $this->server->set($this->setting);
        //回调函数
        $call = [
            'start',
            'workerStart',
            'managerStart',
            'task',
            'finish',
            'receive',
            'connect',
            'close',
            'workerStop',
            'shutdown',
        ];
        //事件回调函数绑定
        foreach ($call as $v) {
            $m = 'on' . ucfirst($v);
            if (method_exists($this, $m)) {
                $this->server->on($v, [$this, $m]);
            }
        }
        echo "服务成功启动" . PHP_EOL;
        echo "服务运行名称:{$this->setting['process_name']}" . PHP_EOL;
        echo "服务运行端口:{$this->setting['host']}:{$this->setting['port']}" . PHP_EOL;
        return $this->server->start();
    }

    public function onConnect() {
         echo '[' . date('Y-m-d H:i:s') . "]\t swoole_http_server onConnect\n";
    }
    /**
     * [onStart description]
     */
    public function onStart($server) {
        echo '[' . date('Y-m-d H:i:s') . "]\t swoole_http_server master worker start\n";
        $this->setProcessName($server->setting['process_name'] . '-master');
        //记录进程id,脚本实现自动重启
        $pid = "{$this->server->master_pid}\n{$this->server->manager_pid}";
        file_put_contents($this->setting['pidfile'], $pid);
        return true;
    }

    /**
     * [onManagerStart description]
     */
    public function onManagerStart($server) {
        echo '[' . date('Y-m-d H:i:s') . "]\t swoole_http_server manager worker start\n";
        $this->setProcessName($server->setting['process_name'] . '-manager');
    }

    /**
     * [onShutdown description]
     */
    public function onClose() {
        echo '[' . date('Y-m-d H:i:s') . "]\t swoole_http_server shutdown\n";
    }

    /**
     * [onWorkerStart description]
     */
    public function onWorkerStart($server, $workerId) {
        if ($workerId == 4) {
            swoole_timer_tick('60000', function() {
                $cmd = 'cd ' . Config::get('absolute_path') . ' && php -f ' . Config::get('absolute_path') . '/think.php KlineMap';
                echo "执行K线图定时器=========开始\n";
                exec($cmd . " > /dev/null &");
                echo "执行K线图定时器=========结束\n";
            });
        }
        if ($workerId == 5) {
            swoole_timer_tick('1000', function() {
                $cmd = 'cd ' . Config::get('absolute_path') . ' && php -f ' . Config::get('absolute_path') . '/think.php KlineData';
                echo "执行K线时时数据定时器=========开始\n";
                exec($cmd . " > /dev/null &");
                echo "执行K线时时数据定时器=========结束\n";
            });
        }
//        if ($workerId == 6) {
//            swoole_timer_tick('1000', function() {
//                $cmd = 'cd ' . Config::get('absolute_path') . ' && php -f ' . Config::get('absolute_path') . '/think.php DoOrder';
//                echo "执行平仓定时器=========开始\n";
//                exec($cmd . " > /dev/null &");
//                echo "执行平仓定时器=========结束\n";
//            });
//        }
        if ($workerId == 7) {
            swoole_timer_tick('1000', function() {
                $cmd = 'cd ' . Config::get('absolute_path') . ' && php -f ' . Config::get('absolute_path') . '/think.php ShareOrder';
                echo "执行分润定时器=========开始\n";
                exec($cmd . " > /dev/null &");
                echo "执行分润定时器=========结束\n";
            });
        }
        echo "workstart-{$workerId}\n";
        if ($workerId >= $this->setting['worker_num']) {
            $this->setProcessName($server->setting['process_name'] . '-task');
        } else {
            $this->setProcessName($server->setting['process_name'] . '-event');
        }
    }

    /**
     * [onWorkerStop description]
     */
    public function onWorkerStop($server, $workerId) {
        echo '[' . date('Y-m-d H:i:s') . "]\t swoole_http_server[{$server->setting['process_name']}  worker:{$workerId} shutdown\n";
    }

     /**
      * 处理请求
      */
     public function onReceive($server, $fd, $from_id, $data){ 
         echo "service-receive\n";
         $orderData = json_decode($data, true);
         foreach ($orderData['order_list'] as $d) {
             $this->server->task(['order_list' => $d, 'pro_data' => $orderData['pro_data'], 'nowtime' => $orderData['nowtime']]); 
         }
         $server->send($fd, "OK\n");
     }

    /**
     * 任务处理
     */
    public function onTask($serv, $task_id, $from_id, $data) {
        echo '[' . date('Y-m-d H:i:s') . "]\t swoole_task[  task:{$task_id}  form_id:{$from_id} do \n";
        require_once ROOT_PATH . 'application/index/service/OrderService.php';
//        echo ROOT_PATH ."\n";
        OrderService::SettleAccounts($data);
        return true;
    }


    /**
     * 任务结束回调函数
     */
    public function onFinish($server, $taskId, $data) {
        echo "finishtask-ser\n";
        return true;
    }

    /**
     * 记录日志 日志文件名为当前年月（date("Y-m")）
     * @param  [type] $msg 日志内容 
     * @return [type]      [description]
     */
//    public function logger($msg, $logfile = '') {
//        if (empty($msg)) {
//            return;
//        }
//        if (!is_string($msg)) {
//            $msg = var_export($msg, true);
//        }
//        //日志内容
//        $msg = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
//        //日志文件大小
//        $maxSize = $this->setting['log_size'];
//        //日志文件位置
//        $file = $logfile ?: $this->setting['log_dir'] . "/" . date('Y-m') . ".log";
//        //切割日志
//        if (file_exists($file) && filesize($file) >= $maxSize) {
//            $bak = $file . '-' . time();
//            if (!rename($file, $bak)) {
//                error_log("rename file:{$file} to {$bak} failed", 3, $file);
//            }
//        }
//        error_log($msg, 3, $file);
//    }

}
