<?php 
namespace app\index\controller;

use app\index\service\BitDataService;
use think\Controller;
use think\Db;
use think\Log;
use think\Config;

class Api extends Controller {
    /*     * ************************开始*************************** */
    private function ip_check() {
        // 这两个方法只允许服务器所在机器访问
        $this->clientIp = $this->request->ip();
        if (in_array(request()->action(), ['getdatetjgj', 'allotordertjgj', 'ordertjgj'])) {
            if ($this->clientIp != config("server_out_ip")) {
                return false;
            }
        }
        return true;
    }

    //获取K线
    function get_history_kline($symbol = '', $period = '', $size = 0, $api, $req_method) {
        $api_method = "/market/history/kline";
        $param = [
            'symbol' => $symbol,
            'period' => $period
        ];
        if ($size)
            $param['size'] = $size;
        $url = $this->create_sign_url($param, $api_method, $req_method, $api);
        return json_decode($this->curl($url, $param, $req_method), 1);
    }

    //获取实时数据
    function get_detail_merged($symbol = '', $api, $req_method) {
        $api_method = "/market/detail/merged";
        $param = [
            'symbol' => $symbol,
        ];
        $url = $this->create_sign_url($param, $api_method, $req_method, $api);
        return json_decode($this->curl($url, $param, $req_method), 1);
    }

    function create_sign_url($append_param = [], $api_method, $req_method, $api) {

        // 验签参数
        $param = [
            'AccessKeyId' => ACCESS_KEY,
            'SignatureMethod' => 'HmacSHA256',
            'SignatureVersion' => 2,
            'Timestamp' => date('Y-m-d\TH:i:s', time())
        ];
        if ($append_param) {
            foreach ($append_param as $k => $ap) {
                $param[$k] = $ap;
            }
        }
        return 'https://' . $api . $api_method . '?' . $this->bind_param($param, $req_method, $api, $api_method);
    }

// 组合参数
    function bind_param($param, $req_method, $api, $api_method) {
        $u = [];
        $sort_rank = [];
        foreach ($param as $k => $v) {
            $u[] = $k . "=" . urlencode($v);
            $sort_rank[] = ord($k);
        }
        asort($u);
        $u[] = "Signature=" . urlencode($this->create_sig($u, $req_method, $api, $api_method));
        return implode('&', $u);
    }

    // 生成签名
    function create_sig($param, $req_method, $api, $api_method) {
        $sign_param_1 = $req_method . "\n" . $api . "\n" . $api_method . "\n" . implode('&', $param);
        $signature = hash_hmac('sha256', $sign_param_1, SECRET_KEY, true);
        return base64_encode($signature);
    }

