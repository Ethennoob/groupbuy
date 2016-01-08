<?php
/**
 * 一元购系统---用户类
 * @authors 凌翔 (553299576@qq.com)
 * @date    2015-11-24 14:58:35
 * @version $Id$
 */

namespace AppMain\controller\Api;
use \System\BaseClass;

class UserController extends Baseclass {
    /**
     * checklogin
     */
    public function checklogin(){
            if (empty($_SESSION['userInfo']['openid'])) {
                $this->R('','90005');//跳//getOpenID
            }else{
                $this->R(['user_id'=>$_SESSION['userInfo']['userid']]);//进网站,返回user_id
            }
    }
    /**
     * 授权
     */
    public function getOpenID(){
        $weObj = new \System\lib\Wechat\Wechat($this->config("WEIXIN_CONFIG"));
        $this->weObj = $weObj;
        if (empty($_GET['code']) && empty($_GET['state'])) {
            $callback = getHostUrl();
            $reurl = $weObj->getOauthRedirect($callback, "1");
            redirect($reurl, 0, '正在发送验证中...');
            exit(); 
        } elseif (intval($_GET['state']) == 1) {
                $accessToken = $weObj->getOauthAccessToken();
                $userInfo=$this->getUserInfo($accessToken);
                // 是否有用户记录
                $isUser = $this->table('user')->where(["openid" => $accessToken['openid'],"is_on"=>1])->get(null, true);
                /*var_dump($isUser);exit();*/
                
                if ($isUser==null) {
                    //没有此用户跳转至输入注册的页面
                    header("LOCATION:".getHost()."/register.html");
                }else{
                $userID=$isUser['id'];
                $updateUser = $this->table('user')->where(['id'=>$userID])->update([
                    'last_login'=>time(),
                    'last_ip'=>ip2long(getClientIp()),
                    'nickname'=>$userInfo['nickname'],
                    'user_img'=>$userInfo['headimgurl']]
                    );
                $_SESSION['userInfo']=[
                    'openid'=>$isUser['openid'],
                    'userid'=>$isUser['id'],
                    'nickname'=>$isUser['nickname'],
                    'user_img'=>$isUser['user_img'],
                ];
                header("LOCATION:".getHost());//进入网站成功
                }
            }
        }
    /**
     * 微信个人中心菜单获取用户信息
     */
    public function getCenter(){
        $weObj = new \System\lib\Wechat\Wechat($this->config("WEIXIN_CONFIG"));
        $this->weObj = $weObj;
        if (empty($_GET['code']) && empty($_GET['state'])) {
            $callback = getHostUrl();
            $reurl = $weObj->getOauthRedirect($callback, "1");
            redirect($reurl, 0, '正在发送验证中...');
            exit(); 
        } elseif (intval($_GET['state']) == 1) {
                $accessToken = $weObj->getOauthAccessToken();
                 
                // 是否有用户记录
                $isUser = $this->table('user')->where(["openid" => $accessToken['openid'],'is_on'=>1])->get(null, true);
                /*var_dump($isUser);exit();*/
                
                if ($isUser==null) {
                    //没有此用户跳转至输入注册的页面
                    header("LOCATION:".getHost()."/register.html");
                }else{
                $userID=$isUser['id'];
                
                $updateUser = $this->table('user')->where(['id'=>$userID])->update(['last_login'=>time(),'last_ip'=>ip2long(getClientIp())]);
                $_SESSION['userInfo']=[
                    'openid'=>$isUser['openid'],
                    'userid'=>$isUser['id'],
                    'nickname'=>$isUser['nickname'],
                    'user_img'=>$isUser['user_img'],
                ];
                header("LOCATION:".getHost()."/personal.html");//进入网站成功
                }
            }
        }
    /**
     * 新用户从微信注册
     */
    public function getNewOpenID(){
        $weObj = new \System\lib\Wechat\Wechat($this->config("WEIXIN_CONFIG"));
        $this->weObj = $weObj;
        if (empty($_GET['code']) && empty($_GET['state'])) {
            $callback = getHostUrl();
            $reurl = $weObj->getOauthRedirect($callback, "1");
            redirect($reurl, 0, '正在发送验证中...');
            exit(); 
        } elseif (intval($_GET['state']) == 1) {
                $accessToken = $weObj->getOauthAccessToken();
                    $mobile = $_GET['phone'];
                    $user = $this->table('user')->where(['is_on'=>1,'phone'=>$mobile])->get(['id'],true);
                    if(!$user){
                        //用户信息
                        $userInfo=$this->getUserInfo($accessToken);
                        $saveUser=$this->saveUser($userInfo,$mobile);//插入新会员数据
                        if (!$saveUser) {
                            $this->R('','40001');
                        }
                        header("LOCATION:".getHost()."/Api/User/getOpenID");//
                    }else{
                        $this->R('','70000');//手机已注册
                    }
                }else{
            //用户取消授权
            $this->R('','90006');
        }
    }
    /**
     * 发送验证码
     */
    public function setCode(){
            $mobile = $_GET['phone'];
            $sendMessage = new \System\AppTools();
            $code= $sendMessage->generateMsgAuthCode();
            setcookie("verify",$code,time()+300,'/');
            $content = "您好！一元购注册的验证码为".$code;
            $sendMessage= $sendMessage->sendSms($mobile,$content);
            //$sendMessage= $sendMessage->sendSms(15521155161,$content);
            if (!$sendMessage) {
                $this->R('',40001);
            }
        }
    /**
     * 查询手机号码是否已注册
     */
    public function checkPhone(){
        $this->V(['phone'=>['mobile']]);
        $mobile = intval($_POST['phone']);
        //验证码验证
            if (!isset($_COOKIE['verify'])) {
                //验证码已过期
                $this->R('','90009');
            }else{
                $this->V(['verify'=>[]]);
                $code = intval($_POST['verify']);
                if ($code!=$_COOKIE['verify']) {
                    //验证码错误
                    $this->R('','90008');
                }else{
        $user = $this->table('user')->where(['is_on'=>1,'phone'=>$mobile])->get(['id'],true);
            if($user){
                $this->R('','70000');//手机已注册
            }
        $this->R();
            }
        }
    }
    /**
     * 获取用户信息
     */
    private function getUserInfo($user){

        $user_info = $this->weObj->getOauthUserinfo($user['access_token'], $user['openid']);

        if (!$user_info){
            die("系统错误，请稍后再试！");
        }

        //是否关注
        $isFollow=$this->weObj->getUserInfo($user['openid']);
        if ($isFollow['subscribe']==1){
            $user_info['is_follow']=1;
        }
        else{
            $user_info['is_follow']=0;
        }

        return $user_info;
    }
    /**
     * 保存用户
     */
    private function saveUser($user_info,$mobile){

        $data = array(
            'openid' => $user_info['openid'],
            'phone' =>$mobile,
            'user_img' => $user_info['headimgurl'],
            'nickname' => $user_info['nickname'],
            'is_follow'=>$user_info['is_follow'],
            'add_time' => time()
        );
        $result=$this->table('user')->save($data);
        if (!$result){
            die("系统错误，请稍后再试！");
        }

        return $data;
    }
    
