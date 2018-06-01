<?php
/**
 * 计算分润定时器，
 * TODO 需要优化查询 防止数据量过大 查询缓慢引起阻塞
 */
namespace app\index\controller;

use think\console\Command;
use think\Db;
use think\Config;
use app\index\service\BitDataService;

class ShareOrder extends Command {

    protected function configure() {
        $this->setName('ShareOrder')->setDescription('Command ShareOrder');
    }

    protected function execute(\think\console\Input $input, \think\console\Output $output) {
        $map['isshow'] = 0;
        $map['ostaus'] = 1;
        $map['selltime'] = array('<', time() - 60);
        $list = db('order')->where($map)->limit(0, 10)->select();

        if (!$list) {
            echo '没有需要执行数据';
            return ;
        }

        foreach ($list as $k => $v) {
            //分配金额
            $this->allotfee($v['uid'], $v['fee'], $v['is_win'], $v['oid'], $v['ploss']);
            //更改订单状态
            db('order')->where('oid', $v['oid'])->update(array('isshow' => 1));
        }
    }

    public function allotfee($uid, $fee, $is_win, $order_id, $ploss) {
        $userinfo = db('userinfo');

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
    }

}
