<?php

/**
 * 门店控制器
 * @author wangshen@dodoca.com
 * @cdate 2017-10-24
 * 
 */
namespace App\Http\Controllers\Weapp\Store;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Facades\Member;

use App\Models\Store;
use App\Models\StoreSetting;
use App\Models\Region;
use App\Models\Member as MemberModel;
use App\Models\MerchantSetting;
use App\Models\MemberCard;
use App\Models\OrderInfo;
use App\Models\Coupon;
use App\Models\CouponCode;
use App\Models\NewUserGift;

use App\Utils\SendMessage;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;


use App\Services\CreditService;
use App\Services\BuyService;
use App\Services\DesignService;
use Carbon\Carbon;

class StoreController extends Controller {

    
    public function __construct(CreditService $creditService,BuyService $buyService, DesignService $designService){
        
        $this->member_id = Member::id();//会员id
        $this->merchant_id = Member::merchant_id();//商户id
        $this->weapp_id = Member::weapp_id();//小程序id
    
        //积分服务类
        $this->creditService = $creditService;
		
		//支付服务类
		$this->buyService = $buyService;
        $this->designService = $designService;
    }
        
    
    
    /**
     * 获取3KM内是否有门店
     * @author wangshen@dodoca.com
     * @cdate 2017-10-24
     *
     */
    public function verifyStore(Request $request){
        
        //参数
        $params = $request->all();
        
        $merchant_id = $this->merchant_id;//商户id
        
        $member_id = $this->member_id;//会员id
        
        if(!$merchant_id){
            return ['errcode' => 99001,'errmsg' => '商户ID不存在'];
        }
        
        //小程序id
        $weapp_id = $this->weapp_id;
        
//         if(!$weapp_id){
//             return ['errcode' => 99001,'errmsg' => '小程序ID不存在'];
//         }
        
        
        //经度
        $longitude = isset($params['longitude']) ? $this->repalce_str($params['longitude']) : '';
        //纬度
        $latitude = isset($params['latitude']) ? $this->repalce_str($params['latitude']) : '';
        
        //未获取到用户地理位置
        if(empty($longitude) || empty($latitude)){
            return ['errcode' => 160001,'errmsg' => '未获取到用户地理位置'];
        }
        
        
        //获取3KM内距离最近门店
        $store_list = Store::select('id','lng','lat')
                            ->where('merchant_id', $merchant_id)
                            ->where('enabled', 1)//开店状态
                            ->where('is_delete', 1)//未删除
                            ->whereRaw("(ACOS(SIN(($latitude*3.1415)/180) * SIN((lat*3.1415)/180) + COS(($latitude*3.1415)/180) * COS((lat*3.1415)/180) * COS(($longitude*3.1415)/180 - (lng*3.1415)/180))*6371)<=3")
                            ->get()->toArray();
        
        if(count($store_list) > 0){
            
            //门店按距离由近到远排序
            $store_list = $this->distance_priority($longitude, $latitude, $store_list);
            
            //最近的门店
            $store_info = $store_list[0];
            
            //最近的门店的门店id
            $store_id = $store_info['id'];
            
            //获取门店详情方法
            $store_detail = $this->store_detail($merchant_id, $member_id, $store_id, $longitude, $latitude, $weapp_id);
            
            if($store_detail['errcode'] == 0){
                return ['errcode' => 0,'errmsg' => '3KM内有门店','data' => $store_detail['data']];
            }else{
                return ['errcode' => $store_detail['errcode'],'errmsg' => $store_detail['errmsg']];
            }
            
        }else{
            
            return ['errcode' => 0,'errmsg' => '3KM内无门店','data' => ''];
        }
        
        
    }
    
    
    /**
     * 门店列表
     * @author wangshen@dodoca.com
	 * @cdate 2017-10-24
     * 
     */
    public function getStoreList(Request $request){
        
        //参数
        $params = $request->all();
        
        $merchant_id = $this->merchant_id;//商户id
        
        if(!$merchant_id){
            return ['errcode' => 99001,'errmsg' => '商户ID不存在'];
        }
        
        
        //分页参数
        $page = isset($params['page']) ? (int)$params['page'] : 1;//页码
        $pagesize = isset($params['pagesize']) ? (int)$params['pagesize'] : 10;//每页条数
        
        //偏移量/每页数量
        $offset = ($page - 1) * $pagesize;
        $limit = $pagesize;
        
        
        //经度
        $longitude = isset($params['longitude']) ? $this->repalce_str($params['longitude']) : '';
        //纬度
        $latitude = isset($params['latitude']) ? $this->repalce_str($params['latitude']) : '';
        
        
        
        
        //条件
        $wheres = [];
        $wheres[] = ['column' => 'merchant_id','operator' => '=','value' => $merchant_id];
        $wheres[] = ['column' => 'enabled','operator' => '=','value' => 1];//开店状态
        $wheres[] = ['column' => 'is_delete','operator' => '=','value' => 1];//未删除
        
        //查询字段
        $fields = 'id,name,province,city,district,address,lng,lat,img';
        
        //数量
        //$_count = Store::get_data_count($wheres);
        
        //列表数据
        $store_list = Store::get_data_list_all($wheres,$fields,'CONVERT(name USING GBK)','ASC');
        
        if($store_list){
            
            //如果获取了用户的位置，门店按距离由近到远排序
            if(!empty($longitude) && !empty($latitude)){
                $store_list = $this->distance_priority($longitude, $latitude, $store_list);
            }
            
            foreach($store_list as $key => $val){
                
                //距离单位显示规则，如果在1000米以内的，显示m为单位；如果超出1000米的，显示km为单位。
                if(!empty($longitude) && !empty($latitude)){
                    if($val['distance_num'] < 1){
                        $store_list[$key]['distance_num'] = ($val['distance_num'] * 1000) . 'm';
                    }else{
                        $store_list[$key]['distance_num'] = $val['distance_num'] . 'km';
                    }
                }else{
                    $store_list[$key]['distance_num'] = '';
                }
        
                //省市区转换
                $store_list[$key]['province_name'] = Region::get_title_by_id_cache($val['province']);
                $store_list[$key]['city_name'] = Region::get_title_by_id_cache($val['city']);
                $store_list[$key]['district_name'] = Region::get_title_by_id_cache($val['district']);
                
                //门店图片
                if($val['img']){
                    $store_list[$key]['store_img'] = json_decode($val['img'], true);
                    $store_list[$key]['store_img'] = $store_list[$key]['store_img'][0];//取第一张图片
                }else{
                    $store_list[$key]['store_img'] = '';
                }
                
                
                //去除不必要字段
                unset($store_list[$key]['province']);
                unset($store_list[$key]['city']);
                unset($store_list[$key]['district']);
                unset($store_list[$key]['lng']);
                unset($store_list[$key]['lat']);
                unset($store_list[$key]['img']);
        
            }
        }
        
        
        //数量
        $_count = count($store_list);
        
        //截取分页数据
        $store_list = array_slice($store_list, $offset, $limit);
        
        
        $data['_count'] = $_count;
        $data['data'] = $store_list;
        
        
        return ['errcode' => 0,'errmsg' => '获取列表成功','data' => $data];
    }
    
    
    
