<?php

namespace app\index\service;

use think\Config;

class OrderService {

    public static function SettleAccounts($data) {
        $orderList = $data['order_list'];
        $prodata = $data['pro_data'];
        $nowTime = $data['nowtime'];
        foreach ($orderList as $k => $v) {
            db('order')->startTrans();
            $user = db('userinfo')->where('uid', $v['uid'])->find(); //临时使用
            //此刻可平仓价位
            $sellprice = isset($prodata[$v['pid']]) ? $prodata[$v['pid']] : 0;
            if ($sellprice == 0) {
                db('order')->rollback();
                self::addCache($v);
                continue;
            }
            //买入价
            $buyprice = $v['buyprice'];
            $fee = $v['fee'];
            $order_cha = round(floatval($sellprice) - floatval($buyprice), 6);
            //买涨
            if ($v['ostyle'] == 0 && $nowTime >= $v['selltime']) {

                if ($order_cha > 0) {  //盈利
                    $yingli = $v['fee'] * ($v['endloss'] / 100);
                    $d_map['is_win'] = 1;
                    //平仓增加用户金额
                    $u_add = $yingli + $fee;
                    db('userinfo')->where('uid', $v['uid'])->setInc('usermoney', $u_add);
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
                    self::set_order_log($v, $u_add);
                } elseif ($order_cha < 0) { //亏损
                    $yingli = -1 * $v['fee'];
                    $d_map['is_win'] = 2;
                    self::set_order_log($v, 0);
                } else {  //无效
                    $yingli = 0;
                    $d_map['is_win'] = 3;

                    //平仓增加用户金额
                    $u_add = $fee;
                    db('userinfo')->where('uid', $v['uid'])->setInc('usermoney', $u_add);

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
                    self::set_order_log($v, $u_add);
                }
                //平仓处理订单
                $d_map['ostaus'] = 1;
                $d_map['sellprice'] = $sellprice;
                $d_map['ploss'] = $yingli;
                $d_map['oid'] = $v['oid'];
                db('order')->update($d_map);
                //买跌
            } elseif ($v['ostyle'] == 1 && $nowTime >= $v['selltime']) {
                if ($order_cha < 0) {  //盈利
                    $yingli = $v['fee'] * ($v['endloss'] / 100);
                    $d_map['is_win'] = 1;
                    //平仓增加用户金额
                    $u_add = $yingli + $fee;
                    db('userinfo')->where('uid', $v['uid'])->setInc('usermoney', $u_add);

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
                    self::set_order_log($v, $u_add);
                } elseif ($order_cha > 0) { //亏损
                    $yingli = -1 * $v['fee'];
                    $d_map['is_win'] = 2;
                    self::set_order_log($v, 0);
                } else {  //无效
                    $yingli = 0;
                    $d_map['is_win'] = 3;
                    //平仓增加用户金额
                    $u_add = $fee;
                    db('userinfo')->where('uid', $v['uid'])->setInc('usermoney', $u_add);
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
                    self::set_order_log($v, $u_add);
                }
                //平仓处理订单
                $d_map['ostaus'] = 1;
                $d_map['sellprice'] = $sellprice;
                $d_map['ploss'] = $yingli;
                $d_map['oid'] = $v['oid'];
                $r = db('order')->where(['oid' => $v['oid'], 'ostaus' => 0])->update($d_map);
                if ($r) {
                    db('order')->commit();
                } else {
                    db('order')->rollback();
                }
            }
        }
    }

    /**
     * 写入平仓日志
     * @author lukui  2017-07-01
     * @param  [type] $v        [description]
     * @param  [type] $addprice [description]
     */
    public static function set_order_log($v, $addprice) {
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
    
    public static function addCache($adddata) {
        $redis = new \Redis();
        $redis->connect(Config::get('cache')['redis']['host'], '6379');
        $redis->auth(Config::get('cache')['redis']['password']);
        $redis->select(2); // 选择2数据库
        $redis->zAdd('order_list', $adddata['selltime'], json_encode($adddata));
        $redis->close();
        return ;
    }
}
