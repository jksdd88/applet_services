<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@dodoca.com
 * Date: 2017/8/30
 * Time: 14:50
 */
namespace App\Services;

use App\Models\WeixinInfo;
use App\Models\WeixinFormId;
use App\Models\WeixinMsgMerchant;
use App\Models\WeixinMsgTemplate;
use App\Services\WeixinService;
use App\Utils\Weixin\MsgTemplate;
use App\Utils\Weixin\OfficialMsgTemplate;
use Cache;

class WeixinMsgService
{
    private $appid;

    public $orderurl = 'pages/order/detail/detail?id=';
    public $vipurl   = 'pages/user/vip/vip';
    private $color = '#333333';
    public $tpldata = [
        1 => [
            'id'=>'AT0008',
            'type'  => 1,
            'title'=>'待付款提醒',
            'data'=>[ 'no' => '订单号',  'name' => '物品名称', 'status' => '支付状态', 'price' => '待付金额', 'tip' => '支付提醒'],
            'url'=>'pages/order/detail/detail?id='
        ],
        2 => [
            'id'=>'AT0009',
            'type'  => 1,
            'title'=>'订单支付成功通知',
            'data'=>[  'no' => '订单编号',  'name' => '商品名称',  'price' => '支付金额',  'time' => '支付时间',   'tip' => '支付方式' ],
            'url'=>'pages/order/detail/detail?id='
        ],
        3 => [
            'id'=>'AT0007',
            'type'  => 1,
            'title'=>'订单发货提醒',
            'data'=>[  'company' => '快递公司',  'number' => '快递单号',   'time' => '发货时间',  'no' => '订单编号', 'name' => '商品名称'  ],
            'url'=>'pages/order/detail/detail?id='
        ],
        4 => [
            'id'=>'AT0051',
            'type'  => 1,
            'title'=>'拼团成功通知',
            'data'=>[  'name' => '活动名称',  'success_time' => '成团时间',  'create_time' => '开团时间',   'number' => '成团人数',   'show_price' => '拼团价',  'no' => '订单号', 'price' => '订单金额',  'delivery' => '发货时间'   ],
            'url'=>'pages/order/detail/detail?id='
        ],
        5 => [
            'id'=>'AT0557',
            'type'  => 1,
            'title'=>'会员升级通知',
            'data'=>[ 'current' => '当前会员等级',  'validity_time' => '有效期',  'level' => '专属特权'  ],
            'url'=>'pages/user/vip/vip'
        ],
        6 => [
            'id'=>'AT0104',
            'type'  => 1,
            'title'=>'预约成功通知',
            'data'=>[ 'no' => '订单号',   'name' => '预约服务',  'time' => '预约时间',  'remark' => '预约内容'  ],
            'url'=>'pages/order/detail/detail?id='
        ],
        7 => [
            'id'=>'AT1199',
            'type'  => 1,
            'title'=>'券到期提醒',
            'data'=>['type' => '卡券类别',  'time' => '到期时间',  'remark' => '备注'  ],
            'url'=>'pages/card/detail/detail?id='
        ],
        9 => [
            'id'=>'AT0988',
            'type'  => 1,
            'title'=>'预约到店提醒',
            'data'=>['item' => '预约项目',  'time' => '预约时间',  'store' => '门店' ,'address'=>'地址' ,'phone'=>'电话', 'remark' => '备注'],
            'url'=>'pages/order/detail/detail?id='
        ],
        10 => [
            'id'=>'AT0276',
            'type'  => 1,
            'title'=>'信息提交成功通知',
            'data'=>[ 'content'=>'信息内容','time'=>'提交时间' ],
            'url'=>'pages/form/detail/detail?id='
        ],
        11 => [
            'id'=>'AT0485',
            'type'  => 1,
            'title'=>'新成员加入通知',
            'data'=>[ 'remark'=>'温馨提示','username'=>'用户名','phone'=>'手机号','time'=>'加入时间' ],
            'url'=>'pages/twitter/center/center?shareStatus=1'
        ],
        12 => [
            'id'=>'AT0157',
            'type'  => 1,
            'title'=>'账户余额提醒',
            'data'=>['change'=>'变动金额','time'=>'变动时间','type'=> '变动类型' ],
            'url'=>'pages/twitter/center/center?shareStatus=1'
        ],
        13 => [
            'id'=>'AT0043',
            'type'  => 1,
            'title'=>'日程提醒',
            'data'=>['name'=>'活动名称','time'=>'开始时间','remark'=> '备注' ],
            'url'=>'pages/goods/detail/detail?id='
        ],

        61 => [
            'id' => 'OPENTM409521500',
            'type'  => 2,
            'title' => '订单待发货提醒',
            'data'  => ['first'=>'您有一个新的待发货订单','keyword1'=>'订单号','keyword2'=>'订单金额','keyword3'=>'买家','keyword4'=>'订单状态','remark'=>'客户已付款，尽快发货吧'],
            'url'   => ''
        ],
        62 => [
            'id' => 'OPENTM407277862',
            'type'  => 2,
            'title' => '退款申请通知',
            'data' => ['first'=>'您的买家“xxx”发起退款，请尽快处理','keyword1'=>'订单编号','keyword2'=>'退款金额','keyword3'=>'申请原因','remark'=>'请及时联系对方核实并协商处理'],
            'url'   => ''
        ],
        63 => [
            'id' => 'OPENTM406392238',
            'type'  => 2,
            'title' => '订单自提通知',
            'data' => ['first'=>'您有一笔新的上门自提订单，请尽快备货','keyword1'=>'订单金额','keyword2'=>'商品详情','keyword3'=>'自提门店','keyword4'=>'门店地址','keyword5'=>'订单编号','remark'=>'感谢使用，祝生活愉快！'],
            'url'   => ''
        ],
        64 => [
            'id' => 'OPENTM408171323',
            'type'  => 2,
            'title' => '预定成功通知',
            'data' => ['first'=>'您有一笔新的服务预约订单，请提前准备','keyword1'=>'客户姓名','keyword2'=>'客户手机','keyword3'=>'提交时间','keyword3'=>'预定详情','remark'=>'感谢使用，祝生活愉快！'],
            'url'   => ''
        ],
        65 => [
            'id' => 'OPENTM405485000',
            'type'  => 2,
            'title' => '提现申请通知',
            'data' => ['first'=>'您好，您有一笔新的提现申请，请及时处理','keyword1'=>'昵称','keyword2'=>'时间','keyword3'=>'金额','keyword4'=>'方式','remark'=>'感谢使用，祝生活愉快！'],
            'url'   => ''
        ],
        66 => [
            'id' => 'OPENTM411367650',
            'type'  => 2,
            'title' => '申请加入通知',
            'data' => ['first'=>'您好，您有新用户申请成为你的推客，请及时处理','keyword1'=>'真实姓名','keyword2'=>'申请时间','remark'=>'感谢使用，祝生活愉快！'],
            'url'   => ''
        ],
        67 => [
            'id' => 'OPENTM405584202',
            'type'  => 2,
            'title' => '虚拟商品支付通知',
            'data' => ['first'=>'您好，您有一笔新的虚拟商品订单','keyword1'=>'订单编号','keyword2'=>'商品名称','keyword3'=>'商品数量','keyword4'=>'支付金额','remark'=>'感谢使用，祝您生活愉快！'],
            'url'   => ''
        ]
     ];

