<?php
namespace app\index\controller;
use app\index\service\PayService;
use think\Controller;
use think\Log;

// es支付
class EsPay extends Controller {
    private $key = 'jrer9hh0efusz9c6ykflxv73sc6mwak6';// 密钥key
    private $merid = '10326';   // 商户号

    // 充值接口
    public function recharge() {
        $money = input('get.money');
        $service_type = input('get.st'); // 服务类型 802快捷 803网银
        $order_no = input('get.orderid');

        $post_url = 'http://espay.dhdz578.com/Pay_Index.html';// 请求地址
        $amount = strval($money);//单位：元
        $subject = "recharge $money";//商品名称
        $result_url = 'http://'.$_SERVER['HTTP_HOST'] . '/index/user/index.html';
        $notify_url = 'http://'.$_SERVER['HTTP_HOST'].'/index/es_pay/recharge_notify';

        $post_data = array(
            'pay_memberid' => $this->merid,
            'pay_orderid' => $order_no,
            'pay_amount' => $amount,
            'pay_applydate' => date("Y-m-d H:i:s"),
            'pay_bankcode' => $service_type, // 支付通道编
            'pay_notifyurl' => $notify_url,
            'pay_callbackurl' => $result_url,
        );
        ksort($post_data);
        $md5str = "";
        foreach ($post_data as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        $sign = strtoupper(md5($md5str . "key=" . $this->key));

        $post_data['pay_md5sign'] = $sign;
        $post_data['pay_productname'] = $subject;

        $this->assign('postData', $post_data);
        $this->assign('postUrl', $post_url);
        return $this->fetch('./pay/pay_form_submit');
    }

    // 充值异步回调
    public function recharge_notify($st='') {
        // 返回字段
        $returnArray = array(
            "memberid" => $_REQUEST["memberid"], // 商户ID
            "orderid" =>  $_REQUEST["orderid"], // 订单号
            "amount" =>  $_REQUEST["amount"], // 交易金额
            "datetime" =>  $_REQUEST["datetime"], // 交易时间
            "transaction_id" =>  $_REQUEST["transaction_id"], // 支付流水号
            "returncode" => $_REQUEST["returncode"],
        );

        $result = 'fail';
        if (empty($returnArray)) {
            Log::error('espay的充值异步回调请求数据为空');
            return $this->display($result);
        }

        Log::info('espay的充值异步回调数据:' . json_encode($returnArray));
        if (empty($returnArray['orderid']) or empty($returnArray['amount']) or empty($_REQUEST["sign"])) {
            Log::error('espay的充值异步回调请求数据不正确！');
            return $this->display($result);
        }

        ksort($returnArray);
        reset($returnArray);
        $md5str = '';
        foreach ($returnArray as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        $sign = strtoupper(md5($md5str . "key=" . $this->key));
        if ($sign != $_REQUEST["sign"]) {
            Log::error('espay的充值异步回调签名不正确！');
            return $this->display($result);
        }

        //验签
        if($returnArray["returncode"] == "00"){
            PayService::notify_ok_dopay($returnArray['orderid'], $returnArray['amount']);
            $result = 'OK';
        }

        return $this->display($result);
    }
}