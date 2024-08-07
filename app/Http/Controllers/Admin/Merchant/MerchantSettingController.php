<?php

namespace App\Http\Controllers\Admin\Merchant;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Models\MerchantSetting;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;

class MerchantSettingController extends Controller
{
    protected $merchant_id;

    public function __construct()
    {
        if (app()->isLocal()) {
            $this->merchant_id = 2;
        } else {
            $this->merchant_id = Auth::user()->merchant_id;
        }
    }
    public function postSetting(Request $request)
    {
        $merchant_id = Auth::user()->merchant_id;
        $data = $request->all();
        $seting = MerchantSetting::get_data_by_id($merchant_id);


        if(isset($data['delivery_enabled']) && $data['delivery_enabled'] == 0){
            $seting_array = $seting->toArray(); 
            if(isset($seting_array['store_enabled']) && $seting_array['store_enabled'] == 0){
                return ['errcode' => "1002", 'errmsg' => "关闭失败：买家物流配送功能和上门自提功能不能全关闭"];exit;
            }
        }

        if($seting)
        {
            $result = $this->putSettings($data);
        }else{
            $result = $this->postSettings($data);
        }
        return $result ? Response::json(['errcode'=>0]) : Response::json(['errcode'=>1000001,'errmsg'=>'保存失败']);
    }

    public function getSetting($json=true)
    {
        $result = [];
        if(Auth::user()) {
            $merchant_id = Auth::user()->merchant_id;
            $result = MerchantSetting::get_data_by_id($merchant_id);
            //积分设置处理
            $credit_rank = array('open'=>0,'num'=>10,'spread_only'=>0);
            if(isset($result['credit_rank']) && $result['credit_rank']){
                $credit_rank = json_decode($result['credit_rank'],true);
                $credit_rank['open'] = isset($credit_rank['open']) ? $credit_rank['open'] : 0;
                $credit_rank['num'] = isset($credit_rank['num']) ? $credit_rank['num'] : 10;
                $credit_rank['spread_only'] = isset($credit_rank['spread_only']) ? $credit_rank['spread_only'] : 0;
            }
            $result['credit_rank'] = $credit_rank;
            if(isset($result['fields']) && $result['fields']){
                $result['fields'] = json_decode($result['fields'], true) ? json_decode($result['fields'], true) : [];
            }
            if(!isset($result['merchant_id'])){
                $result = [
                    'merchant_id'=>$merchant_id,
                    'store_enabled'=>0,
                    'store_member_info'=>0,
                    'delivery_enabled'=>1,
                    'label_enabled'=>0,
                    'credit_rank'=>$credit_rank,
                    'credit_rule'=>0,
                    'minutes'=>30,
                    'warning_stock'=>0,
                    'auto_finished_time'=>7,
                    'fields'=>isset($result['fields']) ? $result['fields'] : [],
                    'delivery_alias'=>'',
                    'selffetch_alias'=>'',
                    'is_comment_open'=>0,
                    'comment_type'=>0,
                    'created_time'=>'0000-00-00 00:00:00',
                    'updated_time'=>'0000-00-00 00:00:00'
                ];
            }
        }
        if($json){
            $result['errcode'] = 0;
            return $json ? Response::json([$result]) : Response::json(['errcode'=>100001]);
        }
        return $result;
    }

    /**
     *  配送/自提 名称自定义(手机端下单时)
     */
    function getDeliveryOrSelffetchAliasSetting(){
        $merchant_id = Auth::user()->merchant_id;
        $deliveryOrSelffetchAliasSetting = [];
        $deliveryOrSelffetchAliasSetting['errcode'] = 100001;

        $res = MerchantSetting::select("delivery_alias","selffetch_alias")->where("merchant_id",$merchant_id)->first();
        if($res && $res=$res->toArray()){
            $deliveryOrSelffetchAliasSetting['delivery_alias'] =  $res['delivery_alias'] ? :"快递配送";
            $deliveryOrSelffetchAliasSetting['selffetch_alias'] =  $res['selffetch_alias'] ? :"上门自提";
            $deliveryOrSelffetchAliasSetting['errcode'] = 0;
        }
        return Response::json($deliveryOrSelffetchAliasSetting);
    }

