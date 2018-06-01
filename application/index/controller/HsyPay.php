<?php
namespace app\index\controller;
use app\index\service\PayService;
use think\Controller;
use think\Db;
use think\Log;

// 合盛源支付
class HsyPay extends BasePayConroller {
    private $sid = '06b08PSBAertu7ahI0B0bB7rhbO8AyjQUnn4wJgPBVw.1.';
    private $key = '1B4E1E0F9891D932440D01604F6420E1';
    private $postUrl = 'http://www.heshengyuan.wang/activepay/rangwx.html';

    private function sign($param) {
        $signStr = sprintf('amount=%s&backurl=%s&datetime=%s&shopsn=%s&key=%s',
            $param['total_price'], $param['backurl'], $param['datetime'], $param['shopsn'], $this->key
        );
//        Log::info('signStr:' . $signStr);
        return strtoupper(md5($signStr));
    }

    // 充值接口
    public function recharge() {
        $this->checkLogin();

        $order_no = input('get.orderid');
        $oInfo = $this->getOrderInfo($order_no);
        $money = $oInfo['bpprice'];

        $amount = intval($money * 100);//单位：分
        $notify_url = 'http://' . $_SERVER['HTTP_HOST']  . '/index/hsy_pay/recharge_notify';
        $result_url = 'http://'.$_SERVER['HTTP_HOST'] . '/index/user/index.html';

        $param = array(
            'sid' => $this->sid,
            'total_price' => $amount,
            'backurl' => $notify_url,
            'datetime' => time(),
            'shopsn' => $order_no,
        );
        $param['sign'] = $this->sign($param);
        $param['userid'] = $this->uid;
        $param['notifyurl'] = $result_url;

        $this->assign('postData', $param);
        $this->assign('postUrl', $this->postUrl);
        return $this->fetch('./pay/pay_form_submit');
    }

    // 充值异步回调
    public function recharge_notify() {
        $reqData = $this->request->post();
        Log::info('hsyPpay 异步回调数据:' . json_encode($reqData));
        if (empty($reqData)) {
            Log::error('hsyPpay 异步回调返回空');
            return $this->display('error');
        }

        if (empty($reqData['amount']) or empty($reqData['datetime']) or empty($reqData['shopsn']) or empty($reqData['sign'])) {
            Log::error('hsyPpay 异步回调必填数据返回空');
            return $this->display('empty');
        }

        $notify_url = 'http://' . $_SERVER['HTTP_HOST']  . '/index/hsy_pay/recharge_notify';
        // 验签
        $param = array();
        $param['total_price'] = $reqData['amount'];
        $param['datetime'] = $reqData['datetime'];
        $param['shopsn'] = $reqData['shopsn'];
        $param['backurl'] = $notify_url;
        $mySign = $this->sign($param);
        if ($reqData['sign'] != $mySign) {
            Log::error('hsyPpay 异步回调签名错误');
            return $this->display('error');
        }

        PayService::notify_ok_dopay($reqData['shopsn'], ($reqData['amount']/100));
        Log::info('hsyPpay 异步回调处理成功订单完成：' . $reqData['shopsn']);
        return $this->display('SUCCESS');
    }
}
