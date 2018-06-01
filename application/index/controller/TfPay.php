<?php
namespace app\index\controller;
use app\index\service\PayService;
use think\Controller;
use think\Db;
use think\Log;

// 合盛源支付
class TfPay extends BasePayConroller {
    private $mid = 'A20000000000058';
    private $tradeId = '100850000414608';
    private $key = '1E43374EE3F8014CFBA3193F3E2E1C5B';
    private $postUrl = 'http://www.tianshanpay.cn:8099/OuYaZhiFu/';

    // 充值接口
    public function recharge() {
        $this->checkLogin();

        $order_no = input('get.orderid');
        $st = input('get.st'); // 默认4.13	银联快捷支付；scan：4.10 银联云闪付扫码
        $oInfo = $this->getOrderInfo($order_no);
        $money = $oInfo['bpprice'];

        $amount = intval($money * 100);//单位：分
        $notify_url = 'http://' . $_SERVER['HTTP_HOST']  . '/index/tf_pay/recharge_notify';
        $result_url = 'http://'.$_SERVER['HTTP_HOST'] . '/index/user/index.html';

        $param = array(
            'merchNo' => $this->mid,
            'outTradeNo' => $order_no,
            'totalFee' => $amount,
            'notifyUrl' => $notify_url,
            'nonceStr' => time(),
        );
        $url = '';
        if ($st == 'scan') {
            $url = $this->postUrl . 'UnionPaySanning.action';
            $param['mchId'] = $this->tradeId;
            $signStr = sprintf('merchNo=%s&mchId=%s&outTradeNo=%s&totalFee=%s&notifyUrl=%s&nonceStr=%s&key=%s',
                $this->mid, $param['mchId'], $param['outTradeNo'], $param['totalFee'], $param['notifyUrl'],
                $param['nonceStr'], $this->key
            );
        } else {
            $url = $this->postUrl . 'UnionQuickPay.action';
            $param['return_url'] = $result_url;
            $signStr = sprintf('merchNo=%s&return_url=%s&outTradeNo=%s&totalFee=%s&notifyUrl=%s&key=%s',
                $this->mid, $param['return_url'], $param['outTradeNo'], $param['totalFee'], $param['notifyUrl'], $this->key
            );
        }
//        Log::info('signStr:' . $signStr);
        $param['sign'] = strtoupper(md5($signStr));
        $postRes = PayService::curlfun($url, $param, 'POST');
//        Log::info('tfpay 支付请求返回' . $postRes);

        $postRes = empty($postRes) ? null : json_decode($postRes, true);
        if (empty($postRes)) {
            Log::error('tfpay 支付请求返回数据为空');
            $this->error("支付请求返回数据为空");
        }

        if ($postRes['return_code'] != 'SUCCESS' || $postRes['result_code'] != 'SUCCESS') {
            Log::error('tfpay 支付请求失败：' . $postRes['return_msg']);
            $this->error('支付请求失败：' . $postRes['return_msg']);
        }

        if ($st == 'scan') {
            $this->assign('code_url', 'http://pan.baidu.com/share/qrcode?w=200&h=200&url=' . $postRes['code_url']);
            return $this->fetch('./pay/pay_scan');
        } else {
            $this->redirect($postRes['code_url']);
        }
    }

    // 充值异步回调
    public function recharge_notify() {
        $reqData = $this->request->post();
        Log::info('tfPay 异步回调数据:' . json_encode($reqData));
        if (empty($reqData)) {
            Log::error('tfPay 异步回调返回空');
            return $this->display('error');
        }

        if (empty($reqData['total_fee']) or empty($reqData['return_code']) or empty($reqData['out_trade_no']) or empty($reqData['sign'])) {
            Log::error('tfPay 异步回调必填数据返回空');
            return $this->display('empty');
        }
        $signStr = sprintf('return_code=%s&total_fee=%s&mch_id=%s&out_trade_no=%s&key=%s',
            $reqData['return_code'], $reqData['total_fee'], $this->mid, $reqData['out_trade_no'], $this->key
        );
//        Log::info('signStr:' . $signStr);
        $mySign = strtoupper(md5($signStr));
        if ($reqData['sign'] != $mySign) {
            Log::error('tfPay 异步回调签名错误');
            return $this->display('error');
        }

        if ($reqData['return_code'] == 'SUCCESS') {
            PayService::notify_ok_dopay($reqData['out_trade_no'], ($reqData['total_fee'] / 100));
            Log::info('tfPay 异步回调处理成功订单完成：' . $reqData['out_trade_no']);
        }
        return $this->display('SUCCESS');
    }
}
