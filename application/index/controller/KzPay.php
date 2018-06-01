<?php
namespace app\index\controller;
use app\common\AesUtil;
use app\index\service\PayService;
use think\Controller;
use think\Db;
use think\Log;

// 坤泽支付
class KzPay extends Controller {
    private $key = '39S88877D11KX49J';// 密钥key
    private $merid = '958230088292237312';// 商户号

    protected function fetch($template = '', $vars = [], $replace = [], $config = []) {
        $replace['__HOME__'] = str_replace('/index.php','',\think\Request::instance()->root()).'/static/index';
        return $this->view->fetch($template, $vars, $replace, $config);
    }

    private function checkLogin() {
        if (!isset($_SESSION['uid'])){
            //$this->error('请先登录！','index.php/index/user/login',1,1);
            $this->redirect('login/login?token=' . md5(time()));
        }
        $this->uid = $_SESSION['uid'];
    }

    private function getOrderInfo($oid) {
        $wh = array();
        $wh['balance_sn'] = $oid;
        $wh['isverified'] = 0;
        $wh['uid'] = $this->uid;
        $wh['bptype'] = 3; // 1充值成功,3正在充值,0提现,2后台改动

        $oInfo = Db::name('balance')->field('uid,bpprice')->where($wh)->find();
        if (empty($oInfo['uid'])) {
            $this->error('订单号错误或者已经充值完成');
        }
        return $oInfo;
    }

    private function getReqData($param, $method) {
        ksort($param);
        $post_string = '';
        foreach ($param as $key => $value) {
            if ($value !== '') {
                $post_string .= $key . '=' . $value . '&';
            }
        }
        $post_string = substr($post_string, 0, -1);
        $aes = new AesUtil($this->key);
        $post_data = array();
        $post_data['method'] = $method; // 请求的方法名 必填 区分不同的请求
        $post_data['appKey'] = $this->merid;
        $post_data['v'] = '1.0';
        $post_data['format'] = 'json';
        $post_data['params'] = $aes->encrypt(base64_encode($post_string));
        $post_data['sign'] = $this->sha1Sign($post_data);
        return $post_data;
    }

    // h5充值接口
    public function recharge_h5() {
        $this->checkLogin();

        $order_no = input('get.orderid');
        $oInfo = $this->getOrderInfo($order_no);
        $money = $oInfo['bpprice'];

        $amount = strval($money);//单位：元
        $subject = "recharge$money";//商品名称
        $result_url = 'http://'.$_SERVER['HTTP_HOST'] . '/index/user/index.html';
        $notify_url = 'http://'.$_SERVER['HTTP_HOST'].'/index/kz_pay/recharge_notify';

        $param = array(
            'orgOrderNo' => $order_no,
            'subject' => $subject,
            'amount' => $amount,
            'notifyUrl' => $notify_url,
            'tranTp' => '0',
            'bizType' => '2', // 通道标识 0-未知 1-SDK 2-h5 3-html 4-自定义控件
            'source' => 'QQZF', // // 订单付款方式: WXZF:微信,ZFBZF:支付宝，QQZF :QQ支付
            'returnUrl' => $result_url,
        );

        $post_data = $this->getReqData($param, 'pay');
        $post_url = 'http://pay.xmkzry.com/hfive/pay';// 请求地址
        $this->assign('postData', $post_data);
        $this->assign('postUrl', $post_url);
        return $this->fetch('./pay/pay_form_submit');
    }

    // 扫码充值接口
    public function recharge_scan() {
        $this->checkLogin();

        $order_no = input('get.orderid');
        $oInfo = $this->getOrderInfo($order_no);
        $money = $oInfo['bpprice'];

        $amount = strval($money);//单位：元
        $subject = "recharge$money";//商品名称
        $result_url = 'http://'.$_SERVER['HTTP_HOST'] . '/index/user/index.html';
        $notify_url = 'http://'.$_SERVER['HTTP_HOST'].'/index/kz_pay/recharge_notify';

        $data = array();
        $data['subject'] = $subject;
        $data['amount'] = $amount;
        $data['notifyUrl'] = $notify_url;
        $data['orgOrderNo'] = $order_no;
        $data['returnUrl'] = $result_url;  //前端回调地址
        $data['source'] = 'QQZF';  //支付方式 WXZF:微信,ZFBZF:支付宝，QQZF :QQ支付
        $data['tranTp'] = '0'; //0表示D0  1表示D1  2表示T0  3表示T1

        $post_data = $this->getReqData($data, 'scanPay');
        $post_url = 'http://api.xmkzry.com/router';// 请求地址
        $reqResult = PayService::curlfun($post_url, $post_data, 'POST');
//        Log::info('kz扫码请求返回：' . $reqResult);

//            $imgUrl = '/juhepay/qrcode.php?text=' . $codeUrl[1];
        $reqResult = empty($reqResult) ? null : json_decode($reqResult, true);
        if (empty($reqResult)) {
            $this->error('扫码充值请求返回空');
        }
        if (empty($reqResult['errorCode']) or $reqResult['errorCode'] != '200' or empty($reqResult['data'])) {
            $this->error('扫码充值请求错误：' . @$reqResult['message']);
        }
        $imgUrl = $reqResult['data']['qrCode'];
        $this->assign('code_url', 'http://pan.baidu.com/share/qrcode?w=200&h=200&url=' . $imgUrl);
        return $this->fetch('./pay/pay_scan');
    }

