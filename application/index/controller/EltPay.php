<?php
namespace app\index\controller;
use app\index\service\PayService;
use think\Db;
use think\Log;

// 易路通支付
class EltPay extends BasePayConroller {
    private $key = 'zJ5OLwePCl1qxusi';// 密钥key
    private $appid = '0I5A19B8380001GS';   // 商户号
    private $session = 'f7976d519c5947deba640ed1e5f98e65';
    private $payUrl = 'http://bank.fjelt.com/pay/Rest';

    private function urlSafeEncode($str) {
        $str = str_replace('\n', '', $str);
        $str = str_replace('\r', '', $str);
        $str = str_replace('+', '-', $str);
        $str = str_replace('/', '_', $str);
        return $str;
    }

    private function urlSafeDecode($str) {
        $str = str_replace('-', '+', $str);
        $str = str_replace('_', '/', $str);
        return $str;
    }

    private function getPostData($method, $bizData) {
        $aes_start_str = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $this->key , json_encode($bizData), MCRYPT_MODE_CBC, $this->key);

        $post_data = array();
        $post_data['appid'] = $this->appid;
        $post_data['method'] = $method;
        $post_data['format'] = 'json';
        $post_data['session'] = $this->session;
        $post_data['data'] = $this->urlSafeEncode(base64_encode($aes_start_str));
        $post_data['v'] = '2.0';
        $post_data['timestamp'] = date('Y-m-d H:i:s');
        $str = sprintf('%s%s%s%s%s%s%s%s%s',
            $this->key, $post_data['appid'], $post_data['data'], $post_data['format'], $post_data['method'],
            $post_data['session'], $post_data['timestamp'], $post_data['v'], $this->key
        );
//        Log::info($post_data['data']);
//        Log::info($str);
        $post_data['sign'] = md5($str);
        return $post_data;
    }

    // 充值接口
    public function recharge() {
        $this->checkLogin();

        $service_type = input('get.st'); // 服务类型 802快捷 803网银
        $order_no = input('get.orderid');
        $oInfo = $this->getOrderInfo($order_no);

        $subject = "recharge {$oInfo['bpprice']}";//商品名称
        $amount = strval($oInfo['bpprice'] * 100);//单位：分
        $result_url = 'http://'.$_SERVER['HTTP_HOST'] . '/index/user/index.html';
        $notify_url = 'http://'.$_SERVER['HTTP_HOST'].'/index/elt_pay/recharge_notify';

        $param = array(
            'payordernumber' => $order_no,
            'amount' => $amount,
            'Body' => $subject,
            'backurl' => $notify_url,
            'fronturl' => $result_url,
            'PayType' => '0', // 默认银联在线网关
            'SubpayType' => '01',
        );

        if ($service_type == 'quick') {// 银联在线快捷
            $param['SubpayType'] = '02';
            $bankInfo = Db::name('bankcard')->where('uid', $this->uid)->where('isdelete', '0')->find();
            if (empty($bankInfo['id'])) {
                $this->error('请先绑定银行卡');
            }
            $param['PayParams'] = array(
                'BankCard' => $bankInfo['accntno'],
                'IDCard' => $bankInfo['scard'],
                'Tel' => $bankInfo['phone'],
                'Name' => $bankInfo['accntnm'],
            );
        }

        $result = PayService::curlfun($this->payUrl, $this->getPostData('masget.pay.compay.router.font.pay', $param), 'POST');
        $result = empty($result) ? [] : json_decode($result, true);
        if (isset($result['ret']) && $result['ret'] == '0') {
            $this->redirect($result['data']);
        } else {
            $this->error(empty($result['message']) ? '充值请求失败' : $result['message']);
        }
    }

    // 充值异步回调
    public function recharge_notify() {
        // 返回字段
        $result = 'fail';
        $reqData = $this->request->post();
//        Log::info('eltpay的充值异步回调数据：' . json_encode($reqData));

        if ($reqData['Appid'] != $this->appid) {
            Log::error('eltpay的充值异步回调appid不正确！');
            return $this->display($result);
        }
        if (empty($reqData['Data']) or empty($reqData['Sign'])) {
            Log::error('eltpay的充值异步回调请求数据不正确！');
            return $this->display($result);
        }

        $sign = strtolower(md5($reqData['Data'] . $this->key));
        if ($sign != $reqData['Sign']) {
            Log::error('eltpay的充值异步回调签名不正确！');
            return $this->display($result);
        }

        $aes_data = base64_decode($this->urlSafeDecode($reqData['Data']));
        $aes_data = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $this->key, $aes_data, MCRYPT_MODE_CBC, $this->key);
        $data = empty($aes_data) ? null : json_decode(rtrim($aes_data, "\0"), true);
//        Log::info('eltpay的充值异步回调业务数据：' . $data);

        if (empty($data)) {
            Log::error('eltpay的充值异步回调业务数据为空');
            return $this->display($result);
        }

        if (empty($data['ordernumber']) or empty($data['amount']) or empty($data['respcode'])) {
            Log::error('eltpay的充值异步回调业务数据不正确！');
            return $this->display($result);
        }

        if ($data["respcode"] == '2') {
            PayService::notify_ok_dopay($data['ordernumber'], ($data['amount'] / 100));
            $result = '{"message":"成功", "response":"00"}';
            Log::info('eltpay的充值异步回调处理成功：' . $data['ordernumber']);
        } else {
            Log::error('eltpay的充值异步回调失败的信息：' . @$data['respmsg']);
        }
        return $this->display($result);
    }
}