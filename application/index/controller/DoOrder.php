<?php

namespace app\index\controller;

use think\console\Command;
use think\Db;
use think\Config;
use app\index\service\SwooleClient;

class DoOrder extends Command {
    
    public $redis = null;
    public $user_win = array(); //指定客户赢利
    public $user_loss = array(); //指定客户亏损
    public $orderList = [];
    public $nowTime = '';
    
    protected function configure() {
        $this->setName('DoOrder')->setDescription('Command DoOrder');
    }
    
    public function doInit() {
        $this->nowTime = time();
        $this->getOrderData($this->nowTime);
        if ($this->orderList) {
            $this->delOrderData($this->nowTime);
        }
    }
    
    protected function delOrderData($nowTime) {
        $redis = $this->getRedis();
        $redis->select(2);
        $redis->zRemRangeByScore('order_list', 0, $nowTime);
    }

    protected function getRedis() {
        if ($this->redis) {
            return $this->redis;
        }
        $this->redis = new \Redis();
        $this->redis->connect(Config::get('cache')['redis']['host'], '6379');
        $this->redis->auth(Config::get('cache')['redis']['password']);
        return $this->redis;
    }

    protected function getOrderData($nowTime) {
        $redis = $this->getRedis();
        $redis->select(2);
        $orderList = $redis->zRangeByScore('order_list', 0, $nowTime);
//        var_dump($orderList);die;
        foreach ($orderList as $order) {
            $this->orderList[] = json_decode($order, true);
        }
        return ;
    }


    protected function execute(\think\console\Input $input, \think\console\Output $output) {
        $this->doInit();
        $db_order = db('order');
        $db_userinfo = db('userinfo');
        $data_info = db('productinfo'); // 可放redis 做数据库同步
        //风控参数
        $risk = db('risk')->find(); // 可放redis 做数据库同步
        //此刻产品价格
        $p_map['isdelete'] = 0;
        $proData = db('productdata')->field('pid,Price')->where($p_map)->select(); // 可放redis 做数据库同步
        $prodata = array();
        $redis = $this->getRedis();
        $redis->select(0);
        $tmpPro = $redis->get('nowdata');
        $_pro = json_decode($tmpPro, true);
        foreach ($proData as $k => $v) {
            if (!isset($_pro[$v['pid']])) {
                $prodata[$v['pid']] = $v['Price'];
                continue;
            }
            $prodata[$v['pid']] = $this->order_type($this->orderList, $_pro[$v['pid']], $risk, $data_info);
        }
        $count = count($this->orderList);
        if (!$count) {
            return ;
        }
        $orderListArray = array_chunk($this->orderList, ceil($count / Config::get('swoole_task_worker_num')));
        $swoolClient = new SwooleClient();
        echo "执行投递任务\n";
        $swoolClient->connect(['order_list' => $orderListArray, 'pro_data' => $prodata, 'nowtime' => $this->nowTime]);
        echo "执行投递任务结束\n";
    }

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
        $redis = $this->getRedis();
        $redis->select(2);
        foreach ($orders as $k => $v) {
//            $v['kong_type'] = 0; // 临时
            if (!isset($v['kong_type']) || $v['kong_type'] == '') {
                $kongType = $redis->get($v['oid']);
                if ($kongType) {
                    $v['kong_type'] = $kongType;
                    $redis->del($v['oid']);
                } else {
                    $v['kong_type'] = 0;
                }
            }
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
        }

        if (!empty($dankong_kui) && $is_do_price == 0) {   //单控 2亏损
            if ($dankong_kui['ostyle'] == 0) {
                $pro['Price'] = $v['buyprice'] - $do_rand;
            } elseif ($dankong_kui['ostyle'] == 1) {
                $pro['Price'] = $v['buyprice'] + $do_rand;
            }
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
        }
        //是否存在指定亏损
        if (!empty($is_to_loss) && $is_do_price == 0) {


            if ($is_to_loss['ostyle'] == 0) {
                $pro['Price'] = $v['buyprice'] - $do_rand;
            } elseif ($is_to_loss['ostyle'] == 1) {
                $pro['Price'] = $v['buyprice'] + $do_rand;
            }
            $is_do_price = 1;
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
                    $is_do_price = 1;
                } else {  //客赢
                    $pro['Price'] = $max_buyprice + $do_rand;
                    $is_do_price = 1;
                }
            }

            if (isset($_rand) && $down_price != 0) {

                if ($_rand > $chance_num) { //客损
                    $pro['Price'] = $max_buyprice + $do_rand;
                    $is_do_price = 1;
                } else {  //客赢
                    $pro['Price'] = $min_buyprice - $do_rand;
                    $is_do_price = 1;
                }
            }
        }

        //多个人下单，并且所有人下单买的方向不相同
        if ($up_price != 0 && $down_price != 0 && $is_do_price == 0) {

            //买涨大于买跌的
            if ($up_price > $down_price) {
                $pro['Price'] = $min_buyprice - $do_rand;
                $is_do_price = 1;
            }
            //买涨小于买跌的
            if ($up_price < $down_price) {
                $pro['Price'] = $max_buyprice + $do_rand;
                $is_do_price = 1;
            }
            if ($up_price == $down_price) {
                $is_do_price = 2;
            }
        }
        if ($is_do_price == 2 || $is_do_price == 0) {
            $pro['Price'] = $this->fengkong($pro['Price'], $proinfo);
        }
        if ($proinfo['isopen'] == 1) { // 莫名其妙了...
            db('productdata')->where('pid', $pro['pid'])->update($pro);
            $redis->select(0);
            $redis->set('pan_' . $pro['pid'], json_encode($pro));
        }

        //存储k线值
        $k_map['pid'] = $pro['pid'];
        $minute = date('Y-m-d H:i', time()) . ':00';
        $minute = strtotime($minute);
        $k_map['ktime'] = $minute;
        return $pro['Price'];
    }

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

}