    /**
     * 门店详情
     * @author wangshen@dodoca.com
     * @cdate 2017-10-24
     *
     * @param int $store_id 门店id
     */
    public function getStoreDetail($store_id,Request $request){
        
        //参数
        $params = $request->all();
        
        
        $store_id = isset($store_id) ? (int)$store_id : 0;//门店id
        
        
        $merchant_id = $this->merchant_id;//商户id
        
        $member_id = $this->member_id;//会员id
        
        if(!$merchant_id){
            return ['errcode' => 99001,'errmsg' => '商户ID不存在'];
        }
        
//         if(!$member_id){
//             return ['errcode' => 99001,'errmsg' => '会员ID不存在'];
//         }

        
        //小程序id
        $weapp_id = $this->weapp_id;
        
//         if(!$weapp_id){
//             return ['errcode' => 99001,'errmsg' => '小程序ID不存在'];
//         }
        
        
        //经度
        $longitude = isset($params['longitude']) ? $this->repalce_str($params['longitude']) : '';
        //纬度
        $latitude = isset($params['latitude']) ? $this->repalce_str($params['latitude']) : '';
        
        
        
        //获取门店详情方法
        $store_detail = $this->store_detail($merchant_id, $member_id, $store_id, $longitude, $latitude, $weapp_id);
        
        if($store_detail['errcode'] == 0){
            return ['errcode' => 0,'errmsg' => '获取门店信息成功','data' => $store_detail['data']];
        }else{
            return ['errcode' => $store_detail['errcode'],'errmsg' => $store_detail['errmsg']];
        }
        
    }
    
    
    
    /**
     * 获取门店详情方法
     * @author wangshen@dodoca.com
     * @cdate 2017-10-31
     *
     * @param int $merchant_id 商户id
     * @param int $member_id 会员id
     * @param int $store_id 门店id
     * @param string $longitude 经度
     * @param string $latitude 纬度
     * @param int $weapp_id 小程序id
     */
    public function store_detail($merchant_id,$member_id,$store_id,$longitude,$latitude,$weapp_id){
        
        //门店信息
        $store_info = Store::get_data_by_id($store_id, $merchant_id);
        
        if(!$store_info){
            return ['errcode' => 160003,'errmsg' => '门店信息不存在'];
        }
        
        if($store_info['is_delete'] == -1){
            return ['errcode' => 160004,'errmsg' => '该门店已删除'];
        }
        
        if($store_info['enabled'] == 0){
            return ['errcode' => 160005,'errmsg' => '该门店是关店状态'];
        }
        
        
        //门店图片
        $img = json_decode($store_info['img'], true);
        
        
        //如果获取了用户位置，显示与门店距离
        if(!empty($longitude) && !empty($latitude)){
        
            $distance = $this->getDistance($store_info['lng'], $store_info['lat'], $longitude, $latitude);
            $distanceKm = floor($distance) / 1000;
        
        
            if($distanceKm < 1){
                $distance_num = ($distanceKm * 1000) . 'm';
            }else{
                $distance_num = $distanceKm . 'km';
            }
        }else{
            $distance_num = '';
        }
        
        
        //特色服务
        $special_wifi = 0;
        $special_car = 0;
        $special_smoke = 0;
        $special_eat = 0;
        $special_room = 0;
        
        if($store_info['special_service']){
            $special_service_arr = explode(',',$store_info['special_service']);
        
            $special_wifi  = in_array('1',$special_service_arr) ? 1 : 0;
            $special_car   = in_array('2',$special_service_arr) ? 1 : 0;
            $special_smoke = in_array('3',$special_service_arr) ? 1 : 0;
            $special_eat   = in_array('4',$special_service_arr) ? 1 : 0;
            $special_room  = in_array('5',$special_service_arr) ? 1 : 0;
        
        }
        
        //营业时间
        $monday = 0;
        $tuesday = 0;
        $wednesday = 0;
        $thursday = 0;
        $friday = 0;
        $saturday = 0;
        $sunday = 0;
        
        if($store_info['office_at']){
            $office_at_arr = explode(',',$store_info['office_at']);
        
            $monday    = in_array('1',$office_at_arr) ? 1 : 0;
            $tuesday   = in_array('2',$office_at_arr) ? 1 : 0;
            $wednesday = in_array('3',$office_at_arr) ? 1 : 0;
            $thursday  = in_array('4',$office_at_arr) ? 1 : 0;
            $friday    = in_array('5',$office_at_arr) ? 1 : 0;
            $saturday  = in_array('6',$office_at_arr) ? 1 : 0;
            $sunday    = in_array('7',$office_at_arr) ? 1 : 0;
        
        }
        
        //省市区转换
        $province_name = Region::get_title_by_id_cache($store_info['province']);
        $city_name = Region::get_title_by_id_cache($store_info['city']);
        $district_name = Region::get_title_by_id_cache($store_info['district']);
        
        
        if($member_id){
            //是否显示领取会员卡入口（默认会员且未绑定手机才显示）
            $if_get_card = 0;
        
            $member_info = MemberModel::get_data_by_id($member_id, $merchant_id);
        
            if($member_info){
                if($member_info['member_card_id'] == 0 && $member_info['mobile'] == ''){
                    $if_get_card = 1;
                }
            }
        }else{
            $if_get_card = 1;//没有传token，显示领取会员卡入口
        }
        
        //小程序的门店设置
        $coupon_onoff = 0 ; //优惠券设置默认未开启
        $salepay_onoff = 0 ;//优惠买单设置默认未开启
        $store_design = StoreSetting::get_data_by_id($merchant_id,$weapp_id);
        if($store_design){
            $store_design = $store_design->toArray();
            $coupon_onoff = $store_design['coupon_onoff']; //读取优惠券设置
            $salepay_onoff = $store_design['salepay_onoff']; //读取优惠买单设置
        }

        //新用户有礼
        $is_gift = NewUserGift::where('merchant_id', $merchant_id)
            ->where('begin_time', '<=', Carbon::now())
            ->where('end_time', '>=', Carbon::now())
            ->where('status', '<>', 2)
            ->where('is_delete', 1)
            ->value('id');
        $is_gift = $is_gift ? 1: 0;
        //接口返回数据
        $data = [
        
            'store_id' => $store_info['id'],//门店id
            'info' => $store_info['info'],//门店详情
            'img' => $img,//门店图片，一维数组形式
            'name' => $store_info['name'],//门店名称
            'distance_num' => $distance_num,//距离，为空则没有获取到用户位置
        
            //特色服务
            'special_wifi' => $special_wifi,//特色服务是否有WIFI，0否、1是
            'special_car' => $special_car,//特色服务是否有停车位，0否、1是
            'special_smoke' => $special_smoke,//特色服务是否有无烟区，0否、1是
            'special_eat' => $special_eat,//特色服务是否有茶水小食，0否、1是
            'special_room' => $special_room,//特色服务是否有包厢，0否、1是
        
            //营业时间
            'monday' => $monday,//营业时间是否有周一，0否、1是
            'tuesday' => $tuesday,//营业时间是否有周二，0否、1是
            'wednesday' => $wednesday,//营业时间是否有周三，0否、1是
            'thursday' => $thursday,//营业时间是否有周四，0否、1是
            'friday' => $friday,//营业时间是否有周五，0否、1是
            'saturday' => $saturday,//营业时间是否有周六，0否、1是
            'sunday' => $sunday,//营业时间是否有周日，0否、1是
            'open_time' => $store_info['open_time'],//营业开始时间，例：8:00
            'close_time' => $store_info['close_time'],//营业结束时间，例：23:00
        
            'mobile' => $store_info['mobile'],//门店电话
            'province_name' => $province_name,//省名称
            'city_name' => $city_name,//市名称
            'district_name' => $district_name,//区名称
            'address' => $store_info['address'],//详细地址
            'longitude' => $store_info['lng'],//经度
            'latitude' => $store_info['lat'],//纬度
        
            'if_get_card' => $if_get_card,//是否显示领取会员卡，0不显示、1显示
            'coupon_onoff' => $coupon_onoff,//是否显示优惠券，0不显示、1显示
            'salepay_onoff' => $salepay_onoff,//是否显示优惠买单，0不显示、1显示
            'is_gift' => $is_gift//新用户有礼开关 0 关闭 1 开启
        
        ];
        
        
        
        return ['errcode' => 0,'errmsg' => '获取门店信息成功','data' => $data];
    }
    
    
    
    
    
