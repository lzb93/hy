<?php
namespace app\index\controller;
use app\index\service\PayService;
use think\Controller;
use think\Log;

// 掌灵移动支付
class ZlydPay extends Controller {
    private $payName = 'zlydpay ';
    private $key = '6b5efebb2d7f8b0fb3f0821eacfa0f85';// 密钥key
    private $merid = '0000002188';   // 商户号
    private $pubKey = '-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQC0dYM3DXkVg9q+WcNjBPWaUwKo
eRMrwdE4p4F6fiztv/Ys6F5AxGCbFW5UfbtbQavMp9Rrg3+8mJ5/Lp8sjf471NFe
6EvbCcVwJ63Q6fA4xVyCAE7mQdfAlpCk9WKN7Qa/HqwO/OM6JDyOyycnjnNi3f3K
2tK/JbWd/SHYOSMEDQIDAQAB
-----END PUBLIC KEY-----';

    protected function fetch($template = '', $vars = [], $replace = [], $config = []) {
        $replace['__HOME__'] = str_replace('/index.php','',\think\Request::instance()->root()).'/static/index';
        return $this->view->fetch($template, $vars, $replace, $config);
    }

    // 充值接口
    public function recharge()
    {
        $money = input('get.money');
        $st = input('get.st'); // 服务类型
        $order_no = input('get.orderid');
        if (empty($money) or empty($order_no) or floatval($money) <= 0) {
            return $this->display('参数错误！');
        }

        $h5_post_url = 'http://trans.palmf.cn/sdk/api/v1.0/cli/order_h5/0';// 请求地址
        $scan_post_url = 'http://trans.palmf.cn/sdk/api/v1.0/cli/order_api/0';// 扫码请求地址

        $subject = "recharge $money";//商品名称
        $result_url = 'http://' . $_SERVER['HTTP_HOST'] . '/index/user/index.html';
        $notify_url = 'http://' . $_SERVER['HTTP_HOST'] . '/index/zlyd_pay/recharge_notify';

        $parameter = array(
            "amount" => $money * 100,//[必填]订单总金额，单位(分)
            "appid" => $this->merid,//[必填]//交易发起所属app
            "body" => $subject,//[必填]商品描述
            "clientIp" => $this->request->ip(),//[必填]客户端IP
            "cpChannel" => "",//CP分发渠道
            "currency" => "",//币种，默认RMB
            "description" => "",//订单附加描述
            "expireMs" => "",//过期时间毫秒数，默认24小时过期
            "extra" => "",//附加数据，以键值对形式存放，例如{"key":"value"}
            "mchntOrderNo" => $order_no,//[必填]商户订单号，必须唯一性
            "notifyUrl" => $notify_url,//[必填]订单支付结果异步通知地址，用于接收订单支付结果通知，必须以http或https开头
            "payChannelId" => empty($st) ? '' : $st,//支付渠道id
            "returnUrl" => $result_url,//[必填]订单支付结果同步跳转地址，用于同步跳转到商户页面，必须以http或https开头
            "subject" => "onlinepay",//[必填]商品名称
            "version" => "h5_NoEncrypt",//接口版本号，值为h5_NoEncrypt时,则明天平台返回商户参数时，不进行RSA加密
        );

        if ($st == '2000000003' || $st == '2100000001') { // QQ扫码支付、微信扫码支付
            $parameter['version'] = 'api_NoEncrypt';
            $orderInfo = $this->getOrderInfo($parameter);
            $post_data['orderInfo'] = $orderInfo;
            Log::record($this->payName . '扫码预下单请求参数：' . $orderInfo, Log::DEBUG);
            $reqResult = PayService::curlfun($scan_post_url, $post_data, 'POST');
            Log::record($this->payName . '扫码预下单返回：' . $reqResult, Log::DEBUG);
            $reqResult = empty($reqResult) ? null : json_decode($reqResult, true);
            if (empty($reqResult) or empty($reqResult['respCode'])) {
                return $this->error('扫码预下单请求返回空', $result_url);
            } elseif ($reqResult['respCode'] != '200') {
                return $this->error($reqResult['respCode'] . '-' . @$reqResult['respMsg'] , $result_url);
            }

            // code_url=https://xxx&code_img_url=http://trans.palmf.cn/xxxxxx
            $r = explode('&', $reqResult['extra']);
            $codeUrl = explode('=', $r[0]);
//            $imgUrl = '/juhepay/qrcode.php?text=' . $codeUrl[1];
            $imgUrl = explode('=', $r[1]); // 微信、支付宝、qq扫码有code_img_url
            $this->assign('code_url', $imgUrl[1]);
            return $this->fetch('./pay/pay_scan');
        } else {
            $orderInfo = $this->getOrderInfo($parameter);
            $post_data['orderInfo'] = $orderInfo;

            $this->assign('postData', $post_data);
            $this->assign('postUrl', $h5_post_url);
            return $this->fetch('./pay/pay_form_submit');
        }
    }