    function curl($url, $postdata = [], $req_method) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        if ($req_method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata));
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
        ]);
        $output = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
        return $output;
    }

    /*     * ************************结束************************** */

    public function __construct() {
        define('ACCOUNT_ID', '33344851'); // 你的账户ID 
        define('ACCESS_KEY', '3ba01827-f4ffa02b-5be007f1-06d0b'); // 你的ACCESS_KEY
        define('SECRET_KEY', '967d2b85-f5b998ab-ffd30c4d-2181f'); // 你的SECRET_KEY 
        parent::__construct();

        if (!$this->ip_check()) {
            exception($this->clientIp . '非法访问');
        }

        $this->nowtime = time();
        $minute = date('Y-m-d H:i', $this->nowtime) . ':00';
        $this->minute = strtotime($minute);

        //指定客户赢利或亏损：
        //array()里面写客户编号，如客户id为1027则写为  array(1027)
        //如多个客户，则以英文逗号分开，如 array(1027,2018,3765)
        //注意一定是英文逗号，中文逗号会报错
        $this->user_win = array(); //指定客户赢利
        $this->user_loss = array(); //指定客户亏损
        //K线数据库
        $this->klinedata = db('klinedata');
    }

    public function getdatetjgj() {
        //产品列表
        $pro = db('productinfo')->where('isdelete', 0)->select();

        if (!isset($pro)) {
            return $this->display('empty');
        }

        $nowtime = time();
        $_rand = rand(1, 900) / 100000;
        $thisdatas = array();

        foreach ($pro as $k => $v) {

            //验证休市
            $isopen = ChickIsOpen($v['pid']);
            if (!$isopen) {
                continue;
            }

            //比特币
            if (in_array($v['procode'], array("btc", "ltc", "eth"))) {
                $minute = date('i', $nowtime);
                if ($minute >= 0 && $minute < 15) {
                    $minute = 0;
                } elseif ($minute >= 15 && $minute < 30) {
                    $minute = 15;
                } elseif ($minute >= 30 && $minute < 45) {
                    $minute = 30;
                } elseif ($minute >= 45 && $minute < 60) {
                    $minute = 45;
                }
                $new_date = strtotime(date('Y-m-d H', $nowtime) . ':' . $minute . ':00');

                switch ($v['procode']) {
                    case 'btc':
                        $From = 12;
                        break;
                    case 'ltc':
                        $From = 35;
                        break;
                    case 'eth':
                        $From = 36;
                        break;

                    default:
                        continue;
                        break;
                }
                $url = "https://www.okex.com/api/klineData.do?marketFrom=" . $From . "&type=2&since=" . $new_date . "000&coinVol=0";

                $getdata = $this->curlfun($url);


                $getdata = substr($getdata, 2, -2);
                $data_arr = explode(',', $getdata);

                if (!is_array($data_arr) || count($data_arr) != 7)
                    continue;
                //$thisdata['Price'] = $this->fengkong($data_arr[4],$v);;
                $thisdata['Price'] = $data_arr[4];
                $thisdata['Open'] = $data_arr[1];
                $thisdata['Close'] = $data_arr[6];
                $thisdata['High'] = $data_arr[2];
                $thisdata['Low'] = $data_arr[3];
                $thisdata['Diff'] = $data_arr[5];
                $thisdata['DiffRate'] = $data_arr[5] / 1000;

                /*                 * ************************开始************************** */
            }elseif (in_array($v['procode'], array('BTCCNY'))) {   //虚拟货币
                $api = 'api.huobipro.$com';
                $thisdata = BitDataService::getNowData();
                if ($thisdata == null) {
                    continue;
                }
                //外汇网：http://forex.cnfol.com/
            } elseif (in_array($v['procode'], array("USDJPY", "GBPJPY", "EURUSD", "GBPUSD"))) {
                $url = "http://forex.cnfol.com/fol_inc/v6.0/Gold/goldhq/json_table/" . $v['procode'] . "_forexdata.json";
                $getdata = $this->curlfun($url, array(), 'POST');

                $arrdata = json_decode($getdata, 1);
                $arrdata = $arrdata["data"];

                $_data = array();
                foreach ($arrdata as $key => $value) {
                    if ($v['procode'] == $value['Code']) {
                        $_data = $value;
                    }
                }

                $thisdata['Price'] = $_data['Last'];
                $thisdata['Open'] = $_data['Open'];
                $thisdata['Close'] = $_data['LastClose'];
                $thisdata['High'] = $_data['High'];
                $thisdata['Low'] = $_data['Low'];
                $thisdata['Diff'] = 0;
                $thisdata['DiffRate'] = 0;
            } elseif (in_array($v['procode'], array(12, 13, 116))) {   //口袋贵金属
                $url = 'https://m.sojex.net/api.do?rtp=GetQuotesDetail&id=' . $v['procode'];
                //$html = file_get_contents($url); 
                $html = $this->curlfun($url);

                $res = json_decode($html, 1);
                $res = $res['data']['quotes'];

                //$thisdata['Price'] = $this->fengkong($res['buy'],$v);;
                $thisdata['Price'] = $res['buy'];
                $thisdata['Open'] = $res['open'];
                $thisdata['Close'] = $res['last_close'];
                $thisdata['High'] = $res['top'];
                $thisdata['Low'] = $res['low'];
                $thisdata['Diff'] = 0;
                $thisdata['DiffRate'] = 0;
            } elseif (in_array($v['procode'], array('llg', 'lls'))) {
                $url = "https://www.91pme.com/marketdata/gethq?code=" . $v['procode'];
                $html = $this->curlfun($url);
                $arr = json_decode($html, 1);
                if (!isset($arr[0]))
                    continue;
                $data_arr = $arr[0];

                $thisdata['Price'] = $this->fengkong($data_arr['buy'], $v);
                ;
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
                //$thisdata['Price'] = $this->fengkong($data_arr[1],$v);
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
            //$ids = db('productdata')->where('pid',$v['pid'])->update($thisdata);
        }
        //dump($thisdatas);exit;;
        cache('nowdata', $thisdatas);
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

        $FloatLength = getFloatLength($point_top);
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
     * 全局平仓
     * @return [type] [description]
     */
    public function ordertjgj() {
//        $oid = $_GET['oid'];
        $nowtime = time();
        $kong_end = getconf('kong_end');
        $kong_end_arr = explode('-', $kong_end);
        if (count($kong_end_arr) == 2) {
            $s_rand = rand($kong_end_arr[0], $kong_end_arr[1]);
        } else {
            $s_rand = rand(6, 12);
        }

        $db_order = db('order');
        $db_userinfo = db('userinfo');
        //订单列表
        $map['ostaus'] = 0;
        $map['selltime'] = array('elt', $nowtime + $s_rand);
//        $map['oid'] = ['<=', $oid];
        $_orderlist = $db_order->where($map)->order('selltime asc')->limit('0,50')->select();

        //dump($_orderlist);
        $data_info = db('productinfo');

        //风控参数
        $risk = db('risk')->find();

        //此刻产品价格
        $p_map['isdelete'] = 0;
        $proData = db('productdata')->field('pid,Price')->where($p_map)->select();
        $proRandData = array();
        $prodata = array();
        
        $redis = new \Redis();
        $redis->connect(Config::get('cache')['redis']['host'], '6379');
        $redis->auth(Config::get('cache')['redis']['password']);
        $redis->select(0);
        $tmpPro = $redis->get('nowdata');
        if (!$tmpPro) return ;
        $_pro = json_decode($tmpPro, true);
        foreach ($proData as $k => $v) {
            $proinfo = $data_info->where('pid', $v['pid'])->find();
            $proRandData[$v['pid']] = $proinfo['rands'];
//            $_pro = cache('nowdata');
            if (!isset($_pro[$v['pid']])) {
                $prodata[$v['pid']] = $v['Price'];
                continue;
            }

            $prodata[$v['pid']] = $this->order_type($_orderlist, $_pro[$v['pid']], $risk, $data_info);
            // dump($prodata);
            //echo '---------------------------------------------------<br>';
        }
        //exit;
        //订单列表
        $map['ostaus'] = 0;
        $map['selltime'] = array('elt', $nowtime);
//        $map['oid'] = ['<=', $oid];
        $orderlist = $db_order->where($map)->limit(0, 50)->select();

        //exit;
        if (!$orderlist) {
            return $this->display('empty');
        }

        //循环处理订单
        $nowtime = time();
        foreach ($orderlist as $k => $v) {
            $user = $db_userinfo->where('uid', $v['uid'])->find(); //临时使用
            //此刻可平仓价位
            $sellprice = isset($prodata[$v['pid']]) ? $prodata[$v['pid']] : 0;
            if ($sellprice == 0) {
                continue;
            }
            //买入价
            $buyprice = $v['buyprice'];
            $fee = $v['fee'];
            if ($v['kong_type'] != 0) { // 有单控的，价格计算优先级最高处理
                $do_rand = 0;
                $data_randsLength = getFloatLength(strval($proRandData[$v['pid']]));
                if ($data_randsLength > 0) {
                    $tn = pow(10, $data_randsLength);
                    $_j_rand = $tn * $proRandData[$v['pid']];
                    $do_rand = rand(1, $_j_rand) / $tn;
                }
                $sellprice = $this->getOrderFkPrice($buyprice, $do_rand, $v['ostyle'], $v['kong_type']);
            }

            $order_cha = round(floatval($sellprice) - floatval($buyprice), 6);

            //买涨
            if ($v['ostyle'] == 0 && $nowtime >= $v['selltime']) {

                if ($order_cha > 0) {  //盈利
                    $yingli = $v['fee'] * ($v['endloss'] / 100);
                    $d_map['is_win'] = 1;

                    //平仓增加用户金额
                    $u_add = $yingli + $fee;
                    $db_userinfo->where('uid', $v['uid'])->setInc('usermoney', $u_add);
                    $logData = [
                        'order_no' => $v['orderno'],
                        'order_money' => $u_add,
                        'user_money' => $user['usermoney'],
                        'user_paid_money' => $user['usermoney'] + $u_add,
                        'log_type' => '平仓',
                        'log_time' => date('Y-m-d H:i:s', time()),
                        'log_time_stamp' => time(),
                        'user_id' => $v['uid'],
                    ];
                    Db('userpaylog')->insertGetId($logData);

                    //写入日志
                    $this->set_order_log($v, $u_add);
                } elseif ($order_cha < 0) { //亏损
                    $yingli = -1 * $v['fee'];
                    $d_map['is_win'] = 2;
                    $this->set_order_log($v, 0);
                } else {  //无效
                    $yingli = 0;
                    $d_map['is_win'] = 3;

                    //平仓增加用户金额
                    $u_add = $fee;
                    $db_userinfo->where('uid', $v['uid'])->setInc('usermoney', $u_add);

                    $logData = [
                        'order_no' => $v['orderno'],
                        'order_money' => $u_add,
                        'user_money' => $user['usermoney'],
                        'user_paid_money' => $user['usermoney'] + $u_add,
                        'log_type' => '平仓',
                        'log_time' => date('Y-m-d H:i:s', time()),
                        'log_time_stamp' => time(),
                        'user_id' => $v['uid'],
                    ];
                    Db('userpaylog')->insertGetId($logData);

                    //写入日志
                    $this->set_order_log($v, $u_add);
                }

                //平仓处理订单
                $d_map['ostaus'] = 1;
                $d_map['sellprice'] = $sellprice;
                $d_map['ploss'] = $yingli;
                $d_map['oid'] = $v['oid'];
                db('order')->update($d_map);
                //买跌
            } elseif ($v['ostyle'] == 1 && $nowtime >= $v['selltime']) {
                if ($order_cha < 0) {  //盈利
                    $yingli = $v['fee'] * ($v['endloss'] / 100);
                    $d_map['is_win'] = 1;


                    //平仓增加用户金额
                    $u_add = $yingli + $fee;
                    $db_userinfo->where('uid', $v['uid'])->setInc('usermoney', $u_add);

                    $logData = [
                        'order_no' => $v['orderno'],
                        'order_money' => $u_add,
                        'user_money' => $user['usermoney'],
                        'user_paid_money' => $user['usermoney'] + $u_add,
                        'log_type' => '平仓',
                        'log_time' => date('Y-m-d H:i:s', time()),
                        'log_time_stamp' => time(),
                        'user_id' => $v['uid'],
                    ];
                    Db('userpaylog')->insertGetId($logData);

                    //写入日志
                    $this->set_order_log($v, $u_add);
                } elseif ($order_cha > 0) { //亏损
                    $yingli = -1 * $v['fee'];
                    $d_map['is_win'] = 2;
                    $this->set_order_log($v, 0);
                } else {  //无效
                    $yingli = 0;
                    $d_map['is_win'] = 3;

                    //平仓增加用户金额
                    $u_add = $fee;
                    $db_userinfo->where('uid', $v['uid'])->setInc('usermoney', $u_add);

                    $logData = [
                        'order_no' => $v['orderno'],
                        'order_money' => $u_add,
                        'user_money' => $user['usermoney'],
                        'user_paid_money' => $user['usermoney'] + $u_add,
                        'log_type' => '平仓',
                        'log_time' => date('Y-m-d H:i:s', time()),
                        'log_time_stamp' => time(),
                        'user_id' => $v['uid'],
                    ];
                    Db('userpaylog')->insertGetId($logData);

                    //写入日志
                    $this->set_order_log($v, $u_add);
                }

                //平仓处理订单
                $d_map['ostaus'] = 1;
                $d_map['sellprice'] = $sellprice;
                $d_map['ploss'] = $yingli;
                $d_map['oid'] = $v['oid'];
                $db_order->update($d_map);
            }
        }
    }

    /**
     * 写入平仓日志
     * @author lukui  2017-07-01
     * @param  [type] $v        [description]
     * @param  [type] $addprice [description]
     */
    public function set_order_log($v, $addprice) {
        $o_log['uid'] = $v['uid'];
        $o_log['oid'] = $v['oid'];
        $o_log['addprice'] = $addprice;
        $o_log['addpoint'] = 0;
        $o_log['time'] = time();
        $o_log['user_money'] = db('userinfo')->where('uid', $v['uid'])->value('usermoney');
        db('order_log')->insert($o_log);

        //资金日志
        set_price_log($v['uid'], 1, $addprice, '结单', '订单到期获利结算', $v['oid'], $o_log['user_money']);
    }

    /**
     * 订单类型
     * @param  [type] $orders [description]
     * @return [type]         [description]
     */
    public function order_type($orders, $pro, $risk, $data_info) {


        $_prcie = $pro['Price'];

        $pid = $pro['pid'];
        $thispro = array();  //买此产品的用户
        //此产品购买人数
        $price_num = 0;
        //买涨金额，计算过盈亏比例以后的
        $up_price = 0;
        //买跌金额，计算过盈亏比例以后的
        $down_price = 0;
        //买入最低价
        $min_buyprice = 0;
        //买入最高价
        $max_buyprice = 0;
        //下单最大金额
        $max_fee = 0;
        //指定客户亏损
        $to_win = explode('|', $risk['to_win']);
        $to_win = array_filter(array_merge($to_win, $this->user_win));
        $is_to_win = array();
        //指定客户亏损
        $to_loss = explode('|', $risk['to_loss']);
        $to_loss = array_filter(array_merge($to_loss, $this->user_loss));
        $is_to_loss = array();



        $i = 0;

        foreach ($orders as $k => $v) {

            if ($v['pid'] == $pid) {
                //没炒过最小风控值直接退出price
                if ($v['fee'] < $risk['min_price']) {
                    //return $pro['Price'];
                }
                $i++;



                //单控 赢利  
                if ($v['kong_type'] == '1' || $v['kong_type'] == '3') {
                    $dankong_ying = $v;
                    break;
                }


                //单控 亏损  
                if ($v['kong_type'] == '2') {

                    $dankong_kui = $v;
                    break;
                }
                //dump($v['kong_type']);
                //是否存在指定盈利
                if (in_array($v['uid'], $to_win)) {
                    $is_to_win = $v;
                    break;
                }
                //是否存在指定亏损
                if (in_array($v['uid'], $to_loss)) {
                    $is_to_loss = $v;
                    break;
                }

                //总下单人数
                $price_num++;
                //买涨买跌累加
                if ($v['ostyle'] == 0) {
                    $up_price += $v['fee'] * $v['endloss'] / 100;
                } else {
                    $down_price += $v['fee'] * $v['endloss'] / 100;
                }
                //统计最大买入价与最大下单价
                if ($i == 1) {
                    $min_buyprice = $v['buyprice'];
                    $max_buyprice = $v['buyprice'];
                    $max_fee = $v['fee'];
                } else {
                    if ($min_buyprice > $v['buyprice']) {
                        $min_buyprice = $v['buyprice'];
                    }
                    if ($max_buyprice < $v['buyprice']) {
                        $max_buyprice = $v['buyprice'];
                    }
                    if ($max_fee < $v['fee']) {
                        $max_fee = $v['fee'];
                    }
                }
            }
        }

        // if(isset($orders[0]) && isset($max_buyprice)){
        // 	if($orders[0]['buyprice'] > $max_buyprice){
        // 		$max_buyprice = $orders[0]['buyprice'];
        // 	}
        // }
        // if(isset($orders[0]) && isset($min_buyprice)){
        // 	if($orders[0]['buyprice'] < $min_buyprice){
        // 		$min_buyprice = $orders[0]['buyprice'];
        // 	}
        // }
        // if( $pid == 12){
        // dump('$pid:'.$pid);
        // dump('$price_num:'.$price_num);
        // dump('$up_price:'.$up_price);
        // dump('$down_price:'.$down_price);
        // dump('$min_buyprice:'.$min_buyprice);
        // dump('$max_buyprice:'.$max_buyprice);
        // dump('$max_fee:'.$max_fee);
        // }
        $proinfo = $data_info->where('pid', $pro['pid'])->find();
        //根据现在的价格算出风控点
        $FloatLength = getFloatLength((float) $pro['Price']);

        if ($FloatLength == 0) {
            $FloatLength = getFloatLength($proinfo['point_top']);
        }

        //是否存在指定盈利
        $is_do_price = 0;  //是否已经操作了价格

        $jishu_rand = pow(10, $FloatLength);
        $beishu_rand = rand(1, 10);

        $data_rands = $data_info->where('pid', $pro['pid'])->value('rands');

        $data_randsLength = getFloatLength($data_rands);
        if ($data_randsLength > 0) {
            $_j_rand = pow(10, $data_randsLength) * $data_rands;
            $_s_rand = rand(1, $_j_rand) / pow(10, $data_randsLength);
        } else {
            $_s_rand = 0;
        }


        $do_rand = $_s_rand;

        //if($pro['pid'] == 12) dump($do_rand);
        //先考虑单控
        if (!empty($dankong_ying) && $is_do_price == 0) {   //单控 1赢利
            if ($dankong_ying['ostyle'] == 0) {
                $pro['Price'] = $v['buyprice'] + $do_rand;
            } elseif ($dankong_ying['ostyle'] == 1) {
                $pro['Price'] = $v['buyprice'] - $do_rand;
            }
            $is_do_price = 1;
            //echo 1;
        }

        if (!empty($dankong_kui) && $is_do_price == 0) {   //单控 2亏损
            if ($dankong_kui['ostyle'] == 0) {
                $pro['Price'] = $v['buyprice'] - $do_rand;
            } elseif ($dankong_kui['ostyle'] == 1) {
                $pro['Price'] = $v['buyprice'] + $do_rand;
            }

            //echo 2;
            $is_do_price = 1;
        }

        //指定客户赢利
        if (!empty($is_to_win) && $is_do_price == 0) {

            if ($is_to_win['ostyle'] == 0) {
                $pro['Price'] = $v['buyprice'] + $do_rand;
            } elseif ($is_to_win['ostyle'] == 1) {
                $pro['Price'] = $v['buyprice'] - $do_rand;
            }
            $is_do_price = 1;
            ////echo 1;
            //echo 3;
        }
        //是否存在指定亏损
        if (!empty($is_to_loss) && $is_do_price == 0) {


            if ($is_to_loss['ostyle'] == 0) {
                $pro['Price'] = $v['buyprice'] - $do_rand;
            } elseif ($is_to_loss['ostyle'] == 1) {
                $pro['Price'] = $v['buyprice'] + $do_rand;
            }
            $is_do_price = 1;
            //echo 4;
        }
        //没有任何下单记录
        if ($up_price == 0 && $down_price == 0 && $is_do_price == 0) {
            $is_do_price = 2;
            //return $pro['Price'];
        }

        //只有一个人下单，或者所有人下单买的方向相同
        if (( ($up_price == 0 && $down_price != 0) || ($up_price != 0 && $down_price == 0) ) && $is_do_price == 0) {

            //风控参数
            $chance = $risk["chance"];
            $chance_1 = explode('|', $chance);
            $chance_1 = array_filter($chance_1);
            //循环风控参数
            if (count($chance_1) >= 1) {
                foreach ($chance_1 as $key => $value) {
                    //切割风控参数
                    $arr_1 = explode(":", $value);
                    $arr_2 = explode("-", $arr_1[0]);
                    //比较最大买入价格
                    if ($max_fee >= $arr_2[0] && $max_fee < $arr_2[1]) {
                        //得出风控百分比
                        if (!isset($arr_1[1])) {
                            $chance_num = 30;
                        } else {
                            $chance_num = $arr_1[1];
                        }

                        $_rand = rand(1, 100);
                        continue;
                    }
                }
            }




            //买涨
            if (isset($_rand) && $up_price != 0) {

                if ($_rand > $chance_num) { //客损
                    $pro['Price'] = $min_buyprice - $do_rand;

                    // if( abs($pro['Price'] - $_prcie) > $proinfo['point_top']){
                    // 	$pro['Price'] = $_prcie - ($proinfo['point_top'] + rand(100,999)/1000);
                    // }

                    $is_do_price = 1;
                    //echo 5;
                } else {  //客赢
                    $pro['Price'] = $max_buyprice + $do_rand;
                    // if( abs($pro['Price'] - $_prcie) > $proinfo['point_top']){
                    // 	$pro['Price'] = $_prcie + ($proinfo['point_top'] + rand(100,999)/1000);
                    // }
                    $is_do_price = 1;
                    //echo 6;
                }
            }

            if (isset($_rand) && $down_price != 0) {

                if ($_rand > $chance_num) { //客损
                    $pro['Price'] = $max_buyprice + $do_rand;
                    // if( abs($pro['Price'] - $_prcie) > $proinfo['point_top']){
                    // 	$pro['Price'] = $_prcie + ($proinfo['point_top'] + rand(100,999)/1000);
                    // }
                    $is_do_price = 1;
                    //echo 7;
                } else {  //客赢
                    $pro['Price'] = $min_buyprice - $do_rand;
                    // if( abs($pro['Price'] - $_prcie) > $proinfo['point_top']){
                    // 	$pro['Price'] = $_prcie - ($proinfo['point_top'] + rand(100,999)/1000);
                    // }
                    $is_do_price = 1;
                    //echo 8;
                }
            }
        }


        //多个人下单，并且所有人下单买的方向不相同
        if ($up_price != 0 && $down_price != 0 && $is_do_price == 0) {

            //买涨大于买跌的
            if ($up_price > $down_price) {
                $pro['Price'] = $min_buyprice - $do_rand;
                // if( abs($pro['Price'] - $_prcie) > $proinfo['point_top']){
                // 	$pro['Price'] = $_prcie - ($proinfo['point_top'] + rand(100,999)/1000);
                // }
                $is_do_price = 1;
                //echo 9;
            }
            //买涨小于买跌的
            if ($up_price < $down_price) {
                $pro['Price'] = $max_buyprice + $do_rand;
                // if( abs($pro['Price'] - $_prcie) > $proinfo['point_top']){
                // 	$pro['Price'] = $_prcie + ($proinfo['point_top'] + rand(100,999)/1000);
                // }
                $is_do_price = 1;
                //echo 10;
            }
            if ($up_price == $down_price) {
                $is_do_price = 2;
            }
        }



        if ($is_do_price == 2 || $is_do_price == 0) {
            $pro['Price'] = $this->fengkong($pro['Price'], $proinfo);
        }
        //if( $pid == 12) dump($pro['Price']);
        //dump($pro);
        if ($proinfo['isopen'] == 1) {
            db('productdata')->where('pid', $pro['pid'])->update($pro);
//                cache('pan_' . $pro['pid'], json_encode($pro));
            $redis = new \redis();  // TODO实现单例
            $redis->connect(Config::get('cache')['redis']['host'], 6379);
            $redis->auth(Config::get('cache')['redis']['password']);
            $redis->select(0);
            $redis->set('pan_' . $pro['pid'], json_encode($pro));
        }
        //存储k线值
        $k_map['pid'] = $pro['pid'];
        $k_map['ktime'] = $this->minute;

        /* 此处多余--
          $issetkline = $this->klinedata->where($k_map)->find();

          if($issetkline){
          $nk_map['id'] = $issetkline['id'];
          if($issetkline['updata'] < $pro['Price']){
          $nk_map['updata'] = $pro['Price'];

          }
          if($issetkline['downdata'] > $pro['Price']){
          $nk_map['downdata'] = $pro['Price'];

          }
          $nk_map['closdata'] = $pro['Price'];
          $this->klinedata->update($nk_map);
          }else{
          $k_map['updata'] = $pro['Price'];
          $k_map['downdata'] = $pro['Price'];
          $k_map['opendata'] = $pro['Price'];
          $this->klinedata->insert($k_map);
          }




          if($pro['Price'] < $pro['Low']){
          $pro['Price'] = $pro['Low'];
          }
          if($pro['Price'] > $pro['High']){
          $pro['Price'] = $pro['High'];
          }
         */
        return $pro['Price'];
    }

    /**
     * 获取K线数据
     * @author lukui  2017-07-01
     * @return [type] [description]
     */
    public function getkdata($pid = null, $num = null, $interval = null, $isres = null) {
        $redis = new \Redis();
        $redis->connect(Config::get('cache')['redis']['host'], '6379');
        $redis->auth(Config::get('cache')['redis']['password']);
        $redis->select(0);
        $r = $redis->get('klinemap_' . $pid . '_' . $interval);
        if ($isres) {
            return (str_replace('\\','',$r));
        } else {
            exit(json_encode(base64_encode(str_replace('\\','',$r))));
        }

        $pid = empty($pid) ? input('param.pid') : $pid;
        $num = empty($num) ? input('param.num') : $num;
        $num = empty($num) ? 30 : $num;
        $pro = GetProData($pid);
        $all_data = array();


        $interval = empty($interval) ? input('param.interval') : $interval;
        $interval = input('param.interval') ? input('param.interval') : 1;
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
                exit;
            }
        }

        if ($pro['procode'] == 'llg') {
            if ($interval == 'd')
                $interval = 1440;
            $geturl = "https://hq.91pme.com/query/kline?callback=jQuery183014447531082730047_" . $nowtime . "&code=" . $pro['procode'] . "&level=" . $interval . "&maxrecords=" . $num . "&_=" . $nowtime;

            $html = $this->curlfun($geturl);
            $returnJson = substr($html, 42, -1);
            $dataArray = json_decode($returnJson, true);
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
                $newArr = [];
                foreach ($tmpArr as $arr) {
                    $a = explode(',', $arr);
                    $newArr[$a['0']] = [$a['0'], $a['1'], $a['4'], $a['2'], $a['3']];
                }
                ksort($newArr);
                $res_arr = array_values(array_slice($newArr, -$num));
            } else {
                $returnJson = substr($html, (63 + strlen($pro['procode'] . '_' . $interval . '_' .$nowtime)), -1);
                $json = preg_replace('/([a-z]+)/is', '"$1"', $returnJson);
                $tmpArr = json_decode($json, true);
                $newArr = [];
                foreach ($tmpArr as $a) {
                      $a['d'] = strtotime($a['d']);
                    $newArr[$a['d']] = [$a['d'], $a['o'], $a['c'], $a['h'], $a['l']];
                }

                ksort($newArr);
                $res_arr = array_values(array_slice($newArr, -$num));
            }
        }
        if ($pro['pid'] == 14 && $interval == 15) {
            echo count($res_arr);die;
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
        if ($isres) {
            return ($r);
        } else {
            exit(json_encode(base64_encode($r)));
        }
    }

    //test web data
    /* 	public function setusers()
      {
      test_web();
      }

     */

    public function getprodata() {

        $pid = input('param.pid');
        $redis = new \redis();
        $redis->connect(Config::get('cache')['redis']['host'], 6379);
        $redis->auth(Config::get('cache')['redis']['password']);
        $redis->select(0); // 选择2数据库
        $data = $redis->get('pan_' . $pid);
        $pro = json_decode($data, true);


        if (!$pro) {
            //echo 'data error!';
            exit;
        }
        $topdata = array(
            'topdata' => $pro['UpdateTime'],
            'now' => $pid == 12 ? round($pro['Price'], 2) : $pro['Price'],
            'open' => $pro['Open'],
            'lowest' => $pro['Low'],
            'highest' => $pro['High'],
            'close' => $pro['Close']
        );

        exit(json_encode(base64_encode(json_encode($topdata))));
    }

    /**
     * 分配订单
     * @return [type] [description]
     */
    public function allotordertjgj() {
        //查找以平仓未分配的订单  isshow字段
        $map['isshow'] = 0;
        $map['ostaus'] = 1;
        $map['selltime'] = array('<', time() - 60);
        $list = db('order')->where($map)->limit(0, 10)->select();

        if (!$list) {
            return $this->display('empty');
        }

        foreach ($list as $k => $v) {
            //分配金额
            $this->allotfee($v['uid'], $v['fee'], $v['is_win'], $v['oid'], $v['ploss']);
            //更改订单状态
            db('order')->where('oid', $v['oid'])->update(array('isshow' => 1));
        }
        //dump($list);
    }

    public function allotfee($uid, $fee, $is_win, $order_id, $ploss) {
        $userinfo = db('userinfo');
        $allot = db('allot');
        $nowtime = time();

        $user = $userinfo->field('uid,oid')->where('uid', $uid)->find();
        $myoids = myupoid($user['oid']);



        if (!$myoids) {
            return;
        }

        //红利
        $_fee = 0;
        //佣金
        $_feerebate = 0;
        //手续费
        $web_poundage = getconf('web_poundage');
        //分配金额
        if ($is_win == 1) {
            $pay_fee = $ploss;
        } elseif ($is_win == 2) {
            $pay_fee = $fee;
        } else {
            //20170801 edit
            $pay_fee = 0;
        }

        foreach ($myoids as $k => $v) {
            $rebate = empty($v["rebate"]) ? 0 : $v["rebate"];
            $feerebate = empty($v["feerebate"]) ? 0 : $v["feerebate"];

            if ($user['oid'] == $v['uid']) { //直接推荐者拿自己设置的比例
                $_fee = round($pay_fee * ($rebate / 100), 2);
                $_feerebate = round($fee * $web_poundage / 100 * ($feerebate / 100), 2);
            } else {  //他上级比例=本级-下级比例
                $sonRebate = empty($myoids[$k - 1]["rebate"]) ? 0 : $myoids[$k - 1]["rebate"];
                $sonfeeRebate = empty($myoids[$k - 1]["feerebate"]) ? 0 : $myoids[$k - 1]["feerebate"];

                $_my_rebate = ($rebate - $sonRebate);
                if ($_my_rebate < 0)
                    $_my_rebate = 0;
                $_fee = round($pay_fee * ( $_my_rebate / 100), 2);
                $_my_feerebate = ($feerebate - $sonfeeRebate);
                if ($_my_feerebate < 0)
                    $_my_feerebate = 0;
                $_feerebate = round($fee * $web_poundage / 100 * ( $_my_feerebate / 100), 2);
            }


            //红利
            if ($is_win == 1) { //客户盈利代理亏损
                if ($_fee != 0) {
                    $ids_fee = $userinfo->where('uid', $v['uid'])->setDec('usermoney', $_fee);
                } else {
                    $ids_fee = null;
                }

                $type = 2;
                $_fee = $_fee * -1;
            } elseif ($is_win == 2) { //客户亏损代理盈利
                if ($_fee != 0) {
                    $ids_fee = $userinfo->where('uid', $v['uid'])->setInc('usermoney', $_fee);
                } else {
                    $ids_fee = null;
                }

                $type = 1;
            } elseif ($is_win == 3) { //无效订单不做操作
                $ids_fee = null;
            }

            if ($ids_fee) {
                //余额
                $nowmoney = $userinfo->where('uid', $v['uid'])->value('usermoney');
                set_price_log($v['uid'], $type, $_fee, '对冲', '下线客户平仓对冲', $order_id, $nowmoney);
            }

            //手续费
            if ($_feerebate != 0) {
                $ids_feerebate = $userinfo->where('uid', $v['uid'])->setInc('usermoney', $_feerebate);
            } else {
                $ids_feerebate = null;
            }

            if ($ids_feerebate) {
                //余额
                $nowmoney = $userinfo->where('uid', $v['uid'])->value('usermoney');
                set_price_log($v['uid'], 1, $_feerebate, '客户手续费', '下线客户下单手续费', $order_id, $nowmoney);
            }
        }

        /*

          foreach ($myoids as $k => $v) {
          //分红利
          if($_fee <= 0){
          continue;
          }

          if($v['rebate'] <= 0 || $v['rebate'] > 100){
          continue;
          }

          //分给我的钱
          $my_fee = round($_fee*(100-$v['rebate'])/100,2);

          if($my_fee <= 0.01){
          continue;
          }


          if($is_win == 1){	//客户盈利代理亏损
          $ids = $userinfo->where('uid',$v['uid'])->setDec('usermoney', $my_fee);
          $type = 2;
          $my_fee = $my_fee*-1;
          }elseif($is_win == 2){	//客户亏损代理盈利

          $ids = $userinfo->where('uid',$v['uid'])->setInc('usermoney', $my_fee);
          $type = 1;
          }elseif($is_win == 3){	//无效订单不做操作
          $ids = null;
          }
          //余额
          $nowmoney = $userinfo->where('uid',$v['uid'])->value('usermoney');

          if($ids){
          $_data['is_win'] = $is_win;
          $_data['time'] = $nowtime;
          $_data['uid'] = $v['uid'];
          $_data['order_id'] = $order_id;
          $_data['my_fee'] = $my_fee;
          $_data['nowmoney'] = $nowmoney;
          $_data['type'] = 1;
          $allot->insert($_data);

          set_price_log($v['uid'],$type,$my_fee,'对冲','下线客户平仓对冲',$order_id,$nowmoney);
          }

          $_fee = round($_fee*$v['rebate']/100,2);


          }

          //分佣金
          foreach ($myoids as $k => $v) {


          if($yj_fee <= 0){
          continue;
          }

          if($v['feerebate'] <= 0 || $v['feerebate'] > 100){
          continue;
          }

          //分给我的钱
          $my_fee = round($yj_fee*(100-$v['feerebate'])/100,2);

          if($my_fee <= 0.01){
          continue;
          }

          //余额
          $nowmoney = $userinfo->where('uid',$v['uid'])->value('usermoney');
          if($is_win == 1 || $is_win == 2){	//有效订单
          $ids = $userinfo->where('uid',$v['uid'])->setInc('usermoney', $my_fee);
          $type = 1;
          }else{
          $ids = null;
          }
          if($ids){
          $_data['is_win'] = $is_win;
          $_data['time'] = $nowtime;
          $_data['uid'] = $v['uid'];
          $_data['order_id'] = $order_id;
          $_data['my_fee'] = $my_fee;
          $_data['nowmoney'] = $nowmoney;
          $_data['type'] = 2;
          $allot->insert($_data);

          set_price_log($v['uid'],$type,$my_fee,'客户手续费','下线客户下单手续费',$order_id,$nowmoney);
          }

          $yj_fee = round($yj_fee*$v['feerebate']/100,2);


          }
         */
    }

    /**
     * 获取K线。缓存起来
     * @author lukui  2017-08-13
     * @return [type] [description]
     */
    public function cachekline() {

        $pro = db('productinfo')->field('pid')->where('isdelete', 0)->select();
        $kline = cache('cache_kline');
        foreach ($pro as $k => $v) {

            $res[$v['pid']][1] = $this->getkdata($v['pid'], 60, 1, 1);
            if (!$res[$v['pid']][1])
                $res[$v['pid']][1] = $kline[$v['pid']][1];
            $res[$v['pid']][5] = $this->getkdata($v['pid'], 60, 5, 1);
            if (!$res[$v['pid']][5])
                $res[$v['pid']][5] = $kline[$v['pid']][5];
            $res[$v['pid']][15] = $this->getkdata($v['pid'], 60, 15, 1);
            if (!$res[$v['pid']][15])
                $res[$v['pid']][15] = $kline[$v['pid']][15];
            $res[$v['pid']][30] = $this->getkdata($v['pid'], 60, 30, 1);
            if (!$res[$v['pid']][30])
                $res[$v['pid']][30] = $kline[$v['pid']][30];
            $res[$v['pid']][60] = $this->getkdata($v['pid'], 60, 60, 1);
            if (!$res[$v['pid']][60])
                $res[$v['pid']][60] = $kline[$v['pid']][60];
            $res[$v['pid']]['d'] = $this->getkdata($v['pid'], 60, 'd', 1);
            if (!$res[$v['pid']]['d'])
                $res[$v['pid']]['d'] = $kline[$v['pid']]['d'];
        }



        cache('cache_kline', $res);
    }

    function getkline() {

        $kline = cache('cache_kline');
        $pid = input('pid');
        $interval = input('interval');

        if (!$interval || !$pid) {
            return false;
        }

        $info = json_decode($kline[$pid][$interval], 1);

        return exit(json_encode($info));
    }

    // 获取单个订单的风控卖价
    private function getOrderFkPrice($buyPrice, $rand, $buyType, $kongType) {
        // 0不控1盈利2亏损
        if (($buyType == 0 && $kongType == 1) || ($buyType == 1 && $kongType == 2)) { // 买涨控盈利的或者买跌控制亏的
            return $buyPrice + $rand;
        } elseif (($buyType == 0 && $kongType == 2) || ($buyType == 1 && $kongType == 1)) {// 买涨控亏的或者买跌控制盈的
            return $buyPrice - $rand;
        } else {
            return $buyPrice;
        }
    }

}

?>