    //门店按距离由近到远排序
    public function distance_priority($longitude,$latitude,$data){
        
        //计算距离
        if($longitude && $latitude){
            foreach($data as &$v){
                $distance = $this->getDistance($v['lng'], $v['lat'], $longitude, $latitude);
                $distanceKm = floor($distance) / 1000;
                $v['distance_num']= $distanceKm;
            }
            $distance_num = array();
            foreach ($data as $value) {
                $distance_num[] = $value['distance_num'];
            }
            array_multisort($distance_num, SORT_ASC, $data);
            
            
            return $data;
        }
        
    }
    
    /**
     * 根据座标计算地理位置
     * 单位：米
     *
     * @return void
     * @author
     **/
    public function getDistance($lng1, $lat1, $lng2, $lat2) {
        $radLat1 = deg2rad($lat1); //deg2rad()函数将角度转换为弧度
        $radLat2 = deg2rad($lat2);
        $radLng1 = deg2rad($lng1);
        $radLng2 = deg2rad($lng2);
        $a = $radLat1 - $radLat2;
        $b = $radLng1 - $radLng2;
        $s = 2 * asin(sqrt(pow(sin($a / 2), 2) + cos($radLat1) * cos($radLat2) * pow(sin($b / 2), 2))) * 6371.137 * 1000;
        return $s;
    }
    
    
    
    /**
     * 获取用户是否领取过会员卡
     * @author wangshen@dodoca.com
     * @cdate 2017-10-30
     */
    public function getMemberCard(Request $request){
        
        $merchant_id = $this->merchant_id;//商户id
        
        $member_id = $this->member_id;//会员id
        
        if(!$merchant_id){
            return ['errcode' => 99001,'errmsg' => '商户ID不存在'];
        }
        
        if(!$member_id){
            return ['errcode' => 99001,'errmsg' => '会员ID不存在'];
        }
        
        
        //是否领取过会员卡（默认会员且未绑定手机才算领取过）
        $get_member_card = 0;
        
        $member_info = MemberModel::get_data_by_id($member_id, $merchant_id);
        
        if($member_info){
            if($member_info['member_card_id'] != 0 || $member_info['mobile'] != ''){
                $get_member_card = 1;
            }
        }
        
        
        //接口返回数据
        $data = [
            'get_member_card' => $get_member_card//是否领取过会员卡，0未领取过、1领取过
        ];
        
        
        
        return ['errcode' => 0,'errmsg' => '获取信息成功','data' => $data];
        
    }
    
    
    
    
    /**
     * 发送验证码
     * @author wangshen@dodoca.com
     * @cdate 2017-10-25
     *
     * @param string $mobile 手机号
     * @param string $captcha 图片验证码
     */
    public function sendSmsMessage(Request $request){
        
        //参数
        $params = $request->all();
        
        $mobile = isset($params['mobile']) ? $this->repalce_str($params['mobile']) : '';//手机号
        
        $merchant_id = $this->merchant_id;//商户id
        
        $member_id = $this->member_id;//会员id
        
        //图片验证码判断
        $captcha = isset($params['captcha']) ? $this->repalce_str($params['captcha']) : '';
        
        if(!$captcha){
            return ['errcode' => 160018, 'errmsg' => '请输入图片验证码'];
        }
        
        $captcha_key = CacheKey::get_member_captcha_key($merchant_id, $member_id);
        $captcha_code = Cache::get($captcha_key);
        
        if($captcha_code != strtolower($captcha)){
            return ['errcode' => 160017, 'errmsg' => '图片验证码不正确'];
        }
        
        
        if(!$merchant_id){
            return ['errcode' => 99001,'errmsg' => '商户ID不存在'];
        }
        
        if(!$member_id){
            return ['errcode' => 99001,'errmsg' => '会员ID不存在'];
        }
        
        if(!$mobile){
            return ['errcode' => 160006,'errmsg' => '手机号参数缺失'];
        }
        
        if(!is_numeric($mobile) || strlen($mobile) != 11){
            return ['errcode' => 160007,'errmsg' => '手机号格式不正确'];
        }
        
        
        //增加60秒内不能重复发送限制
        $sixty_key = CacheKey::get_member_sms_sixty_by_mobile_key($mobile, $merchant_id);
        $sixty_data = Cache::get($sixty_key);
        
        if($sixty_data){
            return ['errcode' => 160008,'errmsg' => '60秒内不能重复发送'];
        }
        
        
        
        //发送验证码
        $sms_str = $this->_createSmsStr();
        $sms_content = '您的验证码是：'.$sms_str;
        
        $result = SendMessage::send_sms($mobile,$sms_content,2);
        
        if($result){
            
            //短信验证码存入缓存，有效期5分钟
            $key = CacheKey::get_member_sms_by_mobile_key($mobile, $merchant_id);
            Cache::put($key, $sms_str, 5);   //有效期5分钟
            
            //增加60秒内不能重复发送限制
            Cache::put($sixty_key, $mobile, 1);
            
            
            return ['errcode' => 0,'errmsg' => '发送成功'];
        }else{
            return ['errcode' => 160009,'errmsg' => '发送失败'];
        }
        
        
    }
    