    public function __construct()
    {

    }

    /**
     * @name  待付款提醒
     * @param $data array    ['merchant_id'=>'商户id','member_id'=>'会员id','appid'=>'appid','orderid'=>'订单id', 'no' => '订单号','name' => '物品名称','status' => '支付状态','price' => '代付款金额','tip' => '支付提醒' ]
     * @return  array
     */
    public function payPending($data){
        $type        = 1 ;
        $orderid     = (int)$data['orderid'];
        $merchant_id = (int)$data['merchant_id'];
        $member_id   = (int)$data['member_id'];
        $appid       = (string)$data['appid'];
        if($orderid < 1 || $merchant_id < 1 || $member_id < 1 || empty($appid)){
            return ['errcode'=>1,'errmsg'=>'param error'] ;
        }
        $page = $this->tpldata[$type]['url'].$orderid;
        return $this->action($type,$data,$merchant_id,$member_id,$appid,$page);
    }

    /**
     * @name 订单支付成功通知
     * @param $data array    ['merchant_id'=>'商户id','member_id'=>'会员id','appid'=>'appid','orderid'=>'订单id','no' => '订单编号','name' => '物品名称','price' => '支付金额','time' => '支付时间','tip' => '支付方式']
     * @return  array
     */
    public function PaySuccess($data){
        $type        = 2 ;
        $orderid     = (int)$data['orderid'];
        $merchant_id = (int)$data['merchant_id'];
        $member_id   = (int)$data['member_id'];
        $appid       = (string)$data['appid'];
        if($orderid < 1 || $merchant_id < 1 || $member_id < 1 || empty($appid)){
            return ['errcode'=>1,'errmsg'=>'param error'] ;
        }
        $page = $this->tpldata[$type]['url'].$orderid;
        return $this->action($type,$data,$merchant_id,$member_id,$appid,$page);
    }