    /**
     * 配送/自提 名称自定义设置
     */
    function setDeliveryOrSelffetchAliasSetting(Request $request){
        $params = $request->all();
        if(!isset($params['delivery_alias']) || empty($params['delivery_alias']) || !isset($params['selffetch_alias']) || empty($params['selffetch_alias'])){
            return Response::json(array('errcode' => 100001, 'errmsg' => '缺少必要参数'));
        }
        //长度限制
        if(mb_strlen($params['delivery_alias']) >4 || mb_strlen($params['selffetch_alias']) >4){
            return Response::json(array('errcode' => 100002, 'errmsg' => '长度不能超过4个中文字符'));
        }
        $merchant_id = Auth::user()->merchant_id;
        MerchantSetting::where("merchant_id",$merchant_id)->update(['delivery_alias' => $params['delivery_alias'], 'selffetch_alias' => $params['selffetch_alias']]);

        return Response::json(array('errcode' => 0));
    }

    //新增设置
    public function postSettings($data)
    {
        $merchant_id = Auth::user()->merchant_id;
        $settingData = [
            'merchant_id' => $merchant_id,
            'store_enabled' => isset($data['store_enabled']) ? $data['store_enabled'] : 0,
            'credit_rule' => isset($data['credit_rule']) ? $data['credit_rule'] : 0,
            'warning_stock' => isset($data['warning_stock']) ? $data['warning_stock'] : 10,
            'credit_rank' => (isset($data['credit_rank']) && $data['credit_rank']) ? json_encode($data['credit_rank']) : '',
            'fields'    =>  (isset($data['fields']) && $data['fields']) ? json_encode($data['fields']) : '',
            'store_enabled' => isset($data['store_enabled']) ? $data['store_enabled'] : 0,
            'delivery_enabled' => isset($data['delivery_enabled']) ? $data['delivery_enabled'] : 0,
            'label_enabled' => isset($data['label_enabled']) ? $data['label_enabled'] : 0
        ];

        $settingInfo = MerchantSetting::get_data_by_id($merchant_id);
        if(!$settingInfo){
            return MerchantSetting::insert_data($settingData);
        }

        //更新商户设置
        //unset($settingData['merchant_id']);
        //return MerchantSetting::where(['merchant_id',$merchant_id])->update($settingData);
    }

    //修改设置
    private function putSettings($data)
    {
        $merchant_id = Auth::user()->merchant_id;
		$setarr = [];
        //是否启用上门自提
        if(isset($data['store_enabled'])){
            $setarr['store_enabled'] = $data['store_enabled'];
        }
        //上门自提是否需要完善信息
        if(isset($data['store_member_info'])){
            $setarr['store_enabled'] = $data['store_member_info'];
        }
        //买家物流配送功能
        if(isset($data['delivery_enabled'])){
            $setarr['delivery_enabled'] = $data['delivery_enabled'];
        }
        //是否开启服务标签
        if(isset($data['label_enabled'])){
            $setarr['label_enabled'] = $data['label_enabled'];
        }
        //预警库存
        if(isset($data['warning_stock'])){
            $setarr['warning_stock'] = $data['warning_stock'];
        }
        //消费积分抵扣规则
        if(isset($data['credit_rule'])){
            $setarr['credit_rule'] = $data['credit_rule'];
        }
        //发件人姓名
        if(isset($data['addresser_name'])){
            $setarr['addresser_name'] = $data['addresser_name'];
        }
        if(isset($data['addresser_address'])){
            $setarr['addresser_address'] = $data['addresser_address'];
        }
        if(isset($data['addresser_tel'])){
            $setarr['addresser_tel'] = $data['addresser_tel'];
        }
        if(isset($data['addresser_company'])){
            $setarr['addresser_company'] = $data['addresser_company'];
        }
        //技师别名
        if(isset($data['staff_alias'])){
            $setarr['staff_alias'] = $data['staff_alias'];
        }

        //积分榜设置
        if(isset($data['credit_rank']) && $data['credit_rank']){
            if(is_array($data['credit_rank'])){
                $setarr['credit_rank'] = json_encode($data['credit_rank']);
            }
        }
        //订单留言自定义选填字段，name字段名称，type字段类型，required=1是必填 0:是非必填
        if (isset($data['fields'])) {
            if(is_array($data['fields'])){
                $setarr['fields'] = json_encode($data['fields']);
            }
        }

        $result = MerchantSetting::update_data($merchant_id,$setarr);
        return $result;
    }