    private function _createSmsStr()
    {
        /* 选择一个随机的方案 */
        mt_srand((double) microtime() * 1000000);
        $str = str_pad(mt_rand(1, 99999), 6, '0', STR_PAD_LEFT);
        return $str;
    }
    
    
    
    /**
     * 立即开卡
     * @author wangshen@dodoca.com
     * @cdate 2017-10-25
     *
     * @param string $mobile 手机号
     * @param string $sms_str 短信验证码
     */
    public function openCard(Request $request){
        
        //参数
        $params = $request->all();
        
        $mobile = isset($params['mobile']) ? $this->repalce_str($params['mobile']) : '';//手机号
        $sms_str = isset($params['sms_str']) ? $this->repalce_str($params['sms_str']) : '';//短信验证码
        
        $merchant_id = $this->merchant_id;//商户id
        
        $member_id = $this->member_id;//会员id
        
        
        if(!$merchant_id){
            return ['errcode' => 99001,'errmsg' => '商户ID不存在'];
        }
        
        if(!$member_id){
            return ['errcode' => 99001,'errmsg' => '会员ID不存在'];
        }
        
        if(!$mobile){
            return ['errcode' => 160006,'errmsg' => '手机号参数缺失'];
        }
        
        if(!is_numeric($mobile) || strlen($mobile) != 11){
            return ['errcode' => 160007,'errmsg' => '手机号格式不正确'];
        }
        
        if(!$sms_str){
            return ['errcode' => 160010,'errmsg' => '短信验证码参数缺失'];
        }
        
        //判断短信验证码
        $key = CacheKey::get_member_sms_by_mobile_key($mobile, $merchant_id);
        $sms_data = Cache::get($key);
        
        if(!$sms_data){
            return ['errcode' => 160011,'errmsg' => '验证码失效，请重新获取'];
        }
        
        if($sms_data != $sms_str){
            return ['errcode' => 160012,'errmsg' => '验证码错误'];
        }
        
        //判断手机号是否已经是这个商家的会员
        $wheres = [];
        $wheres[] = ['column' => 'merchant_id','operator' => '=','value' => $merchant_id];
        $wheres[] = ['column' => 'mobile','operator' => '=','value' => $mobile];
        
        //数量
        $_count = MemberModel::get_data_count($wheres);
        
        if($_count > 0){
            return ['errcode' => 160013,'errmsg' => '该手机号已是会员'];
        }
        
        
        //查询会员信息
        $member_info = MemberModel::get_data_by_id($member_id, $merchant_id);
        
        
        if($member_info['is_verify_mobile'] == 1){//已验证过手机
            $result = MemberModel::update_data($member_id,$merchant_id,['mobile'=>$mobile]);
        }else{
            $result = MemberModel::update_data($member_id,$merchant_id,['mobile'=>$mobile,'is_verify_mobile'=>1]);
            //绑定手机送积分
            $this->creditService->giveCredit($merchant_id, $member_id, 1);
        }
        
        
        if($result){
            
            //清除短信验证码缓存
            Cache::forget($key);
            
            return ['errcode' => 0,'errmsg' => '恭喜您，开卡成功'];
        }else{
            return ['errcode' => 160014,'errmsg' => '开卡失败'];
        }
        
        
        
        
        
        
    }
    
    
    
