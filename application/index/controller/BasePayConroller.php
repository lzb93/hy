<?php
namespace app\index\controller;
use think\Controller;
use think\Db;

class BasePayConroller extends Controller {
    protected function fetch($template = '', $vars = [], $replace = [], $config = []) {
        $replace['__HOME__'] = str_replace('/index.php','',\think\Request::instance()->root()).'/static/index';
        return $this->view->fetch($template, $vars, $replace, $config);
    }

    protected function checkLogin() {
        if (!isset($_SESSION['uid'])){
            $this->redirect('login/login?token=' . md5(time()));
        }
        $this->uid = $_SESSION['uid'];
    }

    protected function getOrderInfo($oid) {
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

    // 排序key，获取待加密字符串
    protected function getSignStr($para, $sort=true) {
        if ($para == NULL || !is_array($para)) {
            return "";
        }

        $linkString = "";
        if ($sort) {
            ksort ( $para );
        }
        foreach ($para as $key => $value) {
            $linkString .= $key . "=" . $value . "&";
        }
        // 去掉最后一个&字符
        $linkString = substr ( $linkString, 0, -1 );
        return $linkString;
    }
}