    /**
     * 判定是否能去购买(微信支付之前的判断)
     */
    public function purchaseReady(){

        $rule = [
                    'goods_id'    =>['egNum'],
                    'user_id'     =>['egNum'],
                    'num'         =>['egNum'],
                ];
                $this->V($rule); 

            $goods_id    = $_POST['goods_id'];
            $user_id     = $_POST['user_id'];
            $num         = $_POST['num'];

            $good = $this->table('goods')->where(['id'=>$goods_id])->get(['limit_num'],true);
            if(!$good){
                $this->R('',90001);
            }
            //判断是否超过限购数
            if ($num>$good['limit_num']) {
                $this->R('',90001);
            }
            //判断是否卖完了
            $code = $this->table('code')->where(['goods_id'=>$goods_id,'is_use'=>0])->get(['id'],true);
            if(!$code){
                $this->R('',90001);
            }
            //判断是否已经买过并且超过限购数量
            $limit = $this->table('purchase')->where(['user_id'=>$user_id,'goods_id'=>$goods_id,'is_on'=>1])->get(['id'],false);
            $count = count($limit);
            if ($count+$num>$good['limit_num']) {
                $this->R('',90001);
            }
            
            $this->R(); 
    }   
    /**
     * 用户的购买记录,订单列表(分页)     
     * user_id
     * 缩略图，商品标题，价格(总须人次)，已购买人次
     */
    public function billList(){

        $this->V(['user_id'=>['egNum',null,true]]);
        $id = intval($_POST['user_id']);
        $pageInfo = $this->P();
        //调用Helper类
        $dataClass=$this->H('Bill');
        $where = 'A.is_on = 1 and A.user_id='.$id;
        $order='A.add_time desc';
        $billList=$dataClass->getbillList(null,null,null,false,$order);
        $billList=$this->getOnePageData($pageInfo, $dataClass, 'getBillList','getBillListListLength',[$where,null,null,false,$order],true);
        if($billList){
            foreach ($billList as $k => $v) {
            $good = $this->table('goods')->where(['id'=>$v['goods_id']])->get(['goods_album'],true);
            $ImgUrl=explode(';', $good['goods_album']);
            $billList[$k]['goods_img'] = $ImgUrl[0];
            unset($billList[$k]['goods_album'] );
            }
        }else{
            $billList=false;
        }
        $this->R(['billList'=>$billList,'page'=>$pageInfo]);
    }
    /**
     * 订单详情
     * goods_id
     * user_id
     */
    public function billOneDetail(){

        $this->V(['bill_id'=>['egNum',null,true]]);
        $id = intval($_POST['bill_id']);

        $dataClass=$this->H('Bill');
        $where = 'A.is_on = 1 and A.id='.$id;
        
        $billOneDetail=$dataClass->getbillList($where,null,null,true);
        if ($billOneDetail) {
            $good = $this->table('goods')->where(['id'=>$billOneDetail['goods_id']])->get(['goods_album'],true);
            $ImgUrl=explode(';', $good['goods_album']);
            $billOneDetail['goods_img'] = $ImgUrl[0];
            unset($billOneDetail['goods_album'] );
        }else{
            $billOneDetail=null;
        }
        
        //返回数据，参见System/BaseClass.class.php方法
        $this->R(['billOneDetail'=>$billOneDetail]);
    }
    