    // 快捷充值-获取预下单的信息
    public function recharge_kj() {
        $this->checkLogin();
        $order_no = input('get.orderid');
        $accInfo = Db::name('bankcard')->where('uid', $this->uid)->where('isdelete', 0)->find();
        if (empty($accInfo['id'])) {
            $this->error('请先绑定银行卡');
        }

        $oInfo = $this->getOrderInfo($order_no);
        $money = $oInfo['bpprice'];

        $amount = strval($money);//单位：元
        $notifyUrl = 'http://'.$_SERVER['HTTP_HOST'] . '/index/kz_pay/recharge_notify';

        $param = array(
            'orderId' => $order_no,
            'cerNumber' => $accInfo['scard'], //身份证号码
            'cardByName' => $accInfo['accntnm'], //开户名
            'amount' => $amount,
            'cardType' => '0',  //0借记卡 1信用卡
            'mobile' => $accInfo['phone'],
            'cardByNo' => $accInfo['accntno'], //银行卡号
            'payBackUrl' => $notifyUrl,
            'tranTp' => '3', // 0表示D0  1表示D1  2表示T0  3表示T1
        );
//        Log::info('kz预下单请求:' . json_encode($param));
        $post_data = $this->getReqData($param, 'getPayCode');
        $post_url = 'http://api.xmkzry.com/router';// 请求地址
        $reqResult = $this->requestCurl($post_url, $post_data);
//        Log::info('kz预下单请求返回：' . $reqResult);
        $reqResult = !$reqResult ? null : json_decode($reqResult, true);
        if (empty($reqResult)) {
            return $this->error('kz快捷发送短信请求返回空');
        }

        if (empty($reqResult['errorCode']) or $reqResult['errorCode'] != 200) {
            return $this->error('kz快捷发送短信请求失败：' . @$reqResult['message']);
        }

        if (empty($reqResult['data']) or empty($reqResult['data']['memberId'])) {
            return $this->error('kz快捷发送短信失败：' . @$reqResult['data']['message']);
        }

        $postUrl = 'http://'.$_SERVER['HTTP_HOST'].'/index/kz_pay/recharge_kj_do';
        $this->assign('postData', $reqResult['data']);
        $this->assign('postUrl', $postUrl);
        return $this->fetch('./pay/pay_form');
    }

    // 快捷下单支付
    public function recharge_kj_do() {
        $this->checkLogin();

        $memberId = input('post.memberId');
        $order_no = input('post.orderId');
        $contractId = input('post.contractId');
        $supOrderId = input('post.supOrderId');
        $code = input('post.smsCode');
        if (empty($order_no) or empty($memberId) or empty($code)) {
            $this->error('快捷下单参数错误');
        }

        $oInfo = $this->getOrderInfo($order_no);
        $money = $oInfo['bpprice'];
        $amount = strval($money);//单位：元

        $param = array(
            'memberId' => $memberId,
            'orderId' => $order_no,
            'supOrderId' => $supOrderId,
            'contractId' => $contractId,
            'amount' => $amount,
            'checkCode' => $code,  // 短信验证码
            'cardType' => '0', // 0借记卡 1信用卡
        );
        $post_data = $this->getReqData($param, 'quickPayment');
        $post_url = 'http://api.xmkzry.com/router';// 请求地址

        $reqResult = $this->requestCurl($post_url, $post_data);
        Log::info('kz快捷下单请求返回：' . $reqResult);

        $reqResult = !$reqResult ? null : json_decode($reqResult, true);
        if (empty($reqResult)) {
            return $this->error('kz快捷下单请求返回空');
        }

        if (empty($reqResult['errorCode']) or $reqResult['errorCode'] != 200) {
            return $this->error('kz快捷下单请求失败：' . @$reqResult['message']);
        }

        if (empty($reqResult['data'])) {
            return $this->error('kz快捷下单失败：' . @$reqResult['data']['message']);
        }

        $code = isset($reqResult['data']['code']) ? $reqResult['data']['code'] : null;
        if ($code == null) {
            $code = isset($reqResult['data']['code']) ? $reqResult['data']['oriRespCode'] : 'FF';
            $code = 'FF' == $code ? 0 : 1;
        } else {
            $code = ('200' == $code || '0000' == $code) ? 1 : 0;
        }
        $message = empty($reqResult['data']['message']) ? @$reqResult['data']['oriRespMsg'] : $reqResult['data']['message'];
        if ($code == 1) {
            return $this->success();
        } else {
            return $this->error('kz快捷下单失败：' . $message);
        }
    }

