<?php

namespace App\Http\Controllers\Admin\Index;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Holidaymarketing;
use App\Models\HolidaymarketingActivity;
use App\Models\HolidaymarketingTag;
use App\Models\HolidaymarketingMerchant;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Http\Request;

class HolidaymarketingController extends Controller
{
    
    /**
     * 选择节日营销活动
     */
    function postMarketingid(Request $request){
        //营销活动id
        if( !isset($request['marketing_id']) || empty($request['marketing_id']) ){
            $rt['errcode']=110001;
            $rt['errmsg']='营销活动id 不能为空';
            return Response::json($rt);
        }
        //节日营销--营销活动id
        if( !isset($request['holiday_marketing_activity_id']) || empty($request['holiday_marketing_activity_id']) ){
            $rt['errcode']=110001;
            $rt['errmsg']='节日营销--营销活动id 不能为空';
            return Response::json($rt);
        }
        $rs_HolidaymarketingActivity = HolidaymarketingActivity::get_data_by_id($request['holiday_marketing_activity_id']);
        if(empty($rs_HolidaymarketingActivity['code'])){
            $rt['errcode']=110001;
            $rt['errmsg']='节日营销--营销活动 出错';
            return Response::json($rt);
        }
        //节日营销--商家活动管理
        $rs_HolidaymarketingMerchant = HolidaymarketingMerchant::where([
            'holiday_marketing_activity_id'=>$request['holiday_marketing_activity_id'],
            'merchant_id'=>Auth::user()->merchant_id
        ])->first();
        if(empty($rs_HolidaymarketingMerchant)){
            $data_holiday_marketing_merchant['holiday_marketing_activity_id'] = (int)$request['holiday_marketing_activity_id'];
            $data_holiday_marketing_merchant['code'] = $rs_HolidaymarketingActivity['code'];
            $data_holiday_marketing_merchant['merchant_id'] = Auth::user()->merchant_id;
            $data_holiday_marketing_merchant['marketing_id'] = $request['marketing_id'];
            $data_holiday_marketing_merchant['is_delete'] = 1;
            $data_holiday_marketing_merchant['created_time']=date('Y-m-d H:i:s');
            $data_holiday_marketing_merchant['updated_time']=date('Y-m-d H:i:s');
            
            $rs_HolidaymarketingMerchant = HolidaymarketingMerchant::insertGetId($data_holiday_marketing_merchant);
        }else{
            $data_holiday_marketing_merchant['marketing_id'] = $request['marketing_id'];
            $data_holiday_marketing_merchant['updated_time']=date('Y-m-d H:i:s');
            
            $rs_HolidaymarketingMerchant = HolidaymarketingMerchant::update_data($rs_HolidaymarketingMerchant['id'],$data_holiday_marketing_merchant);
        }
        
        if(!$rs_HolidaymarketingMerchant){
            $rt['errcode']=110002;
            $rt['errmsg']='提交 失败';
            $rt['data'] = array();
            return Response::json($rt);
        }
        
        $rt['errcode']=0;
        $rt['errmsg']='提交 成功';
        $rt['data'] = array('');
        return Response::json($rt);
    }
    
    /**
     * 节日营销活动列表
     * Author: songyongshang@dodoca.com
     */
    public function getHolidaymarketingMerchantList(Request $request) {
        //节日营销--营销活动id
        if( !isset($request['holiday_marketing_activity_id']) || empty($request['holiday_marketing_activity_id']) ){
            $rt['errcode']=110001;
            $rt['errmsg']='节日营销--营销活动id 不能为空';
            return Response::json($rt);
        }
        $wheres[] = array('column' => 'holiday_marketing_activity_id', 'value' => $request['holiday_marketing_activity_id'], 'operator' => '=');
        $wheres[] = array('column' => 'merchant_id', 'value' => Auth::user()->merchant_id, 'operator' => '=');
        $offset = isset($this->params['page']) ? intval($this->params['page']) : 1;
        $limit = isset($this->params['pagesize']) ? intval($this->params['pagesize']) : 10;
        $offset = ($offset - 1) * $limit;
        $result = HolidaymarketingMerchant::get_data_list($wheres, 'id,content,created_time,updated_time', $offset, $limit);
        
        $data['errcode'] = 0;
        $data['_count'] = HolidaymarketingMerchant::get_data_count($wheres);
        $data['data'] = $result;

        return Response :: json($data);
    }
    
    /**
     * 节日列表
     * Author: songyongshang@dodoca.com
     */
    public function getHolidaymarketingList(Request $request) {
        $wheres[] = array('column' => 'merchant_id', 'value' => Auth::user()->merchant_id, 'operator' => '=');
        $wheres[] = array('column' => 'is_delete', 'value' => 1, 'operator' => '=');
        $wheres[] = array('column' => 'is_delete', 'value' => 1, 'operator' => '=');
        $offset = isset($this->params['page']) ? intval($this->params['page']) : 1;
        $limit = isset($this->params['pagesize']) ? intval($this->params['pagesize']) : 2;
        $offset = ($offset - 1) * $limit;
        $result = HolidaymarketingMerchant::get_data_list($wheres, '*', $offset, $limit);
    
        $data['errcode'] = 0;
        $data['_count'] = HolidaymarketingMerchant::get_data_count($wheres);
        $data['data'] = $result;
    
        return Response :: json($data);
    }
}
