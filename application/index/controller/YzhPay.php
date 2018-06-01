<?php
namespace app\index\controller;
use app\index\service\PayService;
use think\Controller;

// 易智慧支付
class YzhPay extends Controller {
    private $comid = 'kJPDckxgk7';///易智慧的密钥id
    private $comkey = '0pe8apsmXrvSVv0k9nXcaxm2UnoNit';///易智慧的密钥key
    private $merid = '5987';//商户号

    // 充值接口
    public function recharge() {
        $money = input('get.money');
        $service_type = input('get.st'); // 服务类型 802快捷 803网银
        $order_no = input('get.orderid');

        $post_url = 'http://www.yzhpay.com/Pay/Index/pay';///易智慧的请求地址
        $amount = strval($money*100);//单位：分
        $subject = "recharge$money";//商品名称
        $result_url = 'http://'.$_SERVER['HTTP_HOST'] . '/index/user/index.html';
        $notify_url = 'http://'.$_SERVER['HTTP_HOST'].'/index/yzh_pay/recharge_notify';
        $type = 2;//类型

        $post_data = array(
            'order_no' => $order_no,
            'mer_id' => $this->merid,
            'service_type' => $service_type,
            'order_amount' => $amount,
            'subject' => $subject,
            'sign_type'=>'md5',//验签类型
            'type'=>$type,
        );
        //md5加密
        $str = $this->linkString($post_data);
        $sign = md5($this->comid . $this->comkey. $str);

        $post_data['sign'] = $sign;
        $post_data['return_url'] = $result_url;
        $post_data['notify_url'] = $notify_url;
        $post_data['order_time'] = date('Y-m-d H:i:s');
        $return = $this->createHtml($post_data, $post_url);
        return $this->display($return);
    }

    // 充值异步回调
    public function recharge_notify($st='') {
        $order_no = $_POST['order_no'];//订单号
        $service_type = $_POST['service_type'];//服务类型
        $merOderidNum = $_POST['transaction_no'];//三方订单号
        $code = $_POST['resp_code'];//状态code
        $message = $_POST['resp_msg'];//状态信息
        $sign_type = $_POST['sign_type'];//验签类型
        $order_time = $_POST['order_time'];//订单时间
        $type = $_POST['type'];//通道类型
        $trade_status = $_POST['trade_status'];//支付状态
        $order_amount = $_POST['order_amount'];//金额(以分为单位)
        $sign = $_POST['sign'];//签值
        $subject = $_POST['subject'];//商品名称
        $amount = $order_amount/100;//金额

        $data = array(
            'order_no' => $order_no,
            'mer_id' => $this->merid,
            'service_type' => $service_type,
            'order_amount' => $order_amount,
            'subject'=>$subject,
            'sign_type' => $sign_type,
            'type' => $type
        );

        //验签
        $res = $this->publicSing($this->comid, $this->comkey, $data, $sign);
        if($res && $trade_status=='success'){
            PayService::notify_ok_dopay($order_no, $amount);
            echo 'success';
        }else{
            //验签失败
            echo 'fail';
        }
    }

    private function createHtml($params,$url){
        $encodeType = isset ( $params ['encoding'] ) ? $params ['encoding'] : 'UTF-8';
        $html='<html><head><meta http-equiv="Content-Type" content="text/html; charset='
            . $encodeType . '"/></head><body onload="javascript:document.pay_form.submit();">
			<form id="pay_form" name="pay_form" action="'.$url.'" method="post">';
        foreach ( $params as $key => $value ) {
            $html.= "<input type=\"hidden\" name=\"{$key}\" id=\"{$key}\" value=\"{$value}\" />\n";
        }
        $html.='<!-- <input type="submit" type="hidden">--></form></body></html>';
        return $html;
    }

    // 拼接字符串
    private function linkString($para,$sort=true,$encode=true){
        if($para == NULL || !is_array($para))
            return "";

        $linkString = "";
        if ($sort) {
            ksort ( $para );
        }
        foreach ($para as $key => $value) {
            if($value!=''){
                if ($encode) {
                    $value = urlencode ( $value );
                }
                $linkString .= $key . "=" . $value . "&";
            }
        }
        // 去掉最后一个&字符
        $linkString = substr ( $linkString, 0, -1 );
        return $linkString;
    }

    //验签方法
    private function publicSing($comid,$comkey,$data,$sign){
        $params_str = $this->linkString($data,true,false);
        $newsign = md5($comid.$comkey.$params_str);
        return $newsign == $sign;
    }
}