    // 充值异步回调
    public function recharge_notify() {
        $reqData = $this->request->getInput();
        $reqData = !$reqData ? null : json_decode($reqData, true);
        if (empty($reqData)) {
            Log::error('kzpay 异步回调返回空');
            return $this->display('error');
        }

        $aes = new AesUtil($this->key);
        $transData = $reqData['transData'];
        $data = $transData ? $aes->decrypt($transData) : null;
        $data = $data ? base64_decode($data) : null;
        Log::info('kzpay 异步回调返回transData中的数据：' . $data);

        if (empty($data)) {
            Log::error('kzpay 异步回调返回空');
            return $this->display('empty');
        }

        $data = $this->paramToArray($data);
        $order_no = $data['reqMsgId'];//订单号
        $outOrderNo = $data['smzfMsgId'];//三方订单号
        $amount = $data['totalAmount'];//金额(以分为单位)
        $reqAppId = $data['merchantCode'];
        $reqSign = $data['sign'];

        if (empty($amount) or empty($order_no) or empty($reqSign)) {
            Log::error('kzpay 异步回调返回数据不正确');
            return $this->display('error');
        }

        if (empty($reqAppId) or $reqAppId != $this->merid) {
            Log::error('kzpay 异步回调返回应用编号不正确：' . $reqAppId);
            return $this->display('error');
        }

        // 验签
        unset($data['sign']);
        $mySign = $this->sha1Sign($data);
        if ($reqSign != $mySign) {
            Log::error('kzpay 异步回调签名错误');
            return $this->display('error');
        }

        $orderStatus = $data['isClearOrCancel']; // 0:支付成功,1:支付失败
        if($orderStatus == '0'){
            PayService::notify_ok_dopay($order_no, $amount);
            Log::info('kzpay 异步回调处理成功订单完成：' . $order_no);
            return $this->display('0000');
        } else {
            Log::info('kzpay 异步回调处理失败订单完成：' . $order_no);
            return $this->display('fail');
        }
    }

    private function sha1Sign($data) {
        $secKey = $this->key;
        ksort($data);
        $res = $secKey;
        foreach ($data as $key=>$val) {
            $res .= $key . $val;
        }
        $res .= $secKey;
        $res = sha1($res, true);
        return strtoupper(bin2hex($res));
    }

    private function paramToArray($data) {
        $params = explode('&', $data);
        $res = array();
        foreach ($params as $param) {
            if (empty($param)) {
                continue;
            }
            $vals = explode('=', $param);
//            if (empty($vals) or count($vals) != 2) {
//                continue;
//            }
            $res[$vals[0]] = (isset($vals[1]) && $vals[1] != '') ? $vals[1] : 'null';
        }
        return $res;
    }

    private function requestCurl($url, $data) {
        $post_string = '';
        foreach ($data as $key => $value) {
            if ($value !== '') {
                $post_string .= $key . '=' . $value . '&';
            }
        }
        $post_string = substr($post_string, 0, -1);
//        $post_string = http_build_query($data);
        $ch = curl_init();
        // 设置curl允许执行的最长秒数
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_URL,$url);
        //发送一个常规的POST请求。
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,$post_string);
        curl_setopt($ch, CURLOPT_HEADER,0);//是否需要头部信息（否）
        // 执行操作
        $result = curl_exec($ch);
        if($result){
            curl_close($ch);
        }else{
            $err_str=curl_error($ch);
            iecho($err_str);
            curl_close($ch);
        }
        #返回数据
        return $result;
    }
}