    /**
     * 自提门店列表
     * @author wangshen@dodoca.com
     * @cdate 2017-10-26
     *
     */
    public function getPickStoreList(Request $request){
    
        //参数
        $params = $request->all();
    
        $merchant_id = $this->merchant_id;//商户id
    
        if(!$merchant_id){
            return ['errcode' => 99001,'errmsg' => '商户ID不存在'];
        }
		
    	$store_type = isset($params['store_type']) ? (int)$params['store_type'] : 1;	//1:自提门店，2：同城配送门店
    
        //分页参数
        $page = isset($params['page']) ? (int)$params['page'] : 1;//页码
        $pagesize = isset($params['pagesize']) ? (int)$params['pagesize'] : 10;//每页条数
    
        //偏移量/每页数量
        $offset = ($page - 1) * $pagesize;
        $limit = $pagesize;
        
        //经度
        $longitude = isset($params['longitude']) ? $this->repalce_str($params['longitude']) : '';
        //纬度
        $latitude = isset($params['latitude']) ? $this->repalce_str($params['latitude']) : '';
    
        
        //条件
        $wheres = [];
        $wheres[] = ['column' => 'merchant_id','operator' => '=','value' => $merchant_id];
        $wheres[] = ['column' => 'enabled','operator' => '=','value' => 1];//开店状态
		if($store_type==1) {	//自提门店
        	$wheres[] = ['column' => 'extract_enabled','operator' => '=','value' => 1];//门店自提点状态为开启
		} else if($store_type==2) {	//同城配送门店
			$wheres[] = ['column' => 'city_enabled','operator' => '=','value' => 1];
		}
        $wheres[] = ['column' => 'is_delete','operator' => '=','value' => 1];//未删除
    
        //查询字段
        $fields = 'id,name,province,city,district,address,lng,lat,mobile';
    
        //数量
        //$_count = Store::get_data_count($wheres);
    
        //列表数据
        $store_list = Store::get_data_list_all($wheres,$fields,'CONVERT(name USING GBK)','ASC');
    	
		$merchant_setting_info = MerchantSetting::get_data_by_id($merchant_id);
		
        if($store_list){
            
            //如果获取了用户的位置，门店按距离由近到远排序
            if(!empty($longitude) && !empty($latitude)){
                $store_list = $this->distance_priority($longitude, $latitude, $store_list);
            }
            
            foreach($store_list as $key => $val){
                
                //距离单位显示规则，如果在1000米以内的，显示m为单位；如果超出1000米的，显示km为单位。
                if(!empty($longitude) && !empty($latitude)){
                    if($store_type==2) {	//同城配送（有效距离）
						$store_list[$key]['is_valid'] = ($merchant_setting_info['city_distance']==0 || $merchant_setting_info['city_distance']>=$val['distance_num']) ? 1 : 0;
					}
					if($val['distance_num'] < 1){
                        $store_list[$key]['distance_num'] = ($val['distance_num'] * 1000) . 'm';
                    }else{
                        $store_list[$key]['distance_num'] = $val['distance_num'] . 'km';
                    }
                }else{
                    $store_list[$key]['distance_num'] = '';
                }
    
                //省市区转换
                $store_list[$key]['province_name'] = Region::get_title_by_id_cache($val['province']);
                $store_list[$key]['city_name'] = Region::get_title_by_id_cache($val['city']);
                $store_list[$key]['district_name'] = Region::get_title_by_id_cache($val['district']);
    
    
                //去除不必要字段
                unset($store_list[$key]['province']);
                unset($store_list[$key]['city']);
                unset($store_list[$key]['district']);
    
            }
        }
    
        
        //数量
        $_count = count($store_list);
        
        //截取分页数据
        $store_list = array_slice($store_list, $offset, $limit);
        
    
        $data['_count'] = $_count;
        $data['data'] = $store_list;
    
    
        return ['errcode' => 0,'errmsg' => '获取列表成功','data' => $data];
    }
    
    

    
    
    
    