    public function getExpress(){
        $this->V(['logistics_number'=>['egNum',null,true]]);
        $id = $_POST['logistics_number'];
        $express = new \System\lib\Express\Express();
        $expressdetail = $express->getorder($id);
        if ($expressdetail['state'] == 3) {
            $updateExpress = $this->table('bill')->where(['logistics_number'=>$id])->update(['status'=>5]);
        }else{
            $updateExpress = $this->table('bill')->where(['logistics_number'=>$id])->update(['status'=>4]);
        }
        $this->R(['expressdetail'=>$expressdetail]);
    }
    /////////////////////////////模拟购买数据接口/////////////////////勿删除
    /**
     * 购买商品(微信支付)
     */
    public function purchase(){

        $rule = [
                    'thematic_id' =>['egNum'],
                    'goods_id'    =>['egNum'],
                    'user_id'     =>['egNum'],
                    'num'         =>['egNum'],
                ];
                $this->V($rule); 

            $thematic_id = $_POST['thematic_id'];
            $goods_id    = $_POST['goods_id'];
            $user_id     = $_POST['user_id'];
            $num         = $_POST['num'];

            $good = $this->table('goods')->where(['id'=>$goods_id])->get(['limit_num'],true);
            if(!$good){
                $this->R('',70009);
            }
            //判断是否超过限购数
            if ($num>$good['limit_num']) {
                $this->R('',90001);
            }
            //判断是否卖完了
            $code = $this->table('code')->where(['goods_id'=>$goods_id,'is_use'=>0])->get(['id'],true);
            if(!$code){
                $this->R('',90001);
            }
            //判断是否已经买过并且超过限购数量
            $limit = $this->table('purchase')->where(['user_id'=>$user_id,'goods_id'=>$goods_id,'is_on'=>1])->get(['id'],false);
            $count = count($limit);
            if ($count+$num>$good['limit_num']) {
                $this->R('',90001);
            }
            //分配认购码给用户,生成购物流水单
            $roll = $this->generateCodeToUser($user_id,$goods_id,$thematic_id,$num);

            //生成购买记录
            $data = array(
                'goods_id' => $goods_id,
                'thematic_id' =>$thematic_id,
                'user_id' => $user_id,
                'num' => $num,
                'add_time' => time()
                );
            $record = $this->table('record')->save($data);
                if(!$record){
                    $this->R('',40001);
                }
            $this->R(); 
    }   
    /**
     * 分配认购码给用户
     * $user_id,$goods_id,$thematic_id,$num
     */
    private function generateCodeToUser($user_id,$goods_id,$thematic_id,$num){
        $codenum = $this->table('code')->where(['is_on'=>1,'is_use'=>0,'goods_id'=>$goods_id])->order("rand()")->limit($num)->get(['code'],false);
        $count = count($codenum);
            for ($i=0; $i < $count; $i++) { 
                //$code = $this->table('code')->where(['is_on'=>1,'is_use'=>0,'goods_id'=>$goods_id])->order("rand()")->get(['code'],true);
                $data['code'] = $codenum[$i]['code'];
                $data['user_id'] = $user_id;
                $data['goods_id'] = $goods_id;
                $data['thematic_id'] = $thematic_id;
                $data['add_time'] = time();
                $purchase = $this->table('purchase')->save($data);
                if(!$purchase){
                    $this->R('',40001);
                }
                $codeupdate = $this->table('code')->where(['code'=>$data['code']])->update(['is_use'=>1,'user_id'=>$user_id,'update_time'=>time()]);
                if(!$codeupdate){
                    $this->R('',40001);
                }
            }
    }
    
}