    /**
     * @name 订单发货提醒
     * @param $data array   ['merchant_id'=>'商户id','member_id'=>'会员id','appid'=>'appid','orderid'=>'订单id','company' => '快递公司','number' => '快递单号','time' => '发货时间','no' => '订单编号','name' => '商品名称']
     * @return  array
     */
    public function shipment($data){
        $type        = 3 ;
        $orderid     = (int)$data['orderid'];
        $merchant_id = (int)$data['merchant_id'];
        $member_id   = (int)$data['member_id'];
        $appid       = (string)$data['appid'];
        if($orderid < 1 || $merchant_id < 1 || $member_id < 1 || empty($appid)){
            return ['errcode'=>1,'errmsg'=>'param error'] ;
        }
        $page = $this->tpldata[$type]['url'].$orderid;
        return $this->action($type,$data,$merchant_id,$member_id,$appid,$page);
    }

    /**
     * @name 拼团成功通知
     * @param $data array   ['merchant_id'=>'商户id','member_id'=>'会员id','appid'=>'appid','orderid'=>'订单id','name' => '活动名称','success_time' => '成团时间','create_time' => '开团时间','number' => '成团人数','show_price' => '拼团价格','no' => '订单编号','price' => '订单金额','delivery' => '发货时间']
     * @return  array
     */
    public function spellGroup($data){
        $type        = 4 ;
        $orderid     = (int)$data['orderid'];
        $merchant_id = (int)$data['merchant_id'];
        $member_id   = (int)$data['member_id'];
        $appid       = (string)$data['appid'];
        if($orderid < 1 || $merchant_id < 1 || $member_id < 1 || empty($appid)){
            return ['errcode'=>1,'errmsg'=>'param error'] ;
        }
        $page = $this->tpldata[$type]['url'].$orderid;
        return $this->action($type,$data,$merchant_id,$member_id,$appid,$page);
    }

    /**
     * @name 会员升级通知
     * @param $data array  ['merchant_id'=>'商户id','member_id'=>'会员id','appid'=>'appid','current' => '当前等级','validity_time' => '有效期开始','level' => '专属特权']
     * @return  array
     */
    public function upgrade($data){
        $type        = 5 ;
        $merchant_id = (int)$data['merchant_id'];
        $member_id   = (int)$data['member_id'];
        $appid       = isset($data['appid'])? $data['appid'] : '';
        if($merchant_id < 1 || $member_id < 1){
            return ['errcode'=>1,'errmsg'=>'param error'] ;
        }
        $page = $this->tpldata[$type]['url'];
        return $this->action($type,$data,$merchant_id,$member_id,$appid,$page,'DESC');
    }

    /**
     * @name 预约成功通知
     * @param $data array ['merchant_id'=>'商户id','member_id'=>'会员id','appid'=>'appid','no' => '订单号','name' => '预约服务','time' => '预约时间','remark' => '预约内容']
     * @return  array
     */
    public function bespeak($data){
        $type        = 6 ;
        $orderid     = (int)$data['orderid'];
        $merchant_id = (int)$data['merchant_id'];
        $member_id   = (int)$data['member_id'];
        $appid       = (string)$data['appid'];
        if($orderid < 1 || $merchant_id < 1 || $member_id < 1 || empty($appid)){
            return ['errcode'=>1,'errmsg'=>'param error'] ;
        }
        $page = $this->tpldata[$type]['url'].$orderid;
        return $this->action($type,$data,$merchant_id,$member_id,$appid,$page);
    }