    /**
     * 优惠买单信息
     * @author wangshen@dodoca.com
     * @cdate 2017-10-26
     */
    public function getSalepayInfo(Request $request){
        
        //参数
        $params = $request->all();
         
        $merchant_id = $this->merchant_id;//商户id
         
        $member_id = $this->member_id;//会员id
         
        if(!$merchant_id){
            return ['errcode' => 99001,'errmsg' => '商户ID不存在'];
        }
         
//         if(!$member_id){
//             return ['errcode' => 99001,'errmsg' => '会员ID不存在'];
//         }
         
         
        $store_id = isset($params['store_id']) ? (int)$params['store_id'] : 0;//门店id
         
         
        //门店信息
        $store_info = Store::get_data_by_id($store_id, $merchant_id);
         
//         if(!$store_info){
//             return ['errcode' => 160003,'errmsg' => '门店信息不存在'];
//         }

        $store_info_name = '';
         
        if($store_info){
            if($store_info['is_delete'] == -1){
                return ['errcode' => 160004,'errmsg' => '该门店已删除'];
            }
             
            if($store_info['enabled'] == 0){
                return ['errcode' => 160005,'errmsg' => '该门店是关店状态'];
            }
            
            $store_info_name = $store_info['name'];
        }
         
         
        //优惠方式设置信息
        $merchant_setting_info = MerchantSetting::get_data_by_id($merchant_id);
         
         
        
        if($member_id){
            //会员折扣信息
            if($merchant_setting_info['salepay_member'] == 0){
                $member_discounts = '';//优惠买单不能使用会员折扣，会员折扣信息为空
            }else{
                
                //查询会员信息
                $member_info = MemberModel::get_data_by_id($member_id, $merchant_id);
                
                if($member_info['member_card_id'] == 0){
                    $member_discounts = '';//默认会员无会员折扣信息
                }else{
                    
                    //查询会员卡信息
                    $member_card_info = MemberCard::get_data_by_id($member_info['member_card_id'], $merchant_id);
                    
                    //会员卡折扣率
                    $member_discounts = $member_card_info['discount'];
                    $member_discounts = (float)sprintf('%0.2f',$member_discounts);
                }
            } 
            
        }else{
            //没有传token，无优惠方式，无会员折扣信息
            $member_discounts = '';
            $merchant_setting_info['salepay_member'] = 0;
            $merchant_setting_info['salepay_coupon'] = 0;
            $merchant_setting_info['salepay_credit'] = 0;
        }
        
        
        //会员折扣信息整数
        if(is_int($member_discounts)){
            $member_discounts = (int)$member_discounts;
        }
        
         
        //接口返回数据
        $data = [
             
            'store_id' => $store_id,//门店id
            'name' => $store_info_name,//门店名称
            'salepay_member' => $merchant_setting_info['salepay_member'],//优惠买单是否能使用会员折扣：0->否，1->是
            'salepay_coupon' => $merchant_setting_info['salepay_coupon'],//优惠买单是否能使用优惠券：0->否，1->是
            'salepay_credit' => $merchant_setting_info['salepay_credit'],//优惠买单是否能使用积分抵扣：0->否，1->是
            'member_discounts' => $member_discounts//会员折扣信息 
            
        ];
         
         
         
        return ['errcode' => 0,'errmsg' => '获取优惠买单信息成功','data' => $data];
    }
    
    
    
    
    /**
     * 优惠买单-输入金额或选择优惠券，计算优惠金额
     * @author wangshen@dodoca.com
     * @cdate 2017-10-27
     *
     * @param float $salepay_amount 优惠买单消费金额
     * @param int $coupon_id 优惠券id（非必传）
     */
    public function salepayCalculate(Request $request){
         
        //参数
        $params = $request->all();
         
        $merchant_id = $this->merchant_id;//商户id
    
        $member_id = $this->member_id;//会员id
    
        if(!$merchant_id){
            return ['errcode' => 99001,'errmsg' => '商户ID不存在'];
        }
    
        if(!$member_id){
            return ['errcode' => 99001,'errmsg' => '会员ID不存在'];
        }
         
         
        //优惠买单消费金额
        $salepay_amount = isset($params['salepay_amount']) ? (float)$params['salepay_amount'] : 0;
         
        if(!$salepay_amount){
            return ['errcode' => 160015,'errmsg' => '消费金额参数缺失'];
        }
         
        if (!preg_match('/^[0-9]+(.[0-9]{1,2})?$/', $salepay_amount)) {
            return ['errcode' => 160016,'errmsg' => '消费金额参数错误'];
        }
         
        $salepay_amount = (float)sprintf('%0.2f',$salepay_amount);
         
         
        //优惠券id（非必传）
        $coupon_id = isset($params['coupon_id']) ? (int)$params['coupon_id'] : 0;
         
         
        //优惠方式设置信息
        $merchant_setting_info = MerchantSetting::get_data_by_id($merchant_id);
         
        $salepay_member = $merchant_setting_info['salepay_member'];//优惠买单是否能使用会员折扣：0->否，1->是
        $salepay_coupon = $merchant_setting_info['salepay_coupon'];//优惠买单是否能使用优惠券：0->否，1->是
        $salepay_credit = $merchant_setting_info['salepay_credit'];//优惠买单是否能使用积分抵扣：0->否，1->是
        
         
        //会员折扣
        $member_amount = 0;//会员折扣，优惠金额
         
        if($salepay_member == 1){
            //可以使用会员折扣，计算会员折扣
             
            //查询会员信息
            $member_info = MemberModel::get_data_by_id($member_id, $merchant_id);
             
            if($member_info['member_card_id']){
                 
                //查询会员卡信息
                $member_card_info = MemberCard::get_data_by_id($member_info['member_card_id'], $merchant_id);
                 
                if($member_card_info['discount'] > 0 && $member_card_info['discount'] < 10) {
                    $card_discount = $member_card_info['discount'] / 10;
                     
                    $vip_price = (float)sprintf('%0.2f',$salepay_amount * $card_discount);
                    $member_amount = (float)sprintf('%0.2f',$salepay_amount - $vip_price);//会员折扣，优惠金额
                     
                }
            }
        }
         
        //会员折扣优惠后，实际消费金额
        $salepay_amount = (float)sprintf('%0.2f',$salepay_amount - $member_amount);
         
        //优惠券
        $coupon_amount = 0;//优惠券，优惠金额
         
        if($salepay_coupon == 1 && $coupon_id){
            
            //优惠券子表信息
            $coupon_code_info = CouponCode::get_data_by_id($coupon_id, $merchant_id);
            
            if($coupon_code_info){
                
                //优惠券主表信息
                $coupon_info = Coupon::get_data_by_id($coupon_code_info['coupon_id'], $merchant_id);
                
                if($coupon_info){
                
                    //判断是否可使用的优惠券
                    if($coupon_info['rang_goods'] == 1 || ($coupon_info['is_condition'] == 1 && $salepay_amount < $coupon_info['condition_val'])){
                    
                        //去除指定商品的优惠券 || 满减条件但是消费金额未到满减金额的优惠券
                        return ['errcode' => 160019,'errmsg' => '优惠券不可用'];
                    }
                    
                    //计算优惠金额
                    if($coupon_info['content_type'] == 1){//现金券
                        
                        $coupon_amount = $coupon_info['coupon_val'];//优惠券，优惠金额
                        
                        if($coupon_amount > $salepay_amount){
                            $coupon_amount = $salepay_amount;
                        }
                        
                    }elseif($coupon_info['content_type'] == 2){//折扣券
                        
                        if($coupon_info['coupon_val'] > 0 && $coupon_info['coupon_val'] < 10) {
                            $coupon_discount = $coupon_info['coupon_val'] / 10;
                             
                            $coupon_price = (float)sprintf('%0.2f',$salepay_amount * $coupon_discount);
                            $coupon_amount = (float)sprintf('%0.2f',$salepay_amount - $coupon_price);//优惠券，优惠金额
                                   
                        }
                    }
                }
            }
        }
         
        //优惠券优惠后，实际消费金额
        $salepay_amount = (float)sprintf('%0.2f',$salepay_amount - $coupon_amount);
         
        //积分抵扣
        $credit_amount = 0;//积分数
        $credit_ded_amount = 0;//积分，抵扣金额
         
        if($salepay_credit == 1){
            $get_use_credit = $this->buyService->get_credit(['merchant_id' => $merchant_id,'member_id' => $member_id,'amount' => $salepay_amount]);
            
            if($get_use_credit && isset($get_use_credit['credit_amount']) && isset($get_use_credit['credit_ded_amount'])) {
                $credit_amount = $get_use_credit['credit_amount'];//积分数
                $credit_ded_amount = $get_use_credit['credit_ded_amount'];//积分，抵扣金额
            }
        }
         
        //积分优惠后，实际消费金额
        $salepay_amount = (float)sprintf('%0.2f',$salepay_amount - $credit_ded_amount);
         
        
        
        //接口返回数据
        $data = [
    
            'total_amount' => $salepay_amount,//合计金额
            'member_amount' => $member_amount,//会员折扣，优惠金额
            'coupon_amount' => $coupon_amount,//优惠券，优惠金额
            'credit_amount' => $credit_amount,//积分数
            'credit_ded_amount' => $credit_ded_amount//积分，抵扣金额
             
        ];
        	
        return ['errcode' => 0,'errmsg' => '获取优惠金额信息成功','data' => $data];
    }
    
    
    
    
    
    
    
