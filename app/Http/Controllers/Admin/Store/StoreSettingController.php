<?php
/**
 * 门店设置
 */

namespace App\Http\Controllers\Admin\Store;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\StoreService;
use App\Models\StoreSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class StoreSettingController extends Controller
{

    private $merchant_id;

    public function __construct(StoreService $StoreService)
    {
        $this->merchant_id = isset(Auth::user()->merchant_id) ? Auth::user()->merchant_id :0;
    }

    /**
     *新增/修改门店设置
     * @return array
     */
    public function StoreSetting(Request $request)
    {
        $params = $request->all();
        $merchant_id = $this->merchant_id;
        if(!$merchant_id){
            return ['errcode' => 99004, 'errmsg' => '商户ID不存在'];
        }

        $wxinfo_id = isset($params['wxinfo_id']) ? intval($params['wxinfo_id']) : 0;  
        if(!$wxinfo_id){
            return ['errcode' => 99001,'errmsg' => '小程序ID不存在'];
        }

        $coupon_onoff = isset($params['coupon_onoff']) ? intval($params['coupon_onoff']) : 0;        // 优惠券开关设置
        $salepay_onoff = isset($params['salepay_onoff']) ? intval($params['salepay_onoff']) : 0;         // 优惠买单开关设置
        $customer_onoff = isset($params['customer_onoff']) ? intval($params['customer_onoff']) : 0;         // 客服开关设置
        //组织数据
        $storeData = [
            'merchant_id' => $this->merchant_id,
            'wxinfo_id' => $wxinfo_id,
            'coupon_onoff' => $coupon_onoff,
            'salepay_onoff' => $salepay_onoff,
        ];
        //查询本小程序是否做过设置
        $setting_exist = StoreSetting::select('id')->where(['merchant_id'=>$merchant_id,'wxinfo_id'=>$wxinfo_id])->first();
        if($setting_exist){//存在 更新设置
            $result = StoreSetting::update_data($setting_exist->id,$merchant_id, $wxinfo_id,$storeData);
        }else{//不存在 生成设置
            $result = StoreSetting::insert_data($storeData);
        }     
        if($result){
            return ['errcode' => 0, 'errmsg' => '设置成功'];
        }else{
            return ['errcode' => -1, 'errmsg' => '设置失败'];
        }

    }

    
   /**
     *读取门店设置
     * @return array
     */
    public function getStoreSetting($wxinfo_id)
    {
        $merchant_id = $this->merchant_id;
        if(!$merchant_id){
            return ['errcode' => 99004, 'errmsg' => '商户ID不存在'];
        }
        if(!$wxinfo_id){
            return ['errcode' => 99001,'errmsg' => '小程序ID不存在'];
        }

        $data = StoreSetting::get_data_by_id($merchant_id,$wxinfo_id);

         return ['errcode' => 0, 'errmsg' => '获取成功','data' => $data];
    }
   
  






}
