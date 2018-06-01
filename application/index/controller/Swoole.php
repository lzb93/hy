<?php

namespace app\index\controller;

use think\console\Command;
use app\index\service\SwooleSerService;
use think\Config;

class Swoole extends Command {

    protected $setting = [];

    protected function configure() {
        $this->addOption('type', 't', \think\console\input\Option::VALUE_REQUIRED);
        $this->setName('Swoole')->setDescription('Command Swoole');
    }

    protected function prepareSettings() {
        $runtimePath = __DIR__ . '/../../../swoole-async';
        $this->settings = [
            'host' => Config::get('swoole_host'),
            'port' => Config::get('swoole_port'),
            'process_name' => 'swooleServ',
            'open_tcp_nodelay' => '1',
            'daemonize' => 2,
            'buffer_output_size' => 32 * 1024 *1024,
            'socket_buffer_size' => 32 * 1024 *1024,
            'dispatch_mode' => 2,
            'worker_num' => Config::get('swoole_worker_num'),
            'task_worker_num' => Config::get('swoole_task_worker_num'),
            'task_max_request' => '0',
            'pidfile' => $runtimePath . '/swoole-async.pid',
            'log_dir' => $runtimePath . '/log',
            'task_tmpdir' => $runtimePath . '/task',
            'log_file' => $runtimePath . '/log/http.log',
            'log_size' => 204800000,
            'client_timeout' => 5
        ];
    }

    protected function execute(\think\console\Input $input, \think\console\Output $output) {
        $this->prepareSettings();
        $swooleService = new SwooleSerService($this->settings);
        $options = $input->getOptions();
        switch ($options['type']) {
            case 'start':
                $swooleService->serviceStart();
                break;
            case 'restart':
                $swooleService->serviceStop();
                $swooleService->serviceStart();
                break;
            case 'stop':
                $swooleService->serviceStop();
                break;
            case 'stats':
                $swooleService->serviceStats();
                break;
            case 'list':
                $swooleService->serviceList();
                break;
            default:
                exit('error:参数错误');
                break;
        }
    }

}
