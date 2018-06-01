<?php

namespace app\index\controller;

use think\console\Command;
use think\Db;
use think\Config;
use app\index\service\BitDataService;

class KlineData extends Command {

    protected function configure() {
        $this->setName('KlineData')->setDescription('Command KlineData');
    }

    protected function execute(\think\console\Input $input, \think\console\Output $output) {
        //产品列表
        $pro =  Db::name('productinfo')->select();

        $nowtime = time();
        $_rand = rand(1, 900) / 100000;
        $thisdatas = array();

        foreach ($pro as $k => $v) {
            //验证休市 Q:后期放缓存，频率太高不宜读表
//            $isopen = ChickIlsOpen($v['pid']);
//            if (!$isopen) {
//                continue;
//            }
            //比特币
            if ($v['procode'] == 'BTCCNY') {   // 比特币
//                $api = 'api.huobipro.$com';
                $thisdata = BitDataService::getNowData();
                if ($thisdata == null) {
                    continue;
                }
                //外汇网：http://forex.cnfol.com/
            } elseif ($v['procode'] == 'llg') {
                $url = "https://www.91pme.com/marketdata/gethq?code=" . $v['procode'];
                $html = $this->curlfun($url);
                $arr = json_decode($html, 1);
                if (!isset($arr[0]))
                    continue;
                $data_arr = $arr[0];

                $thisdata['Price'] = $this->fengkong($data_arr['buy'], $v);
                $thisdata['Open'] = $data_arr['open'];
                $thisdata['Close'] = $data_arr['lastclose'];
                $thisdata['High'] = $data_arr['high'];
                $thisdata['Low'] = $data_arr['low'];
                $thisdata['Diff'] = 0;
                $thisdata['DiffRate'] = 0;
            }else {
                $url = "http://hq.sinajs.cn/rn=" . $nowtime . "list=" . $v['procode'];
                $getdata = $this->curlfun($url);
                $data_arr = explode(',', $getdata);
                if (!is_array($data_arr) || count($data_arr) != 18)
                    continue;
                $thisdata['Price'] = $data_arr[1];
                $thisdata['Open'] = $data_arr[5];
                $thisdata['Close'] = $data_arr[3];
                $thisdata['High'] = $data_arr[6];
                $thisdata['Low'] = $data_arr[7];
                $thisdata['Diff'] = $data_arr[12];
                $thisdata['DiffRate'] = $data_arr[4] / 10000;
            }

            $thisdata['Name'] = $v['ptitle'];
            $thisdata['UpdateTime'] = $nowtime;
            $thisdata['pid'] = $v['pid'];

            $thisdatas[$v['pid']] = $thisdata;
        }
        $redis = new \Redis();
        $redis->connect(Config::get('cache')['redis']['host'], '6379');
        $redis->auth(Config::get('cache')['redis']['password']);
        $redis->select(0); // 选择2数据库
        $r = $redis->set('nowdata', json_encode($thisdatas));
        cache('nowdata', $thisdatas); // 测试没问题删除
    }

    //curl获取数据
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

    /**
     * 数据风控
     * @author lukui  2017-06-27
     * @param  [type] $price [description]
     * @param  [type] $pro   [description]
     * @return [type]        [description]
     */
    public function fengkong($price, $pro) {

        $point_low = $pro['point_low'];
        $point_top = $pro['point_top'];

        $FloatLength = $this->getFloatLength($point_top);
        $jishu_rand = pow(10, $FloatLength);
        $point_low = $point_low * $jishu_rand;
        $point_top = $point_top * $jishu_rand;
        $rand = rand($point_low, $point_top) / $jishu_rand;

        $_new_rand = rand(0, 10);
        if ($_new_rand % 2 == 0) {
            $price = $price + $rand;
        } else {
            $price = $price - $rand;
        }
        return $price;
    }

    //计算小数点后位数
    public function getFloatLength($num) {
        $count = 0;

        $temp = explode('.', $num);

        if (sizeof($temp) > 1) {
            $decimal = end($temp);
            $count = strlen($decimal);
        }

        return $count;
    }

}