    /**
     * 优惠买单-去支付
     * @author wangshen@dodoca.com
     * @cdate 2017-10-27
     *
     * @param float $salepay_amount 优惠买单消费金额
     * @param int $coupon_id 优惠券id（非必传）
     * @param int $store_id 门店id
     */
    public function salepayTopay(Request $request){
         
        //参数
        $params = $request->all();
         
        $merchant_id = $this->merchant_id;//商户id
    
        $member_id = $this->member_id;//会员id
    
        if(!$merchant_id){
            return ['errcode' => 99001,'errmsg' => '商户ID不存在'];
        }
    
        if(!$member_id){
            return ['errcode' => 99001,'errmsg' => '会员ID不存在'];
        }
        
        
        $store_id = isset($params['store_id']) ? (int)$params['store_id'] : 0;//门店id
		        
        //门店信息
        $store_info = Store::get_data_by_id($store_id, $merchant_id);
         
//         if(!$store_info){
//             return ['errcode' => 160003,'errmsg' => '门店信息不存在'];
//         }

        if($store_info){         
            if($store_info['is_delete'] == -1){
                return ['errcode' => 160004,'errmsg' => '该门店已删除'];
            }
             
            if($store_info['enabled'] == 0){
                return ['errcode' => 160005,'errmsg' => '该门店是关店状态'];
            }
        }
         
        //优惠买单消费金额
        $salepay_amount = isset($params['salepay_amount']) ? (float)$params['salepay_amount'] : 0;
         
        if(!$salepay_amount){
            return ['errcode' => 160015,'errmsg' => '消费金额参数缺失'];
        }
         
        if (!preg_match('/^[0-9]+(.[0-9]{1,2})?$/', $salepay_amount)) {
            return ['errcode' => 160016,'errmsg' => '消费金额参数错误'];
        }
         
        $salepay_amount = (float)sprintf('%0.2f',$salepay_amount);
         
         
        //优惠券id（非必传）
        $coupon_id = isset($params['coupon_id']) ? (int)$params['coupon_id'] : 0;
        
        //调用下单接口
		$paydata = array(
			'merchant_id'	=>	$merchant_id,
			'member_id'		=>	$member_id,
			'order_type'	=>	ORDER_SALEPAY,
			'amount'		=>	$salepay_amount,
			'store_id'		=>	$store_id,
			'coupon_id'		=>	$coupon_id,
			'is_credit'		=>	1,	//是否使用积分（1-使用，0-不使用）
		);
		//启动事务
		DB::beginTransaction();
		try{
			$payinfo = $this->buyService->createorder($paydata);
			if($payinfo && isset($payinfo['errcode']) && $payinfo['errcode']==0) {
				//事务提交
				DB::commit();
			} else {
				DB::rollBack();
			}
			return $payinfo;
		}catch (\Exception $e) {
			DB::rollBack();
			return ['errcode' => '40090', 'errmsg' => '提交失败->'.$e->getMessage()];
		}
    }    
    