    // 充值异步回调
    public function recharge_notify($st='') {
        $data = file_get_contents("php://input");
        $returnArray = json_decode($data, true);
        $result = 'fail';
        if (empty($returnArray)) {
            Log::error($this->payName . '的充值异步回调请求数据为空');
            return $this->display($result);
        }

        Log::info($this->payName . '的充值异步回调数据:' . $data);
        if (empty($returnArray['mchntOrderNo']) or empty($returnArray['amount']) or empty($returnArray["signature"])) {
            Log::error($this->payName . '的充值异步回调请求数据不正确！');
            return $this->display($result);
        }

        $signature = $returnArray["signature"];
        unset($returnArray["signature"]);
        $signature_local = $this->setSignature($returnArray);
        if ($signature != $signature_local) {
            Log::error($this->payName . '的充值异步回调签名不正确！');
        } else if($returnArray["paySt"] == '2') {
            //$parameter["paySt"]支付结果状态，0:待支付；1:支付中；2:支付成功；3:支付失败；4:已关闭
            PayService::notify_ok_dopay($returnArray['mchntOrderNo'], $returnArray['amount'] / 100);
            $result = '{"success":"true"}';
        }
        return $this->display($result);
    }

    public function scan_recharge($post_url, $parameter) {

    }

    /**
     * 生成签名
     * $parameter 已排序要签名的数组
     * $moveNull 是否清除为空的参数
     * return 签名结果字符串
     * php语言切记值为0的时候也要参与拼接
     */
    private function setSignature($parameter, $moveNull=true) {
        $signature="";
        if(is_array($parameter)){
            ksort($parameter);
            foreach($parameter as $k=>$v){
                if($moveNull){
                    if($v!=="" && !is_null($v)){
                        $signature .= $k."=".$v."&";
                    }
                }else{
                    $signature .= $k."=".$v."&";
                }
            }
            if($signature){
                $signature .= "key=" . $this->key;
                $signature = md5($signature);
            }
        }

        return $signature;
    }

    /**
     * 生成POST传递值
     * $parameter 已排序要签名的数组
     * return 生成的字符串
     */
    private function setPostValue($parameter) {
        $orderInfo=array();
        if(is_array($parameter)){
            $parameter["signature"] = $this->setSignature($parameter);
            foreach($parameter as $k=>$v){
                if($v!=="" && !is_null($v)){
                    $orderInfo[$k] = $v;
                }
            }
        }
        $orderInfo = json_encode($orderInfo,JSON_UNESCAPED_UNICODE);
        return $orderInfo;
    }

    /**
     * 获取加密后的参数数据
     * $parameter 已排序要签名的数组
     * return 加密后的字符串
     */
    private function getOrderInfo($parameter) {
        $crypto="";
        $orderInfo = $this->setPostValue($parameter);
//        $itppay_cert = file_get_contents("itppay_cert.pem");
        $publickey = openssl_pkey_get_public($this->pubKey);
        foreach(str_split($orderInfo, 117) as $chunk){
            openssl_public_encrypt($chunk, $encryptData, $publickey);
            $crypto .= $encryptData;
        }
        $crypto = base64_encode($crypto);
        return $crypto;
    }
}