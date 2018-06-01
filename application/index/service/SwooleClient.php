<?php

namespace app\index\service;

use think\Config;

class SwooleClient {

    private $client;

    public function __construct() {
        $this->client = new \swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);
    }

    public function connect($data = null) {
        $dataString = json_encode($data);
        //注册连接成功回调
        $this->client->on("connect", function($cli) use ($dataString){
            echo "connect_success\n";
            $cli->send($dataString);
            echo socket_strerror($cli->errCode) . "send status\n";
        });

        //注册数据接收回调
        $this->client->on("receive", function($cli, $data) {
            $cli->close();
            echo "receive_success\n";
        });

        //注册连接失败回调
        $this->client->on("error", function($cli) {
            echo socket_strerror($cli->errCode) . "Connect failed\n";
//            echo socket_strerror($cli->errCode) . "Connect failed\n";
        });

        //注册连接关闭回调
        $this->client->on("close", function($cli) {
            echo "Connection close\n";
        });
        $this->client->connect(Config::get('swoole_host'), Config::get('swoole_port'), 1); // Config::get('swoole_port')
    }

    public function close() {
        $this->client->close();
    }

}
