<?php
namespace app\index\controller;
use think\Controller;
use think\Db;
use think\Cookie;

use wxpay\database\WxPayUnifiedOrder;
use wxpay\JsApiPay;
use wxpay\NativePay;
use wxpay\PayNotifyCallBack;
use think\Log;
use wxpay\WxPayApi;
use wxpay\WxPayConfig;

use alipay\wappay\buildermodel\AlipayTradeWapPayContentBuilder;
use alipay\wappay\service\AlipayTradeService;

use pinganpay\Webapp;




use pufapay\ConfigUtil;
use pufapay\HttpUtils;
use pufapay\SignUtil;
use pufapay\TDESUtil;



class Pay extends Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->parter1 = 1729;
        $this->key1 = '3177204082c74e4db0f24ae2d5290617';
        $this->parter2 = 2865;
        $this->key2 = '57a599aafd1342f8be3b31417883186f';
    }

    /**
     * 微信支付
     * @return [type] [description]
     */
    public function wxpay($data)
    {


        if (!empty($data)) {
            //获取用户openid
            $tools = new JsApiPay();
            $openId = Db::name('userinfo')->where(array('uid'=>$data['uid']))->value('openid');

            if(!$openId){
                return WPreturn('openId不存在',-1);
            }
            //统一下单
            $input = new WxPayUnifiedOrder();
            $input->setBody("会员余额充值");
            $input->setAttach("web_user_pay_ing");

            $input->setOutTradeNo($data['balance_sn']);
            $input->setTotalFee($data['bpprice'] * 100);
            $input->setTimeStart(date("YmdHis"));
            $input->setTimeExpire(date("YmdHis", time() + 600));
            $input->setGoodsTag("goods");
            $input->setNotifyUrl("http://".$_SERVER['HTTP_HOST']."/index/wechat/notifyurl/bpid/".$data['bpid']);
            $input->setTradeType("JSAPI");
            $input->setOpenid($openId);

            $order = WxPayApi::unifiedOrder($input);

            $jsApiParameters = $tools->getJsApiParameters($order);

            /*
            $this->assign('order',$order);
            $this->assign('jsApiParameters',$jsApiParameters);
            return $this->fetch('jsapi');
            */
           return $jsApiParameters;
        }

    }

    /**
     * 中云支付
     * @return [type] [description]
     */
    public function sand( )
	{
		date_default_timezone_set("Asia/Shanghai");

		require(ROOT_PATH_INDEX.'/sand/common.php');
		$pubkey = loadX509Cert(ROOT_PATH_INDEX.'/sand/'.PUB_KEY_PATH);
		if($_POST)
		{
			$str = $_POST;
			$sign = $str['sign']; //签名
			$signType = $str['signType']; //签名方式
			$data = stripslashes($str['data']); //支付数据
			$charset = $str['charset']; //支付编码
			$result = json_decode( $data, true ); //data数据
			
			if( verify($data, $sign, $pubkey ) ) 
			{
				$this->notify_ok_dopay($result['body']['orderCode'],$result['body']['totalAmount']/100);
			}
		}
		echo "respCode=000000";
	    exit;
	}
    public function zypay($data)
    {
        

    $pay_memberid = "11580";   //商户ID
    $pay_orderid = $data['balance_sn'];    //订单号
    $pay_amount = $data['bpprice'];    //交易金额
    $pay_applydate = date("Y-m-d H:i:s");  //订单时间
    $pay_bankcode = "WftZfb";   //银行编码
    $pay_notifyurl = "http://".$_SERVER['HTTP_HOST']."/index/pay/zypay_notify.html";   //服务端返回地址
    $pay_callbackurl = "http://".$_SERVER['HTTP_HOST']."/index/user/index.html";  //页面跳转返回地址
    
    $Md5key = "FYXPPTVr3Hwk5vhjZOl2kPHyT3kWoM";   //密钥
    
    $tjurl = "http://zy.cnzypay.com/Pay_Index.html";   //提交地址,如有变动请到官网下载最新接口文档
    
    $requestarray = array(
            "pay_memberid" => $pay_memberid,
            "pay_orderid" => $pay_orderid,
            "pay_amount" => $pay_amount,
            "pay_applydate" => $pay_applydate,
            "pay_bankcode" => $pay_bankcode,
            "pay_notifyurl" => $pay_notifyurl,
            "pay_callbackurl" => $pay_callbackurl,
            'tongdao' => 1
        );
        
        ksort($requestarray);
        reset($requestarray);
        $md5str = "";
        foreach ($requestarray as $key => $val) {
            $md5str = $md5str . $key . "=>" . $val . "&";
        }
        
        $sign = strtoupper(md5($md5str . "key=" . $Md5key)); 
        $requestarray["pay_md5sign"] = $sign;
        
        $str = '<form id="Form1" name="Form1" method="post" action="' . $tjurl . '">';
        foreach ($requestarray as $key => $val) {
            $str = $str . '<input type="hidden" name="' . $key . '" value="' . $val . '">';
        }
        $str = $str . '<input type="submit" value="提交">';
        $str = $str . '</form>';
        $str = $str . '<script>';
        $str = $str . 'document.Form1.submit();';
        $str = $str . '</script>';
        
        return $str;
        

    }


    public function zypay_notify()
    {
        
        $ReturnArray = array( // 返回字段
            "memberid" => $_REQUEST["memberid"], // 商户ID
            "orderid" =>  $_REQUEST["orderid"], // 订单号
            "amount" =>  $_REQUEST["amount"], // 交易金额
            "datetime" =>  $_REQUEST["datetime"], // 交易时间
            "returncode" => $_REQUEST["returncode"]
        );
      
        $Md5key = "FYXPPTVr3Hwk5vhjZOl2kPHyT3kWoM";
        //$sign = $this->md5sign($Md5key, $ReturnArray);
        
        ///////////////////////////////////////////////////////
        ksort($ReturnArray);
        reset($ReturnArray);
        $md5str = "";
        foreach ($ReturnArray as $key => $val) {
            $md5str = $md5str . $key . "=>" . $val . "&";
        }
        $sign = strtoupper(md5($md5str . "key=" . $Md5key)); 
        ///////////////////////////////////////////////////////
        if ($sign == $_REQUEST["sign"]) {
            if ($_REQUEST["returncode"] == "00") {
                   $this->notify_ok_dopay($ReturnArray['orderid'],$ReturnArray['amount']);
                   exit("ok");
            }
        }

        cache('');
    }




    public function qianbaotong($data,$pay_type,$type=0)
    {
        
        
        /*
        * 商户id，由平台分配
        */
        $parter = 1729;
        $key = '3177204082c74e4db0f24ae2d5290617';
        
        /*
        * 准备使用网银支付的银行
        */
        $type = $pay_type;
        
        /*
        * 支付金额
        */
        $value = $data['bpprice'];
        
        /*
        * 请求发起方自己的订单号，该订单号将作为平台的返回数据
        */
        $orderid = $data['balance_sn'];
        
        /*
        * 在下行过程中返回结果的地址，需要以http://开头。
        */
        $callbackurl = "http://".$_SERVER['HTTP_HOST']."/index/pay/qdb_notify.html";;
        
        /*
        * 支付完成之后平台会自动跳转回到的页面
        */
        $hrefbackurl = "http://".$_SERVER['HTTP_HOST']."/index/user/index.html";;
        
        /*
        * 商户密钥
        */
        
        

        $shidai_bank_url   = 'http://gateway.qpabc.com/bank/index.aspx';

        $url = "parter=". $parter ."&type=". $type ."&value=". $value. "&orderid=". $orderid ."&callbackurl=". $callbackurl;
        //签名
        $sign   = md5($url. $key);
        
        //最终url
        $url    = $shidai_bank_url . "?" . $url . "&sign=" .$sign. "&hrefbackurl=". $hrefbackurl;

        if(in_array($pay_type,array(1005,1006,1007))){
            return $url;
        }else{
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_TIMEOUT, 500);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_URL, $url);
         
            $res = curl_exec($curl);
            curl_close($curl);
            
            $res_arr = json_decode($res,1);
            if(isset($res_arr["retCode"]) && $res_arr["retCode"] == '0000'){
                return $res_arr["codeUrl"];
            }else{
                return false;
            }

            
        }
        

    }


    public function yinfubao_notify()
	{

		$json = file_get_contents("php://input");     
		$arr = json_decode( $json, true );
		//$arr2 = json_decode( $arr['biz_content'], true );
		$biz_content = json_encode($arr['biz_content']);
		$md5str = "biz_content=".$biz_content."&key=7dd710b78c784b319a182ef0dc63c60d";
		$sign=strtoupper(md5($md5str));		
         		
        $arr2 = json_decode( $biz_content, true );

		$orderid = $arr2["out_order_no"];
		$ovalue = $arr2["payment_fee"]/100;	
	if($sign == $arr["signature"]){
			$this->notify_ok_dopay($orderid,$ovalue);

	exit('success');
	}
      

	}


    public function qyf_notify()
	{
	//	file_put_contents( dirname(__FILE__).'/qyf_get.txt', var_export( $_GET, true  ), FILE_APPEND  );
	//	file_put_contents( dirname(__FILE__).'/qyf_post.txt', var_export( $_POST, true  ), FILE_APPEND  );
		 
		$eka_merchant_id = '45829';
		$eka_merchant_key = 'TfxoQXkGIWHOu21YTTdMRWkQOb2C7HWM7Gz26Lmk';
		$orderid = trim($_GET['orderid']);
		$returncode = trim($_GET['returncode']);
		$userid = $eka_merchant_id;
		$money	= trim($_GET['money']);
		$sign	= trim($_GET['sign']);
		$ext = trim($_GET['ext']);
		$sign_test  = "returncode=".$returncode."&userid=".$userid."&orderid=".$orderid."&money=".$money."&keyvalue=".$eka_merchant_key;
		$sign_md5 	= md5($sign_test);
		if( $sign_md5 == $_GET['sign'] ) 
		{
			 $this->notify_ok_dopay($orderid,$money);
		}
		exit('success');
	}
  
  


    public function juhe_notify()
	{
		//file_put_contents( dirname(__FILE__).'/juhe_get.txt', var_export( $_GET, true  ), FILE_APPEND  );
		//file_put_contents( dirname(__FILE__).'/juhe_post.txt', var_export( $_POST, true  ), FILE_APPEND  );	
      	//file_put_contents( dirname(__FILE__).'/juhe__log_input.txt', file_get_contents("php://input"), FILE_APPEND );
        $Md5key = 'BA891ECC582F8B3FAC5E05F8DD5AD1EF';    
		$json = file_get_contents("php://input");     
		//$json = '{"amount":204.01,"code":"520000","message":"回调支付成功","orderId":"HMZX4981UID1516864264708724","random":0.7465906262216964,"sign":"C6878C04AE7239DC344A25E66281512C","status":4}';
      $arr = json_decode( $json, true );      
    	$sign = $arr['sign'];
         unset($arr['sign']);
         unset($arr['random']);
		ksort($arr);
        reset($arr);
        $md5str = "";
        foreach ($arr as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        $sign_md5 = strtoupper(md5($md5str . "key=" . $Md5key)); 
      
		if( $sign ==$sign_md5) 
		{
			 $this->notify_ok_dopay($arr['orderId'],$arr['amount']);
         // print_r($arr['orderId']);
         // print_r($arr['amount']);
          		exit('success');

		}else{
           		exit('签名错误');

        }
      
      
	}
  
  
  


    public function juhe2_notify()
	{
		//file_put_contents( dirname(__FILE__).'/juhe222_get.txt', var_export( $_GET, true  ), FILE_APPEND  );
		//file_put_contents( dirname(__FILE__).'/juhe222_post.txt', var_export( $_POST, true  ), FILE_APPEND  );	
      	//file_put_contents( dirname(__FILE__).'/juhe222__log_input.txt', file_get_contents("php://input"), FILE_APPEND );
        $Md5key = '	8IFj65EorNE3yuo8L8ERrsPEPIbNIkpV';    
		//$json = file_get_contents("php://input");     
      
		//$arr = json_decode( $json, true );
     $merchantOutOrderNo = $_POST['merchantOutOrderNo'];
     $merid = $_POST['merid'];
     $msg = $_POST['msg'];
      		$arr = json_decode( $json, true );
	 $money = $msg['payMoney'];
     $noncestr = $_POST['noncestr'];
     $orderNo = $_POST['orderNo'];
     $payResult = $_POST['payResult'];
     $sign = $_POST['sign'];
      
      
	$requestarray = array(
            "merchantOutOrderNo" => $_POST[''],
            "merid" => $_POST[''],
            "msg" => $_POST[''],
            "noncestr" => $_POST[''],
            "orderNo" => $_POST[''],
            "payResult" => $_POST['payResult']
        );
        $md5str = "";
        foreach ($requestarray as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        $sign_md5 = md5($md5str . "key=" . $Md5key); 
      
     //	echo "<BR><BR>ARR:";print_r($arr);
     //	echo "<BR><BR>sign:";print_r($sign);
     //	echo "<BR><BR>sign_md5:";print_r($sign_md5);
      
		if( $sign ==$sign_md5) 
		{
			 $this->notify_ok_dopay($merchantOutOrderNo,$money);
          		exit('success');

		}
      
      
	}
  
  
  
    public function qdb_notify()
    {
        cache('qdb_test',$_GET);
        //$_GET = cache('qdb_test');
        //获取返回的下行数据
        
        //$sysorderid     = trim($_GET['sysorderid']);
        //$completiontime     = trim($_GET['systime']);

        //进行爱扬签名认证
        $key = '3177204082c74e4db0f24ae2d5290617';
        header('Content-Type:text/html;charset=GB2312');
        $orderid        = trim($_GET['orderid']);
        $opstate        = trim($_GET['opstate']);
        $ovalue         = trim($_GET['ovalue']);
        $sign           = trim($_GET['sign']);
        
        //订单号为必须接收的参数，若没有该参数，则返回错误
        if(empty($orderid)){
            die("opstate=-1");      //签名不正确，则按照协议返回数据
        }
        
        $sign_text  = "orderid=$orderid&opstate=$opstate&ovalue=$ovalue".$key;
        $sign_md5 = md5($sign_text);
        if($sign_md5 != $sign){
            die("opstate=-2");      //签名不正确，则按照协议返回数据
        }
        $this->notify_ok_dopay($orderid,$ovalue);
        die("opstate=0");       

    }

    
    public function alipay($data){

        $config = array (   
        //应用ID,您的APPID。
        'app_id' => "2017022705923867",

        //商户私钥，您的原始格式RSA私钥
        'merchant_private_key' => "",
        
        //异步通知地址
        'notify_url' => "http://".$_SERVER['HTTP_HOST']."/index/pay/alipay_notify.html",
        
        //同步跳转
        'return_url' => "http://".$_SERVER['HTTP_HOST']."/index/user/index.html",

        //编码格式
        'charset' => "UTF-8",

        //签名方式
        'sign_type'=>"RSA2",

        //支付宝网关
        'gatewayUrl' => "https://openapi.alipay.com/gateway.do",

        //支付宝公钥,查看地址：https://openhome.alipay.com/platform/keyManage.htm 对应APPID下的支付宝公钥。
        'alipay_public_key' => "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA4SvhwaggPK6YcT9KFcWatlWzmPOGuinPibsSuQOKOzIdndmsobx8gxYsL40SBJZJ7gUzLW53WUPJiu1Cn2K6b1m/PsOQNl6WRQD7fD62fCO5z3Wqitx9bts/LoUbX7vb4Dxpplw7KKVikUCBwe75hOTuhAfQ7dqGzbE0xfKjO2ugRBDceCy5InBK/xfvVbNRk+1DZyexLSUJx7pm5nUCkVj81URlnQYzcW06OBjvSSecTpmAktbvruZE450vhxkfDzxp47R0qba4c8ALRrDlnrUb29EPD4TFmXWGxteZQBQWKbEJWte7tV/sGW9ed/6QeC8A9N3CalnzXpqIF4hpcQIDAQAB",
        
    
);


        //商户订单号，商户网站订单系统中唯一订单号，必填
        $out_trade_no = $data['balance_sn'];

        //订单名称，必填
        $subject = '用户充值';

        //付款金额，必填
        $total_amount = $data['bpprice'];

        //商品描述，可空
        $body = '';

        //超时时间
        $timeout_express="1m";

        $payRequestBuilder = new AlipayTradeWapPayContentBuilder();
        $payRequestBuilder->setBody($body);
        $payRequestBuilder->setSubject($subject);
        $payRequestBuilder->setOutTradeNo($out_trade_no);
        $payRequestBuilder->setTotalAmount($total_amount);
        $payRequestBuilder->setTimeExpress($timeout_express);
        
        $payResponse = new AlipayTradeService($config);

        $result=$payResponse->wapPay($payRequestBuilder,$config['return_url'],$config['notify_url']);

        return $result;

    }


    /**
     * izpay
     * @author lukui  2017-08-16
     * @return [type] [description]
     */
    public function izpay_wx($data)
    {
        
        header("Access-Control-Allow-Origin: *");

        $url = 'http://www.izpay.cn:9002/thirdsync_server/third_pay_server';
        
        $para['out_trade_no'] = $data['balance_sn'];
        $para['mer_id'] = 'pay177';
        $para['goods_name'] = 'userpay';
        $para['total_fee'] = $data['bpprice']*100;
        $para['callback_url'] =  "http://".$_SERVER['HTTP_HOST']."/index/user/index.html";
        $para['notify_url'] = "http://".$_SERVER['HTTP_HOST']."/index/pay/izpay_wx_notify.html";
       
        $para['attach'] =  '709';
        $para['nonce_str'] = mt_rand(time(),time()+rand());
        $para['pay_type'] = '003';
        $key = "c71elu2cq25b5m8ks99fxhqteljugo6m";
        
        
        
        
        $sign_str = 'mer_id='.$para['mer_id'].'&nonce_str='.$para['nonce_str'].'&out_trade_no='.$para['out_trade_no'].'&total_fee='.$para['total_fee'].'&key='.$key;
        
        //echo $sign_str;
        
        $para['sign'] = md5($sign_str); 
        
        
        $str = "";
        foreach($para as $key=>$val){
        $str .= $key.'='.$val.'&';
        }
        $newstr = substr($str,0,strlen($str)-1); 
        
        $pay_url = $url.'?'.$newstr;
        
        
        $temp_data = file_get_contents($pay_url);
        $result = json_decode($temp_data,true);
        
        
        return $temp_data;
    }

    public function izpay_alipay($data)
    {
        
        header("Access-Control-Allow-Origin: *");

        $url = 'http://www.izpay.cn:9002/thirdsync_server/third_pay_server';
        
        $para['out_trade_no'] = $data['balance_sn'];
        $para['mer_id'] = 'pay177';
        $para['goods_name'] = 'userpay';
        $para['total_fee'] = $data['bpprice']*100;
        $para['callback_url'] =  "http://".$_SERVER['HTTP_HOST']."/index/user/index.html";
        $para['notify_url'] = "http://".$_SERVER['HTTP_HOST']."/index/pay/izpay_wx_notify.html";
       
        $para['attach'] =  '709';
        $para['nonce_str'] = mt_rand(time(),time()+rand());
        $para['pay_type'] = '006';
        $key = "c71elu2cq25b5m8ks99fxhqteljugo6m";
        
        
        
        
        $sign_str = 'mer_id='.$para['mer_id'].'&nonce_str='.$para['nonce_str'].'&out_trade_no='.$para['out_trade_no'].'&total_fee='.$para['total_fee'].'&key='.$key;
        
        //echo $sign_str;
        
        $para['sign'] = md5($sign_str); 
        
        
        $str = "";
        foreach($para as $key=>$val){
        $str .= $key.'='.$val.'&';
        }
        $newstr = substr($str,0,strlen($str)-1); 
        
        $pay_url = $url.'?'.$newstr;
        
        
        $temp_data = file_get_contents($pay_url);
        $result = json_decode($temp_data,true);
        
        
        return $temp_data;
    }
    
    
    public function izpay_wx_notify(){
        $data = input('');
        if(!isset($data['out_trade_no'])){
            return false;
        }
        $this->notify_ok_dopay($data['out_trade_no'],$data['total_fee']/100);
        return true;
        
    }




    public function fnf_notify()
	{
      $notify =  $_POST['notify'];			
	if($notify){
	$orderid = $_POST['out_trade_no'];
	//$r = explode( "A", $out_trade_no );
	$ovalue = $_POST['total_amount'];	
			$this->notify_ok_dopay($orderid,$ovalue);

	exit('SUCCESS');
	}	  

	}
  
    /**
     * 平安微信扫码支付
     * @author lukui  2017-08-17
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public function pingan_code($balance,$code)
    {
        $papay = new Webapp();

        $data['out_no'] = $balance['balance_sn'];
        $data['pmt_tag'] = $code;
        $data['original_amount'] = $balance['bpprice']*100;
        $data['trade_amount'] = $balance['bpprice']*100;
        $data['ord_name'] = 'userpay';
        $data['auth_code'] = "";
        $data['jump_url'] = "http://".$_SERVER['HTTP_HOST']."/index/user/index.html";;
        $data['notify_url'] = "http://".$_SERVER['HTTP_HOST']."/index/pay/panotify.html";;
        $result = $papay->api("payorder",$data);
        return $result;
    }

    public function panotify()
    {
        
        $data = $_REQUEST;

        if(isset($data['amount']) && isset($data['out_no'])){
            $this->notify_ok_dopay($data['out_no'],$data['amount']/100);
        }
        

    }




    //钱通支付
    public function qiantong_pay($data)
    {
        $gateway_url = "https://123.56.119.177:8443/pay/pay.htm";
        
        $str = '<?xml version="1.0" encoding="utf-8" standalone="no"?>
                <message application="WeiXinScanOrder" version="1.0.1"
                    timestamp="20160210111111"
                    merchantId="1002207"
                    merchantOrderId="'.$data['balance_sn'].'"
                    merchantOrderAmt="'.($data['bpprice']*100).'"
                    merchantOrderDesc="用户充值"
                    userName=""
                    payerId="'.$data['uid'].'"
                    salerId=""
                    guaranteeAmt="0"
                    merchantPayNotifyUrl="'."http://".$_SERVER['HTTP_HOST']."/index/pay/qiantong_notify.html".'"/>';


        /*****生成请求内容**开始*****/
        $strMD5 =  MD5($str,true);  
        $strsign =  $this->qt_sign($strMD5);
        $base64_src=base64_encode($str);
        $msg = $base64_src."|".$strsign;
        /*****生成请求内容**结束*****/
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $gateway_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $msg);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $tmp = explode("|", $result);
        $resp_xml = base64_decode($tmp[0]);
        
        $resp_sign = $tmp[1];
        if($this->qt_verity(MD5($resp_xml,true),$resp_sign) && $_SESSION['uid'] != 1017){//验签
            
            
            $res_arr = xml_to_array($resp_xml);
            $res_arr = $res_arr['@attributes'];
            
            if(isset($res_arr['respCode']) && $res_arr['respCode'] == 000 && isset($res_arr['codeUrl'])){
                return $res_arr['codeUrl'];
            }
        } else return WPreturn('渠道不可用',-1);
    }

    /**
     * 签名  生成签名串  基于sha1withRSA
     * @param string $data 签名前的字符串
     * @return string 签名串
     */
    public function qt_sign($data) {
        $certs = array();
        $_file = file_get_contents($_SERVER['DOCUMENT_ROOT']."/weixin/qiantong/merchant_cert.pfx");
        
        openssl_pkcs12_read($_file,$certs,"11111111"); //其中password为你的证书密码
        if(!$certs) return ;
        $signature = '';  
        openssl_sign($data, $signature, $certs['pkey']);
        return base64_encode($signature);
    }
    /**
     * 验证签名： 
     * @param data：原文 
     * @param signature：签名 
     * @return bool 返回：签名结果，true为验签成功，false为验签失败 
     */  
    public function qt_verity($data,$signature)  
    {  
        $pubKey = file_get_contents($_SERVER['DOCUMENT_ROOT']."/weixin/qiantong/server_cert.cer");  
        $res = openssl_get_publickey($pubKey);  
        $result = (bool)openssl_verify($data, base64_decode($signature), $res);  
        openssl_free_key($res);
        return $result;  
    }

    public function qiantong_kuaijie($data)
    {
        $gateway_url = "https://123.56.119.177:8443/pay/pay.htm";

        $str = '<?xml version="1.0" encoding="utf-8" standalone="no"?>
        <message application="CertPayOrderH5" guaranteeAmt="0"
            merchantFrontEndUrl="http://'.$_SERVER['HTTP_HOST'].'/index/user/index.html"
            merchantId="1002207" merchantName=""
            merchantOrderAmt="'.($data['bpprice']*100).'" merchantOrderDesc="用户充值" merchantOrderId="'.$data['balance_sn'].'"
            merchantPayNotifyUrl="'."http://".$_SERVER['HTTP_HOST']."/index/pay/qiantong_notify.html".'"
            payerId="'.$data['uid'].'" salerId="" version="1.0.1" />';



        
        /*****生成请求内容**开始*****/
        $strMD5 =  MD5($str,true);  
        $strsign =  $this->qt_sign($strMD5);
        $base64_src=base64_encode($str);
        $msg = $base64_src."|".$strsign;

        /*****生成请求内容**结束*****/
        $def_url = '<form name="ipspay" action="'.$gateway_url.'" method="post">';
        $def_url .= '<input name="msg" type="text" value="'.$msg.'" />';
        $def_url .= '</form>';

        return $def_url;

    }

  
    public function zhfh5notify()
	{
    $key = "cdfc0766dac9f0006200f87561089514";//商户密钥
	
    $sign = $_POST["sign"];
  	$signArray = array("pmt_id" => $_POST["pmt_id"], "ord_no" => $_POST["ord_no"], "trade_amount" => $_POST["trade_amount"]); //MD5验签[参与签名的字段有：pmt_id ord_no trade_amount]
  
	$signCheck = $this->zhfh5signs($signArray,$key);//签名
            
  	if($sign == $signCheck){ 
        //验证时做好签名验证与金额验证，如果不做验证直接处理的，后果自负；
    	//业务处理代码
      
		$o = explode( "AASS", $_POST["ord_no"] );
		$this->notify_ok_dopay($o[0],$_POST['trade_amount']/100);
      
       // echo "ok";//返回ok，通知回调成功
    }else{
        echo "fail";//回调失败
    }
	}
  
  
	 function zhfh5signs($array, $keys=null){
        $signature = array();
        foreach($array as $key=>$value){
            $signature[$key]=$key.'='.$value;
        }
        $signature['open_key']='open_key'.'=' . $keys;
        ksort($signature);
        #先sha1加密 在md5加密
        $sign_str = md5(sha1(implode('&', $signature)));
        return $sign_str;
    }
    
    
  
   
	
    public function zhinengyun_notify()
	{ 
		//file_put_contents( dirname(__FILE__).'/zhinengyu_________log_GET.txt', var_export( $_GET, true ), FILE_APPEND );
		//file_put_contents( dirname(__FILE__).'/zhinengyu_________log_POST.txt', var_export( $_POST, true ), FILE_APPEND );
		//file_put_contents( dirname(__FILE__).'/zhinengyu_________log_input.txt', file_get_contents("php://input"), FILE_APPEND );

$ordno = $_REQUEST["ordno"];
$orderid = $_REQUEST["orderid"];
$price = $_REQUEST["price"];
$realprice = $_REQUEST["realprice"];
$orderuid = $_REQUEST["orderuid"];
$key = $_REQUEST["key"];
$token = "267e738f7aa2e9948559b612e59bb547";


$check = md5($orderid . $orderuid . $ordno . $price . $realprice . $token);

if($key == $check){
    //如果key验证成功，并且金额验证成功，只返回success【小写】字符串；
    //业务处理代码..........

      			$this->notify_ok_dopay($orderid,$realprice );

    exit("success");//只输出success，前面不要输出任何东西，包括空格转行回车等；
}else{
    exit("fail");
}


 	}
   
  
  
   
	
    public function edf_notify()
	{ 
header('Content-Type:text/html;charset=utf8');
date_default_timezone_set('Asia/Shanghai');
$userkey='63eda7c38ec7851759be444b797cf9ed72f77a27';
		//file_put_contents( dirname(__FILE__).'/mobaonew_________log_GET.txt', var_export( $_GET, true ), FILE_APPEND );
		//file_put_contents( dirname(__FILE__).'/mobaonew_________log_POST.txt', var_export( $_POST, true ), FILE_APPEND );
		//file_put_contents( dirname(__FILE__).'/mobaonew_________log_input.txt', file_get_contents("php://input"), FILE_APPEND );
//require_once 'inc.php';
$status=$_POST['status'];
$customerid=$_POST['customerid'];
$sdorderno=$_POST['sdorderno'];
$total_fee=$_POST['total_fee'];
$paytype=$_POST['paytype'];
$sdpayno=$_POST['sdpayno'];
$remark=$_POST['remark'];
$sign=$_POST['sign'];

$mysign=md5('customerid='.$customerid.'&status='.$status.'&sdpayno='.$sdpayno.'&sdorderno='.$sdorderno.'&total_fee='.$total_fee.'&paytype='.$paytype.'&'.$userkey);

if($sign==$mysign){
    if($status=='1'){
      
      			$this->notify_ok_dopay($sdorderno,$total_fee );

        echo 'success';
    } else {
        echo 'fail';
    }
} else {
    echo 'signerr';
}
 	}

    public function zhangling_notify()
	{ 
		//file_put_contents( dirname(__FILE__).'/zhangling_notify________log_GET.txt', var_export( $_GET, true ), FILE_APPEND );
		//file_put_contents( dirname(__FILE__).'/zhangling_notify________log_POST.txt', var_export( $_POST, true ), FILE_APPEND );
		//file_put_contents( dirname(__FILE__).'/zhangling_notify_______log_input.txt', file_get_contents("php://input"), FILE_APPEND );
      
		header('Content-Type:text/html;charset=utf8');  
		$data = file_get_contents("php://input");
		$parameter = json_decode($data, true);
		$signature = $parameter["signature"];
		unset($parameter["signature"]);
		$signature_local=$this->setSignature($parameter);
		if($signature && $signature == $signature_local){	
	//$parameter["paySt"]支付结果状态，0:待支付；1:支付中；2:支付成功；3:支付失败；4:已关闭	
	if($parameter["paySt"]=='2'){
      			$this->notify_ok_dopay($parameter["mchntOrderNo"],$parameter["amount"]/100 );
      			echo "{\"success\":\"true\"}";
	} else {
        echo 'fail';
    }	
}else{
          echo 'signerr';
}
    }
 
	/**
	 * 生成签名
	 * $parameter 已排序要签名的数组
	 * $moveNull 是否清除为空的参数
	 * return 签名结果字符串
	 * php语言切记值为0的时候也要参与拼接
	 */
	function setSignature($parameter, $moveNull=true) {
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
				$signature .= "key=6b5efebb2d7f8b0fb3f0821eacfa0f85";
				$signature = md5($signature);
			}
		}
		return $signature;
	}
	   
    public function zhongnan_notify()
	{ 
		//file_put_contents( dirname(__FILE__).'/zhongnan_notify________log_GET.txt', var_export( $_GET, true ), FILE_APPEND );
		//file_put_contents( dirname(__FILE__).'/zhongnan_notify________log_POST.txt', var_export( $_POST, true ), FILE_APPEND );
		//file_put_contents( dirname(__FILE__).'/zhongnan_notify_______log_input.txt', file_get_contents("php://input"), FILE_APPEND );
      
header('Content-Type:text/html;charset=utf8');
date_default_timezone_set('Asia/Shanghai');
$customerid = "168666999001592";
$key='tnnacjeayacek0dhkhncrgkq9u90x7b6';
//require_once 'inc.php';
      
$return_code=$_POST['return_code'];
$out_trade_no=$_POST['out_trade_no'];
$trade_result=$_POST['trade_result'];
$message=$_POST['message'];
$pay_num=$_POST['pay_num'];
$total_fee=$_POST['total_fee'];
$sign=$_POST['sign'];

$mysign= strtoupper(md5($customerid.$out_trade_no.$pay_num.$total_fee.$key));

if($sign==$mysign){
    if($return_code=="10000"&&$trade_result=="success"){
      
      			$this->notify_ok_dopay($pay_num,$total_fee/100 );

        echo 'SUCCESS';
    } else {
        echo 'fail';
    }
} else {
    echo 'signerr';
}

 	}
   
  
	
    public function xzpay2_notify()
	{ 
		//file_put_contents( dirname(__FILE__).'/xzpay2_________log_GET.txt', var_export( $_GET, true ), FILE_APPEND );
		//file_put_contents( dirname(__FILE__).'/xzpay2_2________log_POST.txt', var_export( $_POST, true ), FILE_APPEND );
		//file_put_contents( dirname(__FILE__).'/xzpay2_________log_input.txt', file_get_contents("php://input"), FILE_APPEND );        
        $ReturnArray = $_POST;
  		$sign2 =  $ReturnArray["signData"];
        $Md5key = "daSwTq9WmPbV";
        unset($ReturnArray["signData"]);
        ///////////////////////////////////////////////////////
        ksort($ReturnArray);
        reset($ReturnArray);
        $md5str = "";
        foreach ($ReturnArray as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        $sign = strtoupper(md5($md5str . "key=" . $Md5key)); 
        ///////////////////////////////////////////////////////
        if ($sign == $sign2) {
            if ($ReturnArray["orderStatus"] == "01") {
              $money = $ReturnArray['orderAmount']/100;            
              
                   $this->notify_ok_dopay($ReturnArray['prdOrdNo'],$money);
                   exit("SUCCESS");
            }
        }
      
                   exit("FAIL");

    }
  
	  
	
    public function xzpay_notify()
	{ 
header('Content-Type:text/html;charset=utf8');
date_default_timezone_set('Asia/Shanghai');
		//file_put_contents( dirname(__FILE__).'/xzpay_________log_GET.txt', var_export( $_GET, true ), FILE_APPEND );
		//file_put_contents( dirname(__FILE__).'/xzpay_________log_POST.txt', var_export( $_POST, true ), FILE_APPEND );
		//file_put_contents( dirname(__FILE__).'/xzpay_________log_input.txt', file_get_contents("php://input"), FILE_APPEND );
$security_key = 'D1XJQO6LG7RPRCPIFX4QQLDKW3PCYS16';
$p1 = file_get_contents("php://input");     
$urlarr= parse_url($p1);
parse_str($urlarr['path'],$parr);
//原则是返回的数据编码是utf-8，出现乱码才需要作如下转码，否则直接以utf-8接收
//$jsonData = iconv('gbk', 'utf-8', base64_decode(str_replace(' ','+',$parr['encryptData'])));
$jsonData =base64_decode(str_replace(' ','+',$parr['encryptData']));
$jsonArr = json_decode($jsonData,true);

$sign2=strtoupper(md5 ( $jsonData .  $security_key ));
$orderNo = $jsonArr['orderNo']; 
$money = $jsonArr['transAmt'];      
      
if ($parr['signData'] == $sign2) {
// 	echo '签名正确';
	//file_put_contents('log.txt', '签名正确');
	if ($jsonArr['respCode'] == '00') {
		//file_put_contents('log.txt','支付成功');
      			$this->notify_ok_dopay($orderNo,$money );
		echo 'SUCCESS';
	}
}
else
	echo 'signerr';
 	}
 
  
	
  
    public function yinsheng_notify()
	{
		include ROOT_PATH_INDEX.'/yinsheng/config.php';
		include ROOT_PATH_INDEX.'/yinsheng/lib.php';
		$pay_order_id = $_POST['pay_order_id'];
		$pay_amount = $_POST['pay_amount'];
		$pay_remark = $_POST['pay_remark'];
		$pay_product_name = $_POST['pay_product_name'];
		$sign = $_POST['sign'];
		
		$data = array(
			"pay_order_id" => $pay_order_id,         
			"pay_amount" => $pay_amount,             
			"pay_remark" => $pay_remark,            
			"pay_product_name" => $pay_product_name 
		);	
		$chesign = $this->createSign($data,md5key);
     
		if($sign===$chesign)
		{
			  $this->notify_ok_dopay( $pay_remark, $pay_amount  );
		}	
		exit('ok');
		
	}
    public function qiantong_notify(){
        
        /******异步通知******/
        $result=file_get_contents('php://input', 'r');
        
        $tmp = explode("|", $result);
        $resp_xml = base64_decode($tmp[0]);
        
        $resp_sign = $tmp[1];
        if($this->qt_verity(MD5($resp_xml,true),$resp_sign)){//验签
        
            $resp_arr = xml_to_array($resp_xml);
        
            if(isset($resp_arr["deductList"]["item"]["@attributes"])){
                $item = $resp_arr["deductList"]["item"]["@attributes"];
                $info = $resp_arr["@attributes"];
                if(isset($item["payStatus"]) && $item["payStatus"] == '01' && isset($info["merchantOrderId"]) && isset($item["payAmt"])){
                    $this->notify_ok_dopay($info["merchantOrderId"],round($item["payAmt"]/100,2));
                }
            }
            
        } else echo '验签失败';
        
    }
    

    public function wx_wap_2($data)
    {
        

        date_default_timezone_set('PRC');
        error_reporting(0);
        set_time_limit(0);
        
        $ary = array(
            'token'=>'670b4dc044bd939552228fe114e218b7',//填写用户TOKEN
            'mod'=>'pay',//模式,pay:支付模式,list:返回所有订单信息
            'index'=>1,//模式为list的时候使用,用来指定订单当前页,订单信息每页显示10条
            'oid'=>$data['balance_sn'],//模式为list的时候使用,用来指定查询某个订单的信息
            'title'=>'用户充值',//商品名称
            'price'=>$data['bpprice']*100,//商品价格,请填写整数,以分为单位,例:1元,就填写100
            'curl'=>"http://".$_SERVER['HTTP_HOST']."/index/pay/wxwap2n.html",
            'cip'=>$_SERVER["REMOTE_ADDR"]//用户的支付IP,不可更改
        );
        
        $url = 'http://api.btjson.com/weixinpay';

        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_HEADER,0);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
        curl_setopt($ch,CURLOPT_POST,1);
        curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($ary));
        $content = curl_exec($ch);
        curl_close($ch);
        
        

        if($ary['mod'] == 'pay'){
            $json = json_decode($content,true);
            $oid = $json['data']['oid'];//返回的订单号,可存在自己的数据库中
            $_data['bpid'] = $data['bpid'];
            $_data['pay_type'] = $oid;
            db('balance')->update($_data);
            if(iswechat()){
                return $json['data']['h5url'];
            }else{
                return $json['data']['pcurl'];
            }
            /*
            if($json['data']['img'] != ''){
                header('Content-Type:image/png');
                echo base64_decode($json['data']['img']);
            }else{
                echo $json['data'];
            }*/
        }
        if($ary['mod'] == 'list'){
            return WPreturn('渠道不可用',-1);
        }
    }



    /**
     * 浦发银行
     * @author lukui  2017-09-12
     * @return [type] [description]
     */ 
    public function pfpay($data=null,$paytype=null)
    {
       
        $param['tradeType'] = 'cs.pay.submit';
        $param['version'] = '1.0';
        $param['channel'] = $paytype;
        $param['mchId'] = '000070037000000013';
        $param["body"]= 'userpay';
        $param["outTradeNo"] = $data['balance_sn'];
        $param["amount"] = $data['bpprice'];
        $param["currency"] = 'CNY';
        $param["notifyUrl"] = "http://".$_SERVER['HTTP_HOST']."/index/pay/pfrefund.html";
        $param["callbackUrl"] = "http://".$_SERVER['HTTP_HOST']."/index/user/index.html";
        
        if((ceil($param["amount"])==$param["amount"])){
            return WPreturn('充值金额请输入小数，如：100.12',-1);
        }
        
        $oriUrl = 'https://mch.one2pay.cn/cloud/cloudplatform/api/trade.html';
        
        
        $unSignKeyList = array ("sign");

        //echo  $_POST["currency"];
//      $desKey = ConfigUtil::get_val_by_key("desKey");
        $sign = SignUtil::signMD5($param, $unSignKeyList);

        $param["sign"] = $sign;
        

        $jsonStr=json_encode($param);
        
        $serverPayUrl=ConfigUtil::get_val_by_key("serverPayUrl");

        $httputil = new HttpUtils();
        list ( $return_code, $return_content )  = $httputil->http_post_data($serverPayUrl, $jsonStr);
        
        $respJson=json_decode($return_content,1);
        

        $respSign = SignUtil::signMD5($respJson, $unSignKeyList);
        
        
        
        if($respSign !=  $respJson['sign']){
            return WPreturn('验签失败！',-1);
        }else{
            if($respJson['returnCode'] == '0' && $respJson['resultCode'] == '0' && isset($respJson['payCode'])){
                return $respJson['payCode'];
                
            }else{
                return $return_content;
                
            }
            
        }
    }

    public function pfrefund()
    {
        
        $data = $_REQUEST;
        cache('pfrefund',$data);

    }


    /**
     * 秒冲宝
     * @author lukui  2017-09-18
     * @return [type] [description]
     */
    public function mcpay($data)
    {
        

        return $data;

    }


    public function mcb_notify()
    {
        $this->redirect('user/index');
        exit;
        //支付成功跳转页面
        //************************
        $myappid="2017072346";//您的APPID
        $appkey="78e872a306592f5a9e70a636325fd2c2";//您的APPKEY
        //***********************
        header("Content-Type: text/html; charset=utf-8");
        cache('mcb_not',$_REQUEST);
        if(!isset($_REQUEST['appid'])||!isset($_REQUEST['tno'])||!isset($_REQUEST['payno'])||!isset($_REQUEST['money'])||!isset($_REQUEST['typ'])||!isset($_REQUEST['paytime'])||!isset($_REQUEST['sign'])){
            exit('参数错误');
        }
        $appid=(int)$_REQUEST['appid'];
        $tno=$_REQUEST['tno'];//交易号 支付宝 微信 财付通 的交易号
        $payno=$_REQUEST['payno'];//网站充值的用户名
        $money=$_REQUEST['money'];//付款金额 
        $typ=(int)$_REQUEST['typ'];
        $paytime=$_REQUEST['paytime'];
        $sign=$_REQUEST['sign'];
        if(!$appid||!$tno||!$payno||!$money||!$typ||!$paytime||!$sign){
            exit('参数错误');
        }
        if($myappid!=$appid)exit('appid error');
        //sign 校验
        if($sign!=md5($appid."|".$appkey."|".$tno."|".$payno."|".$money."|".$paytime."|".$typ)){
            exit('签名错误');
        }
        //处理用户充值
                if($typ==1){
                    $typname='手工充值';
                }else if($typ==2){
                    $typname='支付宝充值';
                }else if($typ==3){
                    $typname='财付通充值';
                }else if($typ==4){
                    $typname='手Q充值';
                }else if($typ==5){
                    $typname='微信充值';
                }
                
                if(!$tno)exit('没有订单号');
                if(!$payno)exit('没有付款说明');

                $this->notify_ok_dopay($payno, $money);
                $this->redirect('user/index');
            //************以下代码自己写   
                //查询数据库 交易号tno是否存在  tno数据库充值表增加个字段 长度50 存放交易号
                
                //已经存在输出 存在 跳转到充值记录或其他页面 交易号唯一 
                
                //不存在 查询用户是否存在
                
                //用户存在 增加用户充值记录 写入交易号
                
                //给用户增加金额 
                
                //处理成功
    }

    public function mcbpay()
    {
        

        //软件接口配置
        $key_="JHesdekjer";//接口KEY  自己修改下 软件上和这个设置一样就行
        $md5key="538b3c39fe6db0844ba78ddfb51f3b57";//MD5加密字符串 自己修改下 软件上和这个设置一样就行
    //软件接口地址 http://域名/mcbpay/apipay.php?payno=#name&tno=#tno&money=#money&sign=#sign&key=接口KEY
    
        $getkey=$_REQUEST['key'];//接收参数key
        $tno=$_REQUEST['tno'];//接收参数tno 交易号
        $payno=$_REQUEST['payno'];//接收参数payno 一般是用户名 用户ID
        $money=$_REQUEST['money'];//接收参数money 付款金额
        $sign=$_REQUEST['sign'];//接收参数sign
        $typ=(int)$_REQUEST['typ'];//接收参数typ
        if($typ==1){
            $typname='手工充值';
        }else if($typ==2){
            $typname='支付宝充值';
        }else if($typ==3){
            $typname='财付通充值';
        }else if($typ==4){
            $typname='手Q充值';
        }else if($typ==5){
            $typname='微信充值';
        }
        
        if(!$tno)exit('没有订单号');
        if(!$payno)exit('没有付款说明');
        if($getkey!=$key_)exit('KEY错误');
        //if(strtoupper($sign)!=strtoupper(md5($tno.$payno.$money.$md5key)))exit('签名错误');
    //************以下代码自己写   
        //查询数据库 交易号tno是否存在  tno数据库充值表增加个字段 长度50 存放交易号
        //

        //$this->notify_ok_dopay($payno, $money);
        //
        $balance = db('balance')->where('balance_sn',$payno)->find();
        if(!$balance){
            $this->error('参数错误！');
        }

        

        if($balance['bptype'] != 3){
            
            exit('该订单已充值');
        }
        $_edit['bpid'] = $balance['bpid'];
        $_edit['bptype'] = 1;
        $_edit['isverified'] = 1;
        $_edit['cltime'] = time();
        $_edit['bpbalance'] = $balance['bpbalance']+$balance['bpprice'];
        $_edit['bpprice'] = $money;
        
        $is_edit = db('balance')->update($_edit);
        
        if($is_edit){
            // add money
            $_ids=db('userinfo')->where('uid',$balance['uid'])->setInc('usermoney',$money);
            if($_ids){
                //资金日志
                set_price_log($balance['uid'],1,$money,'充值','用户充值',$_edit['bpid'],$_edit['bpbalance']);
            }
            
            exit('1');
        }else{
            
            exit('该订单已充值');
        }


        //已经存在输出 存在  交易号唯一 
        
        //不存在 查询用户是否存在
        
        //用户存在 增加用户充值记录 写入交易号
        
        //给用户增加金额 
        
        //处理成功 输出1
        
    }




    /**--------------------------------------------------------------------------------
     * 一卡支付
     * @author lukui  2017-10-13
     * @param  [type] $data 订单参数
     * @param  [type] $code 支付编码
     * @return [type]       [description]
     */
    public function yikapay($data,$code)
    {
        #   产品通用接口正式请求地址
        $reqURL_onLine = "http://gaet.51zima.cn/GateWay/Bank.aspx";
            
        # 业务类型
        # 支付请求，固定值"Buy" .   
        $p0_Cmd = "Buy";
            
        #   送货地址
        $p9_SAF = "0";

        #   商户编号p1_MerId,以及密钥merchantKey 需要从商付宝网络科技易卡平台获得
        $p1_MerId           = "10875";                                                                                                      #测试使用
        $merchantKey    = "56D175839A17577B1BD996F1497A8205";       #测试使用

        $logName    = "BANK_HTML.log";


        #   商户订单号,选填.
        ##若不为""，提交的订单号必须在自身账户交易中唯一;为""时，商付宝科技会自动生成随机的商户订单号.
        $p2_Order                   = $data['balance_sn'];

        #   支付金额,必填.
        ##单位:元，精确到分.
        $p3_Amt                     = $data['bpprice'];

        #   交易币种,固定值"CNY".
        $p4_Cur                     = "CNY";

        #   商品名称
        ##用于支付时显示在商付宝科技网关左侧的订单产品信息.
        $p5_Pid                     = 'user pay';

        #   商品种类
        $p6_Pcat                    = 'class';

        #   商品描述
        $p7_Pdesc                   = 'desc';

        #   商户接收支付成功数据的地址,支付成功后商付宝科技会向该地址发送两次成功通知.
        $p8_Url                     = "http://".$_SERVER['HTTP_HOST']."/index/pay/yikarefund.html";

        #   商户扩展信息
        ##商户可以任意填写1K 的字符串,支付成功时将原样返回.                                               
        $pa_MP                      = '';

        #   支付通道编码
        ##默认为""，到商付宝网关.若不需显示普讯商付宝的页面，直接跳转到各银行、神州行支付、骏网一卡通等支付页面，该字段可依照附录:银行列表设置参数值.          
        $pd_FrpId                   = $code;

        #   应答机制
        ##默认为"1": 需要应答机制;
        $pr_NeedResponse    = "1";

        #调用签名函数生成签名串
        $hmac = getReqHmacString($p2_Order,$p3_Amt,$p4_Cur,$p5_Pid,$p6_Pcat,$p7_Pdesc,$p8_Url,$pa_MP,$pd_FrpId,$pr_NeedResponse);

        $str = <<<A
        
        
        <form name='diy' id="diy" action='$reqURL_onLine' method='post'>
        <input type='hidden' name='p0_Cmd'                  value='$p0_Cmd'>
        <input type='hidden' name='p1_MerId'                value='$p1_MerId'>
        <input type='hidden' name='p2_Order'                value='$p2_Order'>
        <input type='hidden' name='p3_Amt'                  value='$p3_Amt'>
        <input type='hidden' name='p4_Cur'                  value='$p4_Cur'>
        <input type='hidden' name='p5_Pid'                  value='$p5_Pid'>
        <input type='hidden' name='p6_Pcat'                 value='$p6_Pcat'>
        <input type='hidden' name='p7_Pdesc'                value='$p7_Pdesc'>
        <input type='hidden' name='p8_Url'                  value='$p8_Url'>
        <input type='hidden' name='p9_SAF'                  value='$p9_SAF'>
        <input type='hidden' name='pa_MP'                   value='$pa_MP'>
        <input type='hidden' name='pd_FrpId'                value='$pd_FrpId'>
        <input type='hidden' name='pr_NeedResponse'         value='$pr_NeedResponse'>
        <input type='hidden' name='hmac'                    value='$hmac'>
        </form>
        
A;
    return ($str);
             
    }

    public function yikarefund()
    {
        
        $data = input('');
        cache('yikarefund',$data);
    }




    /**********************************************客官支付开始********************************************
     * 客官支付
     * @author lukui  2017-10-13
     * @param  [type] $data [description]
     * @param  [type] $code [description]
     * @return [type]       [description]
     */
    public function keguanpay($data,$code)
    {
        
        $parter = 1946;
        $key = 'c10aea0982fe46d19d324cfc35a15449';
        $bank = $code;
        $value = $data['bpprice'];
        $orderid = $data['balance_sn'];
        $callbackurl = "http://".$_SERVER['HTTP_HOST']."/index/pay/keguanrefund.html";
        $hrefbackurl = "http://".$_SERVER['HTTP_HOST']."/index/user/index.html";
        $attach = '';

        $signText = "parter=".$parter."&bank=".$bank."&value=".$value."&orderid=".$orderid."&callbackurl=".$callbackurl.$key;


        $signInfo = md5($signText);

        $gateway = "http://api.ecoopay.com/Bank/index.aspx";
        $parameter = "parter=".$parter."&bank=".$bank."&value=".$value."&orderid=".$orderid."&callbackurl=".$callbackurl."&hrefbackurl=".$hrefbackurl."&sign=".$signInfo."&attach=".$attach;

        $gourl = $gateway.'?'.$parameter;
        return $gourl;

    }

    public function keguanrefund()
    {
        
        $parter = $_REQUEST['parter'];
        $orderid = $_REQUEST['orderid'];
        $opstate = $_REQUEST['opstate'];
        $paymoney = $_REQUEST['paymoney'];
        $sysnumber = $_REQUEST['sysnumber'];
        $attach = $_REQUEST['attach'];
        $sign = $_REQUEST['sign'];

        //$parter = 1946;
        $key = 'c10aea0982fe46d19d324cfc35a15449';

        $signText = "parter=".$parter."&orderid=".$orderid."&opstate=".$opstate."&paymoney=".$paymoney.$key;
        $signInfo = strtolower(md5($signText));

        if($signInfo != strtolower($sign)){
            echo '签名错误';
            exit;
        }

        // 进行订单成功的业务逻辑处理
        // 处理完成后返回给平台opstate=0的标识
        // 平台为了保存数据的完成性会多次发送通知，请做好重复性判断
        $this->notify_ok_dopay($orderid,$paymoney);
        echo 'opstate=0';
    }



    //**********************************************客官支付结束********************************************


    public function evepay($data,$code)
    {

        $open_id = "2017111506";//商户号
        $pay_url = "http://www.boxuy.cn/pay/pay";//支付提交地址
        $key = "263def3d20e403effea4143172eeff59";//商户密钥

        $wx_appid = "";//微信appid，可为空；
        $tag = $data['balance_sn'];//订单标记，订单附加数据
        $time = time();//当前时间戳
        $type = 2;//支付类型，1 web网页支付 2 返回json参数 H5类的支付不支持本参数
        $out_no = $data['balance_sn'];//订单号
        $ord_name = '用户充值';//订单名称（描述）
        $pmt_tag = $code; //支付类型 2 阿里h5支付 3 微信h5支付 4 阿里二维码支付 5 微信二维码支付 6 快捷支付 7 银行网关
        $remark = $time;//订单备注
        $original_amount = $data["bpprice"] * 100;//原始交易金额（以分为单位，没有小数点）
        $trade_amount = $data["bpprice"] * 100;//实际交易金额（以分为单位，没有小数点）
        $notify_url = "http://".$_SERVER['HTTP_HOST']."/index/pay/zypay_notify.html";//异步通知地址
        $jump_url = "http://".$_SERVER['HTTP_HOST']."/index/user/index.html";//支付结果跳转地址

        $arr = array("open_id" => $open_id,"time" => $time,"tag" => $tag,"notify_url" => $notify_url,"jump_url" => $jump_url,"wx_appid" => $wx_appid,"out_no" => $out_no,"ord_name" => $ord_name,"pmt_tag" => $pmt_tag,"remark" => $remark,"original_amount" => $original_amount,"trade_amount" =>$trade_amount, "type"=>$type);
        
        $sign = $this -> evesigns($arr,$key);//签名
        $arr['sign'] = $sign;

        if($code == 3){
            $res = "<form name= 'payForm' action='$pay_url' method= 'post' >
            <input type='hidden' name='open_id' value='$open_id'/><br/>
            <input type='hidden' name='time' value='$time'/><br/>
            <input type='hidden' name='tag' value='$tag'/><br/>
            <input type='hidden' name='notify_url' value='$notify_url'/><br/>
            <input type='hidden' name='jump_url' value='$jump_url'/><br/>
            <input type='hidden' name='wx_appid' value='$wx_appid'/><br/>
            <input type='hidden' name='out_no' value='$out_no'/><br/>
            <input type='hidden' name='ord_name' value='$ord_name'/><br/>
            <input type='hidden' name='pmt_tag' value='$pmt_tag'/><br/>
            <input type='hidden' name='remark' value='$remark'/><br/>
            <input type='hidden' name='original_amount' value='$original_amount'/><br/>
            <input type='hidden' name='trade_amount' value='$trade_amount'/><br/>
            <input type='hidden' name='sign' value='$sign'/><br/>
            <input type='hidden' name='type' value='$type'/><br/>
        </form>";
        }else{
            $api = controller('Api');
            $res = $api->curlfun($pay_url,$arr,'POST');
        }
        

        return ($res);


    }

    public function evesigns($array, $keys=null){
        $signature = array();
        foreach($array as $key=>$value){
            $signature[$key]=$key.'='.$value;
        }
        $signature['open_key']='open_key'.'=' . $keys;
        ksort($signature);
        #先sha1加密 在md5加密
        $sign_str = md5(sha1(implode('&', $signature)));
        return $sign_str;
    }















    

    








    public function notify_ok_dopay($order_no,$order_amount)
    {
        
        if(!$order_no || !$order_amount){
            
            return false;
        }

        $balance = db('balance')->where('balance_sn',$order_no)->find();
        if(!$balance){
            
            return false;
        }

        if($balance['bpprice'] != $order_amount){
            
            return false;
        }

        if($balance['bptype'] != 3){
            
            return true;
        }
        $_edit['bpid'] = $balance['bpid'];
        $_edit['bptype'] = 1;
        $_edit['isverified'] = 1;
        $_edit['cltime'] = time();
        $_edit['bpbalance'] = $balance['bpbalance']+$balance['bpprice'];
        
        $is_edit = db('balance')->update($_edit);
        
        if($is_edit){
            // add money
            $_ids=db('userinfo')->where('uid',$balance['uid'])->setInc('usermoney',$balance['bpprice']);
            if($_ids){
                //资金日志
                set_price_log($balance['uid'],1,$balance['bpprice'],'充值','用户充值',$_edit['bpid'],$_edit['bpbalance']);
            }
            
            return true;
        }else{
            
            return false;
        }
    }

    public function test_not()
    {
        dump(cache('yikarefund'));
    }
    public function test_not_clear()
    {
        cache('yikarefund',null);
    }
}
?>