    /**
     * @name 券到期提醒
     * @param $data array ['merchant_id'=>'商户id','member_id'=>'会员id','appid'=>'','id' => '优惠券id', 'type' => '卡券类别',  'time' => '到期时间',  'remark' => '备注'  ]
     * @return  array
     */
    public function couponRemark($data){
        $type        = 7 ;
        $id          = (int)$data['id'];
        $merchant_id = (int)$data['merchant_id'];
        $member_id   = (int)$data['member_id'];
        $appid       = (string)$data['appid'];
        if($id < 1 || $merchant_id < 1 || $member_id < 1 ){
            return ['errcode'=>1,'errmsg'=>'param error'] ;
        }
        $page = $this->tpldata[$type]['url'].$id;
        return $this->action($type,$data,$merchant_id,$member_id,$appid,$page);
    }

    /**
     * @name 活动加入成功提醒
     * @param $data array ['merchant_id'=>'商户id','member_id'=>'会员id','appid'=>'','id' => '优惠券id',  'title'=>'活动标题' , 'ctime'=>'创建时间','content'=>'活动内容','time'=>'活动时间','remind'=>'温馨提示' ]
     * @return  array
     */
    public function coupon($data){
        $type        = 8 ;
        $id          = (int)$data['id'];
        $merchant_id = (int)$data['merchant_id'];
        $member_id   = (int)$data['member_id'];
        $appid       = (string)$data['appid'];
        if($id < 1 || $merchant_id < 1 || $member_id < 1 ){
            return ['errcode'=>1,'errmsg'=>'param error'] ;
        }
        $page = $this->tpldata[$type]['url'].$id;
        return $this->action($type,$data,$merchant_id,$member_id,$appid,$page);

    }

    /**
     * @name 预约到店提醒
     * @param $data array ['merchant_id'=>'商户id','member_id'=>'会员id','appid'=>'','orderid' => '订单id',  'item' => '预约项目',  'time' => '预约时间',  'store' => '门店' ,'address'=>'地址' ,'phone'=>'电话', 'remark' => '备注' ]
     * @return  array
     */
    public function appointment($data){
        $type        = 9 ;
        $orderid     = (int)$data['orderid'];
        $merchant_id = (int)$data['merchant_id'];
        $member_id   = (int)$data['member_id'];
        $appid       = (string)$data['appid'];
        if($orderid < 1 || $merchant_id < 1 || $member_id < 1 || empty($appid)){
            return ['errcode'=>1,'errmsg'=>'param error'] ;
        }
        $page = $this->tpldata[$type]['url'].$orderid;
        return $this->action($type,$data,$merchant_id,$member_id,$appid,$page);
    }

    /**
     * @name 信息提交成功通知
     * @param $data array ['merchant_id'=>'商户id','member_id'=>'会员id','appid'=>'','id' => '表单ID','feedback_id'=>'反馈id', 'content'=>'信息内容','time'=>'提交时间']
     * @return  array
     */
    public function submit($data){
        $type        = 10 ;
        $id          = (int)$data['id'];
        $feedback_id = (int)$data['feedback_id'];
        $merchant_id = (int)$data['merchant_id'];
        $member_id   = (int)$data['member_id'];
        $appid       = (string)$data['appid'];

        if($id < 1 || $merchant_id < 1 || $member_id < 1 || $feedback_id < 1 || empty($appid)){
            return ['errcode'=>1,'errmsg'=>'param error'] ;
        }
        $page = $this->tpldata[$type]['url'].$id.'&feedback_id='.$feedback_id;
        return $this->action($type,$data,$merchant_id,$member_id,$appid,$page);
    }

