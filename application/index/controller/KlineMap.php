<?php

namespace app\index\controller;

use think\console\Command;
use think\Db;
use think\Config;
use app\index\service\BitDataService;

class KlineMap extends Command {

    protected function configure() {
        $this->setName('KlineMap')->setDescription('Command KlineMap');
    }

    protected function execute(\think\console\Input $input, \think\console\Output $output) {
        $productList = Db::name('productinfo')->alias('pi')
                        ->join('__PRODUCTDATA__ pd', 'pd.pid=pi.pid')->select();
        $num = 60;
        $intervals = ['1', '5', '15', '30', '60', 'd'];
        // 接口待后期封装成服务
        foreach ($productList as $pro) {
            foreach ($intervals as $interval) {
                $nowtime = time() . rand(100, 999);
                if ($pro['procode'] == 'BTCCNY') { // 新比特币
                    $dataLenArr = [
                        '1' => '1m',
                        '5' => '5m',
                        '15' => '15m',
                        '30' => '30m',
                        '60' => '1h',
                        'd' => '1d',
                    ];
                    $res_arr = BitDataService::getKData($dataLenArr[$interval]);
                    if (empty($res_arr)) {
                        echo $pro['pid'] . '_' . $interval . '获取不到' . "\n";
                        continue;
                    }
                }

                if ($pro['procode'] == 'llg') {
                    if ($interval == 'd')
                        $i = 1440;
                    else 
                        $i = $interval;
                    $geturl = "https://hq.91pme.com/query/kline?callback=jQuery183014447531082730047_" . $nowtime . "&code=" . $pro['procode'] . "&level=" . $i . "&maxrecords=" . $num . "&_=" . $nowtime;

                    $html = $this->curlfun($geturl);
                    $returnJson = substr($html, 42, -1);
                    $dataArray = json_decode($returnJson, true);
                    if (empty($dataArray)) {
                        echo $pro['pid'] . '_' . $interval . '获取不到' . "\n";
                        continue;
                    }
                    foreach ($dataArray['value'] as $k => $v) {
                        $time = substr($v['time'], 0, -3);
                        $tmp_arr[$time] = [
                            $time,
                            $v['open'],
                            $v['close'],
                            $v['high'],
                            $v['low']
                        ];
                    }
                    ksort($tmp_arr);
                    $res_arr = array_values($tmp_arr);
                    unset($tmp_arr);
                }

                if (in_array($pro['procode'], ['fx_sgbpcad', 'fx_sgbpaud', 'fx_seurgbp', 'fx_saudcad', 'fx_sgbpusd', 'fx_seurusd'])) {
                    $dataLenArr = [
                        '1' => 1440,
                        '5' => 1440,
                        '15' => 480,
                        '30' => 240,
                        '60' => 120,
                        'd' => '',
                    ];
                    $year = date('Y_n_j', time());
                    if ($interval == 'd') {
                        $geturl = "http://vip.stock.finance.sina.com.cn/forex/api/jsonp.php/var%20_" . $pro['procode'] . "$year=/NewForexService.getDayKLine?symbol=" . $pro['procode'] . "&_=$year";
                    } else {
                        $geturl = "http://vip.stock.finance.sina.com.cn/forex/api/jsonp.php/var%20_" . $pro['procode'] . "_" . $interval . "_$nowtime=/NewForexService.getMinKline?symbol=" . $pro['procode'] . "&scale=" . $interval . "&datalen=" . $dataLenArr[$interval];
                    }
                    $html = $this->curlfun($geturl);

                    if ($interval == 'd') {
                        $returnSting = substr($html, 94, -4);
                        $tmpArr = explode('|', $returnSting);
                        if (empty($tmpArr)) {
                            echo $pro['pid'] . '_' . $interval . '获取不到' . "\n";
                            continue;
                        }
                        $newArr = [];
                        foreach ($tmpArr as $arr) {
                            $a = explode(',', $arr);
                            $newArr[$a['0']] = [$a['0'], $a['1'], $a['4'], $a['2'], $a['3']];
                        }
                        ksort($newArr);
                        $res_arr = array_values(array_slice($newArr, -$num));
                    } else {
                        $returnJson = substr($html, (63 + strlen($pro['procode'] . '_' . $interval . '_' . $nowtime)), -1);
                        $json = preg_replace('/([a-z]+)/is', '"$1"', $returnJson);
                        $tmpArr = json_decode($json, true);
                        if (empty($tmpArr)) {
                            echo $pro['pid'] . '_' . $interval . '获取不到' . "\n";
                            continue;
                        }
                        $newArr = [];
                        foreach ($tmpArr as $a) {
                            $a['d'] = strtotime($a['d']);
                            $newArr[$a['d']] = [$a['d'], $a['o'], $a['c'], $a['h'], $a['l']];
                        }
                        ksort($newArr);
                        $res_arr = array_values(array_slice($newArr, -$num));
                    }
                }
                if ($pro['Price'] < end($res_arr)[1]) {
                    $_state = 'down';
                } else {
                    $_state = 'up';
                }
                $all_data['topdata'] = array(
                    'topdata' => $pro['UpdateTime'],
                    'now' => $pro['Price'],
                    'open' => $pro['Open'],
                    'lowest' => $pro['Low'],
                    'highest' => $pro['High'],
                    'close' => $pro['Close'],
                    'state' => $_state
                );
                $all_data['items'] = $res_arr;
                $r = json_encode($all_data);
                $redis = new \Redis();
                $redis->connect(Config::get('cache')['redis']['host'], '6379');
                $redis->auth(Config::get('cache')['redis']['password']);
                $redis->select(0); // 选择2数据库
                $redis->set('klinemap_' . $pro['pid'] . '_' . $interval, $r);
            }
        }
    }

    public function curlfun($url, $params = array(), $method = 'GET') {

        $header = array();
        $opts = array(CURLOPT_TIMEOUT => 10, CURLOPT_RETURNTRANSFER => 1, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false, CURLOPT_HTTPHEADER => $header);

        /* 根据请求类型设置特定参数 */
        switch (strtoupper($method)) {
            case 'GET' :
                $opts[CURLOPT_URL] = $url . '?' . http_build_query($params);
                $opts[CURLOPT_URL] = substr($opts[CURLOPT_URL], 0, -1);

                break;
            case 'POST' :
                //判断是否传输文件
                $params = http_build_query($params);
                $opts[CURLOPT_URL] = $url;
                $opts[CURLOPT_POST] = 1;
                $opts[CURLOPT_POSTFIELDS] = $params;
                break;
            default :
        }

        /* 初始化并执行curl请求 */
        $ch = curl_init();
        curl_setopt_array($ch, $opts);
        $data = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $data = null;
        }
        return $data;
    }

}