    //訂單自動過期時間設置 && 自動確認收貨時間設置
    public function setExpirationTime(Request $request) {
        $merchant_id = Auth::user()->merchant_id;
        $params = $request->all();
        $minutes = isset($params['time']) && $params['time'] ? intval($params['time']) : '';    // 訂單自動過期時間
        $days = isset($params['day']) && $params['day'] ? intval($params['day']) : '';          // 自動確認收貨時間
        if(!$minutes || $minutes == 0 || $minutes < 20) {
            return Response::json(['errcode' => 100001, 'errmsg' => '请设置有效的订单自动过期时间']);
        }
        if($days < 7 || $days > 30) {
            return Response::json(['errcode' => 100002, 'errmsg' => '请设置有效范围内的自动确认收货时间']);
        }
        if (!preg_match("/^[0-9]*$/", $minutes) || !preg_match("/^[0-9]*$/", $days)) {
            return Response::json(['errcode' => 100003, 'errmsg' => '请设置有效的过期时间']);
        }
        if($minutes >= 10080) {   // 2016年6月2日 小宋提:优化去掉过期时间上限设置 [狂奔的螞蟻]
            return Response::json(['errcode' => 100004, 'errmsg' => '过期时间不能超过7天，请重新设置']);
        }
        $settingInfo = MerchantSetting::where('merchant_id', $merchant_id)->first();
        if(!$settingInfo) {
            $data = array(
                'merchant_id' => $merchant_id,
                'minutes' => $minutes,
                'auto_finished_time' => $days
            );
            MerchantSetting::create($data);
            $original = '';
            $sort = 1;
        }else{
            MerchantSetting::where('merchant_id', $merchant_id)->update(array('minutes' => $minutes, 'auto_finished_time' => $days));
            $original = json_encode($settingInfo);
            $sort = 2;
        }

        //记录日志
        return Response::json(['errcode' => 0, 'errmsg' => '操作成功']);
    }

    //查询知识付费设置
    public function getKnowlageSetting(){
        $data = MerchantSetting::get_data_by_id($this->merchant_id);
        if(!$data){
            //没有则给默认值
            $new_data = [
                'merchant_id'=>$this->merchant_id,
                'knowledge_record'=>1, //默认展示
            ];
        }else{
            $new_data = [
                'merchant_id'=>$this->merchant_id,
                'knowledge_record'=>$data->knowledge_record, //默认展示
            ];
        }
        return ['errcode' => 0, 'errmsg' => '查询成功', 'data' => $new_data];
    } 

    //修改知识付费设置
    public function putKnowlageSetting(Request $request)
    {
        $knowledge_record = $request->input('knowledge_record',-1);
        //参数验证
        if( !in_array($knowledge_record,[1,0]) ){ //0不显示 1显示
            return ['errcode' => 99001, 'errmsg' => '参数非法', 'data' => []];
        }
        $is_have = MerchantSetting::update_data($this->merchant_id,['knowledge_record' => $knowledge_record]);
        if($is_have){
            return ['errcode' => 0, 'errmsg' => '修改成功', 'data' => []];
        }else{
            return ['errcode' => 400300, 'errmsg' => '修改失败', 'data' => []];
        }
        
    }


}