    /**
     * @name 新成员加入通知
     * @param $data array ['merchant_id'=>'商户id','member_id'=>'会员id','appid'=>'','remark'=>'温馨提示','username'=>'用户名','phone'=>'手机号','time'=>'加入时间' ]
     * @return  array
     */
    public function newUser($data){
        $type        = 11 ;
        $merchant_id = (int)$data['merchant_id'];
        $member_id   = (int)$data['member_id'];
        $appid       = (string)$data['appid'];
        if($merchant_id < 1 || $member_id < 1 || empty($appid)){
            return ['errcode'=>1,'errmsg'=>'param error'] ;
        }
        $page = $this->tpldata[$type]['url'];
        return $this->action($type,$data,$merchant_id,$member_id,$appid,$page);
    }

    /**
     * @name 账户余额提醒
     * @param $data array ['merchant_id'=>'商户id','member_id'=>'会员id','appid'=>'','change'=>'变动金额','time'=>'变动时间','type'=> '变动类型']
     * @return  array
     */
    public function balance($data){
        $type        = 12 ;
        $merchant_id = (int)$data['merchant_id'];
        $member_id   = (int)$data['member_id'];
        $appid       = (string)$data['appid'];
        if($merchant_id < 1 || $member_id < 1 || empty($appid)){
            return ['errcode'=>1,'errmsg'=>'param error'] ;
        }
        $page = $this->tpldata[$type]['url'];
        return $this->action($type,$data,$merchant_id,$member_id,$appid,$page);
    }
    /**
     * @name 活动提醒
     * @param $data array ['merchant_id'=>'商户id','member_id'=>'会员id','appid'=>'','id'=>'商品id','name'=>'活动名称','time'=>'开始时间','remark'=> '备注']
     * @return  array
     */
    public function activity($data){
        $type        = 13 ;
        $merchant_id = (int)$data['merchant_id'];
        $member_id   = (int)$data['member_id'];
        $appid       = (string)$data['appid'];
        $id          = (int)$data['id'];
        if($merchant_id < 1 || $member_id < 1 || empty($appid)){
            return ['errcode'=>1,'errmsg'=>'param error'] ;
        }
        $page = $this->tpldata[$type]['url'].$id;
        return $this->action($type,$data,$merchant_id,$member_id,$appid,$page);
    }

    /**
     * @name 消息结构
     * @return array
     */
    private function typeToData($type,$content){
        $data =  $this->tpldata;
        $keyword = 0;
        $response   = [];
        foreach ($data[$type]['data'] as $k => $v) {
            if(empty($content[$k])){
                $keyword = -1 ;
                break;
            }else{
                $keyword++;
                $response['keyword'.$keyword] = ['value'=>$content[$k],'color'=>$this->color];
            }
        }
        return $keyword == -1 ? false : $response  ;
    }
    /**
     * @name 发送消息
     * @return array
     */
    public function action($type,$data,$merchant_id,$member_id,$appid,$page,$order=''){
        $content = $this->typeToData($type,$data);
        if($content === false){
            return ['errcode'=>1,'errmsg'=>'msg param error'] ;
        }
        $forminfo = WeixinFormId::get_one($merchant_id,$member_id,$appid,$order);
        if(empty( $forminfo['id'])){
            return ['errcode'=>1,'errmsg'=>'formid null'] ;
        }
        $msginfo = WeixinMsgTemplate::get_one($merchant_id,$forminfo['appid'],$type);
        if(!$msginfo['id']){
            return ['errcode'=>1,'errmsg'=>'Msg Template null'] ;
        }
        $MsgTemplate = new MsgTemplate();
        $MsgTemplate->setAccessToken((new WeixinService())->getAccessToken($msginfo['appid']));
        $response =  $MsgTemplate->sendMsg($forminfo['open_id'],$msginfo['template_id'],$forminfo['formid'],$content,$page);
        if($response['errcode'] != 0){
            $logdate = ['open_id'=>$forminfo['open_id'],'template_id'=>$msginfo['template_id'],'formid'=>$forminfo['formid'],'content'=>$content,'page'=>$page,'type'=>$type];
            (new WeixinService())->setLog('template_msg_send',$logdate,$response,$merchant_id,$appid);
            if($response['errcode'] == 40037){
              return ['errcode'=>1,'errmsg'=>'template_id error'] ;
            }else if($response['errcode'] == 41028 || $response['errcode'] == 41029){
                WeixinFormId::update_decrement($forminfo['id']);
              return ['errcode'=>1,'errmsg'=>'form_id error'] ;
            }else{
              return ['errcode'=>1,'errmsg'=>'send error'] ;
            }
        }else{
            WeixinFormId::update_decrement($forminfo['id']);
            return ['errcode'=>0,'errmsg'=>'ok'] ;
        }
        //40037	template_id不正确
        //41028	form_id不正确，或者过期
        //41029	form_id已被使用
        //41030	page不正确
        //45009	接口调用超过限额（目前默认每个帐号日调用限额为100万）
    }
    /**
     * @name 删除FormId
     * @return array
     */
    public function delete(){
        WeixinFormId::delete_data();
    }