    /**
     * 买单记录
     * @author wangshen@dodoca.com
     * @cdate 2017-10-31
     *
     */
    public function getSalepayList(Request $request){
        
        //参数
        $params = $request->all();
        
        $merchant_id = $this->merchant_id;//商户id
    
        $member_id = $this->member_id;//会员id
    
        if(!$merchant_id){
            return ['errcode' => 99001,'errmsg' => '商户ID不存在'];
        }
    
        if(!$member_id){
            return ['errcode' => 99001,'errmsg' => '会员ID不存在'];
        }
        
        
        //分页参数
        $page = isset($params['page']) ? (int)$params['page'] : 1;//页码
        $pagesize = isset($params['pagesize']) ? (int)$params['pagesize'] : 10;//每页条数
        
        //偏移量/每页数量
        $offset = ($page - 1) * $pagesize;
        $limit = $pagesize;
        
        
        //条件
        $wheres = [];
        $wheres[] = ['column' => 'merchant_id','operator' => '=','value' => $merchant_id];
        $wheres[] = ['column' => 'member_id','operator' => '=','value' => $member_id];
        $wheres[] = ['column' => 'order_type','operator' => '=','value' => ORDER_SALEPAY];//订单类型为优惠买单
        $wheres[] = ['column' => 'pay_status','operator' => '=','value' => 1];//支付状态：1-已支付
        $wheres[] = ['column' => 'is_valid','operator' => '=','value' => 1];//1有效
        
        //查询字段
        $fields = 'id,order_sn,store_id,amount,goods_amount,pay_time';
        
        //数量
        $_count = OrderInfo::get_data_count($wheres);
        
        //列表数据
        $order_list = OrderInfo::get_data_list($wheres,$fields,$offset,$limit);
        
        if($order_list){
            foreach($order_list as $key => $val){
        
                //门店信息
                $store_info = Store::get_data_by_id($val['store_id'], $merchant_id);
                
                if($store_info){
                    $order_list[$key]['store_name'] = $store_info['name'];
                }else{
                    $order_list[$key]['store_name'] = '';
                }
                
        
                //去除不必要字段
                unset($order_list[$key]['store_id']);
        
            }
        }
        
        
        $data['_count'] = $_count;
        $data['data'] = $order_list;
        
        
        return ['errcode' => 0,'errmsg' => '获取列表成功','data' => $data];
        
        
        
    }
    
    
    /**
     * 用户的适用所有商品的优惠券列表（优惠买单用）
     * @author wangshen@dodoca.com
     * @cdate 2017-11-2
     *
     */
    public function salepayCouponList(Request $request){
        
        //参数
        $params = $request->all();
        
        $merchant_id = $this->merchant_id;//商户id
        
        $member_id = $this->member_id;//会员id

        if(!$merchant_id){
            return ['errcode' => 99001,'errmsg' => '商户ID不存在'];
        }
        
        if(!$member_id){
            return ['errcode' => 99001,'errmsg' => '会员ID不存在'];
        }
        
        //优惠买单消费金额
        $salepay_amount = isset($params['salepay_amount']) ? (float)$params['salepay_amount'] : 0;
         
        if(!$salepay_amount){
            return ['errcode' => 160015,'errmsg' => '消费金额参数缺失'];
        }
         
        if (!preg_match('/^[0-9]+(.[0-9]{1,2})?$/', $salepay_amount)) {
            return ['errcode' => 160016,'errmsg' => '消费金额参数错误'];
        }
         
        $salepay_amount = (float)sprintf('%0.2f',$salepay_amount);
        
        
        //判断是否有会员折扣
        //优惠方式设置信息
        $merchant_setting_info = MerchantSetting::get_data_by_id($merchant_id);
         
        $salepay_member = $merchant_setting_info['salepay_member'];//优惠买单是否能使用会员折扣：0->否，1->是
        
         
        //会员折扣
        $member_amount = 0;//会员折扣，优惠金额
         
        if($salepay_member == 1){
            //可以使用会员折扣，计算会员折扣
             
            //查询会员信息
            $member_info = MemberModel::get_data_by_id($member_id, $merchant_id);
             
            if($member_info['member_card_id']){
                 
                //查询会员卡信息
                $member_card_info = MemberCard::get_data_by_id($member_info['member_card_id'], $merchant_id);
                 
                if($member_card_info['discount'] > 0 && $member_card_info['discount'] < 10) {
                    $card_discount = $member_card_info['discount'] / 10;
                     
                    $vip_price = (float)sprintf('%0.2f',$salepay_amount * $card_discount);
                    $member_amount = (float)sprintf('%0.2f',$salepay_amount - $vip_price);//会员折扣，优惠金额
                     
                }
            }
        }
         
        //会员折扣优惠后，实际消费金额
        $salepay_amount = (float)sprintf('%0.2f',$salepay_amount - $member_amount);
        
        
        
        
        
        //分页参数
        $page = isset($params['page']) ? (int)$params['page'] : 1;//页码
        $pagesize = isset($params['pagesize']) ? (int)$params['pagesize'] : 10;//每页条数
        
        //偏移量/每页数量
        $offset = ($page - 1) * $pagesize;
        $limit = $pagesize;
        
        //列表数据（总数）
        $coupon_code_list = CouponCode::select('id','coupon_id','code','start_time','end_time')
                                    ->where('merchant_id','=',$merchant_id)
                                    ->where('member_id','=',$member_id)
                                    ->where('status','=',0)//未使用
                                    ->where('is_delete','=',1)//未删除
                                    ->where('start_time', '<=', date('Y-m-d H:i:s'))
                                    ->where('end_time', '>=', date('Y-m-d H:i:s'))
                                    ->orderBy('id','DESC')
                                    ->get()->toArray();
        
             
        
        //筛选适用所有商品的优惠券
        if($coupon_code_list){
            foreach($coupon_code_list as $key => $val){
                
                //优惠券信息
                $coupon_info = Coupon::get_data_by_id($val['coupon_id'], $merchant_id);
                
                
                if($coupon_info['rang_goods'] == 1 || ($coupon_info['is_condition'] == 1 && $salepay_amount < $coupon_info['condition_val'])){
                    
                    //去除指定商品的优惠券 || 满减条件但是消费金额未到满减金额的优惠券
                    unset($coupon_code_list[$key]);
                }else{
                    
                    //日期格式转换
                    $coupon_code_list[$key]['start_time'] = date('Y.m.d', strtotime($val['start_time']));
                    $coupon_code_list[$key]['end_time']   = date('Y.m.d', strtotime($val['end_time']));
                    
                    //查询优惠券主表信息
                    $coupon_code_list[$key]['coupon_card_color'] = $coupon_info['card_color'];//颜色
                    $coupon_code_list[$key]['coupon_name'] = $coupon_info['name'];//优惠券名字
                    $coupon_code_list[$key]['coupon_content_type'] = $coupon_info['content_type'];//优惠内容 1->现金券  2->折扣券
                    $coupon_code_list[$key]['coupon_coupon_val'] = $coupon_info['coupon_val'];//若优惠内容为1，则是抵扣金额，若优惠内容为2，则是折扣率
                    $coupon_code_list[$key]['coupon_memo'] = $coupon_info['memo'];//优惠券使用说明
                    $coupon_code_list[$key]['coupon_is_condition'] = $coupon_info['is_condition'];//生效条件 0->无条件 1->满减条件
                    $coupon_code_list[$key]['coupon_condition_val'] = $coupon_info['condition_val'];//生效条件->满减条件(不含运费)
                    
                    //拼接优惠券详细说明
                    $coupon_code_list[$key]['coupon_remark'] = '';
                    
                    //生效条件 0->无条件 1->满减条件
                    if($coupon_info['is_condition'] == 1){
                        $coupon_code_list[$key]['coupon_remark'] .= '满'.$coupon_info['condition_val'].'元';
                    }else{
                        $coupon_code_list[$key]['coupon_remark'] .= '无门槛';
                    }
                    
                    //优惠内容 1->现金券  2->折扣券
                    if($coupon_info['content_type'] == 1){
                        $coupon_code_list[$key]['coupon_remark'] .= '减'.$coupon_info['coupon_val'].'元';
                    }else{
                        $coupon_info['coupon_val'] = floatval($coupon_info['coupon_val']);
                        $coupon_code_list[$key]['coupon_remark'] .= $coupon_info['coupon_val'].'折';
                    }
                    
                    
                    
                    
                    //去除不必要字段
                    unset($coupon_code_list[$key]['coupon_id']);
                    
                }
                
            }
        }
        
        //数量
        $_count = count($coupon_code_list);
        
        //截取分页数据
        $coupon_code_list = array_slice($coupon_code_list, $offset, $limit);
        
        
        
        
        $data['_count'] = $_count;
        $data['data'] = $coupon_code_list;
        
        
        return ['errcode' => 0,'errmsg' => '获取列表成功','data' => $data];
    }
    
    
    
    public function repalce_str($str) {
        return str_replace(array("'",'"','>','<',"\\"),array('','','','',''),$str);
    }

    /*获取门店版的客服设置
     * @param string $page_type     order：订单  cart：购物车  personal ：我的  store： 门店
     */
    public function getCustomer(Request $request){
        //参数
        $params = $request->all();
        $page_type = isset($params['page_type']) ? $params['page_type'] : 0;//页码
        if(!$page_type){
            return ['errcode' => 99001,'errmsg' => '页面类型不能为空'];
        }
        $merchant_id = isset($params['merchant_id']) ? $params['merchant_id'] : 0;//商户id
        if(!$merchant_id){
            return ['errcode' => 99004,'errmsg' => '商户ID不能为空'];
        }
        $weapp_id = isset($params['weapp_id']) ? $params['weapp_id'] : 0;//小程序id
        if(!$weapp_id){
            return ['errcode' => 99001,'errmsg' => '小程序ID不能为空'];
        }
        
        $customer = $this->designService->getCustomer($merchant_id, $weapp_id, $page_type);

        return ['errcode' => 0,'errmsg' => '获取客服设置成功','data' => $customer];
    }
}
