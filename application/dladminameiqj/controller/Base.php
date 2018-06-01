<?php
namespace app\dladminameiqj\controller;
use app\common\Constant;
use think\Controller;
use think\Db;

class Base extends Controller
{
    public function __construct(){
		parent::__construct();

		//session_unset();
		//验证登录
		if(!isset($_SESSION['userid'])){
			$this->error('请先登录！','login/login',1,1);
		}

		if(empty($_SESSION['token']) || $_SESSION['token'] != md5($_SESSION['userid'] . Constant::ADM_LOGIN_KEY)) {
			$this->redirect('login/logout');
		}

		$request = \think\Request::instance();
		
		$contrname = $request->controller();
        $actionname = $request->action();
        
        $this->assign('contrname',$contrname);
        $this->assign('actionname',$actionname);

        $this->otype = $_SESSION['otype'];
        $this->uid = $_SESSION['userid'];

        $this->assign('otype',$this->otype);
	}

	protected function addOpLog($opUid, $desc, $toUid='') {
		Db::name('op_log')->insert(array(
			'op_uid'=>$opUid, 'op_ip'=>'',
			'to_uid'=>$toUid, 'desc'=>$desc, 'create_time'=>date('Y-m-d H:i:s'),
		));
	}

	protected function fetch($template = '', $vars = [], $replace = [], $config = [])
    {
    	$replace['__ADMIN__'] = str_replace('/index.php','',\think\Request::instance()->root()).'/static/admin';
        return $this->view->fetch($template, $vars, $replace, $config);
    }
}