    //=================================== 公众账号 ===================================
    /**
     * @name 订单待发货提醒(公众账号)
     * @param $data array   ['merchant_id'=>'商户id','no'=>'订单号','price' => '订单金额','buyer' => '买家','status' => '订单状态']
     * @return  array
     */
    public function pendingDelivery($data){
        $type = 61 ;
        if(empty($data['merchant_id']) ||  empty($data['no']) || empty($data['price']) ||  empty($data['buyer']) || empty($data['status'])){
            return ['errcode'=>1,'errmsg'=>'param null'];
        }
        $content = $this->tpldata[$type]['data'];
        $content['keyword1'] =  $data['no']  ;
        $content['keyword2'] =  $data['price']   ;
        $content['keyword3'] =  $data['buyer']   ;
        $content['keyword4'] =  $data['status']  ;
        return $this->account($type,$data['merchant_id'],$content);
    }

    /**
     * @name 退款申请通知(公众账号)
     * @param $data array   ['merchant_id'=>'商户id','no'=>'订单号','price' => '订单金额','buyer' => '买家','reason' => '退款原因']
     * @return  array
     */
    public function refundApplication($data){
        $type = 62 ;
        if(empty($data['merchant_id']) || empty($data['no']) || empty($data['price']) ||  empty($data['buyer']) || empty($data['reason'])){
            return ['errcode'=>1,'errmsg'=>'param null'];
        }
        $content = $this->tpldata[$type]['data'];
        $content['first'] = str_replace('xxx',$data['buyer'],$content['first']);
        $content['keyword1'] = $data['no'] ;
        $content['keyword2'] = $data['price'] ;
        $content['keyword3'] = $data['reason'] ;
        return $this->account($type,$data['merchant_id'],$content);
    }

    /**
     * @name 订单自提通知(公众账号)
     * @param $data array   ['merchant_id'=>'商户id','no'=>'订单号','price' => '订单金额','details'=>'订单详情','stores'=>'自提门店','address' => '门店地址']
     * @return  array
     */
    public function extractionSelf($data){
        $type = 63;
        if(empty($data['merchant_id']) || empty($data['no']) || empty($data['price']) || empty($data['details'])||  empty($data['stores']) || empty($data['address'])){
            return ['errcode'=>1,'errmsg'=>'param null'];
        }
        $content = $this->tpldata[$type]['data'];
        $content['keyword1'] = $data['price'] ;
        $content['keyword2'] = $data['details'] ;
        $content['keyword3'] = $data['stores'] ;
        $content['keyword4'] = $data['address'] ;
        $content['keyword5'] = $data['no'] ;
        return $this->account($type,$data['merchant_id'],$content);
    }

    /**
     * @name 预约成功通知(公众账号)
     * @param $data array   ['merchant_id'=>'商户id','name'=>'客户姓名','phone'=>'客户手机','time'=>'提交时间','details'=>'预定详情']
     * @return  array
     */
    public function subscribe($data){
        $type = 64;
        if(empty($data['merchant_id']) || empty($data['name']) || empty($data['phone']) || empty($data['time'])||  empty($data['details']) ){
            return ['errcode'=>1,'errmsg'=>'param null'];
        }
        $content = $this->tpldata[$type]['data'];
        $content['keyword1'] = $data['name'] ;
        $content['keyword2'] = $data['phone'] ;
        $content['keyword3'] = $data['time'] ;
        $content['keyword4'] = $data['details'] ;
        return $this->account($type,$data['merchant_id'],$content);
    }

    /**
     * @name 提现申请(公众账号)
     * @param $data array   ['merchant_id'=>'商户id', 'name'=>'昵称','time'=>'时间','price'=>'金额','type'=>'方式' ]
     * @return  array
     */
    public function withdrawals($data){
        $type = 65;
        if(empty($data['merchant_id']) || empty($data['name']) || empty($data['time']) || empty($data['price'])||  empty($data['type']) ){
            return ['errcode'=>1,'errmsg'=>'param null'];
        }
        $content = $this->tpldata[$type]['data'];
        $content['keyword1'] = $data['name'] ;
        $content['keyword2'] = $data['time'] ;
        $content['keyword3'] = $data['price'] ;
        $content['keyword4'] = $data['type'] ;
        return $this->account($type,$data['merchant_id'],$content);
    }

    /**
     * @name 申请加入(公众账号)
     * @param $data array   ['merchant_id'=>'商户id','name'=>'真实姓名','time'=>'申请时间' ]
     * @return  array
     */
    public function salesmanJoin($data){
        $type = 66;
        if(empty($data['merchant_id']) || empty($data['name'])  || empty($data['time'])  ){
            return ['errcode'=>1,'errmsg'=>'param null'];
        }
        $content = $this->tpldata[$type]['data'];
        $content['keyword1'] = $data['name'] ;
        $content['keyword2'] = $data['time'] ;
        return $this->account($type,$data['merchant_id'],$content);
    }

    /**
     * @name 虚拟商品订单支付通知(公众账号)
     * @param $data array   ['merchant_id'=>'商户id','no'=>'订单编号','name'=>'商品名称','count'=>'商品数量','price'=>'支付金额' ]
     * @return  array
     */
    public function virtualGood($data){
        $type = 67;
        if(empty($data['merchant_id']) || empty($data['no']) || empty($data['name'])  || empty($data['count']) || empty($data['price'])  ){
            return ['errcode'=>1,'errmsg'=>'param null'];
        }
        $content = $this->tpldata[$type]['data'];
        $content['keyword1'] = $data['no'] ;
        $content['keyword2'] = $data['name'] ;
        $content['keyword3'] = $data['count'] ;
        $content['keyword4'] = $data['price'] ;
        return $this->account($type,$data['merchant_id'],$content);
        return ;
    }

    public function account($type,$merchant_id,$content){
        $info = WeixinInfo::get_one('merchant_id',$merchant_id,2);
        if(empty($info['appid'])){
            return ['errcode'=>2,'errmsg'=>'appid null'];
        }
        $msginfo = WeixinMsgTemplate::get_one($info['merchant_id'],$info['appid'],$type);
        if(empty($msginfo['template_id'])){
            return ['errcode'=>2,'errmsg'=>'template_id null'];
        }
        $msgMerchant = WeixinMsgMerchant::list_data($info['merchant_id'],$info['appid']);
        $openlist = [];
        foreach ($msgMerchant as $k => $v) {
            $cacheValue = explode(',',$v['notice']);
            if(in_array($type,$cacheValue)){
                $openlist[$v['openid']] = $v['openid'];
            }
        }
        if(empty($openlist)){
            return ['errcode'=>2,'errmsg'=>'openid null'];
        }
        $WeixinService  = new WeixinService();
        $msgTpl = new OfficialMsgTemplate();
        $msgTpl -> setConfig($WeixinService->getAccessToken($info['appid']));

        $logIs = false;
        foreach ($openlist as $k=> $v) {
            $openlist[$k] = $msgTpl -> sendMsg($v,$msginfo['template_id'],$content);
            if(!$logIs){
                $logIs =  !isset($openlist[$k]['errcode'])  ||  $openlist[$k]['errcode'] != 0 ? true : false;
            }
        }
        if($logIs){
            $WeixinService->setLog('wexinmsg_pendingDelivery',$content,$openlist,$merchant_id,$info['appid']);
        }
        return ['errcode'=>0,'errmsg'=>'','result'=>$openlist];

    }

}