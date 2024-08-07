<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018-04-25
 * Time: 下午 01:45
 */
namespace App\Http\Controllers\Admin\Delivery;


use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Models\MerchantSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Http\Request;
use App\Models\Store;

class AppointController extends Controller
{
    public function __construct()
    {
        $this->merchant_id = Auth::user()->merchant_id;
//        $this->merchant_id = 2;
    }

    /**
     * 预约配置详情
     */
    public function getdetail(Request $request){
        $detail = MerchantSetting::get_data_by_id($this->merchant_id);
        $result = array();
       /* if($detail){
            $result['appoint_enabled'] = $detail['appoint_enabled'];
            $result['advance_min_hour'] = $detail['advance_min_hour'];
            $result['advance_max_day'] = $detail['advance_max_day'];
            $result['time_slot_start'] = $detail['time_slot_start'];
            $result['time_slot_end'] = $detail['time_slot_end'];
        }*/
        $rt['errcode']=0;
        $rt['detail'] = $detail;
        return Response::json($rt);
    }

    /**
     * @param Request $request
     * 保存配置
     */
    public function edit(Request $request){
        $param = $request->all();
        $data = array();
        $data['appoint_enabled'] = isset($param['appoint_enabled'])?$param['appoint_enabled']:0;
        $data['advance_min_hour'] = isset($param['advance_min_hour'])?$param['advance_min_hour']:0;
        $data['advance_max_day'] = isset($param['advance_max_day'])?$param['advance_max_day']:0;
        $data['time_slot_start'] = isset($param['time_slot_start'])?$param['time_slot_start']:0;
        $data['time_slot_end'] = isset($param['time_slot_end'])?$param['time_slot_end']:0;
        $data['city_distance'] = isset($param['city_distance'])?$param['city_distance']:0;
        $data['city_cost'] = isset($param['city_cost'])?$param['city_cost']:0;

        $detail = MerchantSetting::get_data_by_id($this->merchant_id);
		
		if($data['appoint_enabled'] == 0 && $detail['store_enabled']==0 && $detail['delivery_enabled']==0){
			return ['errcode' => "1002", 'errmsg' => "关闭失败：物流配送、同城配送和上门自提不能全关闭",'data' => $data];
		}
		
        if($data){
            if($detail){
                MerchantSetting::update_data($this->merchant_id,$data);
            }else{
                $data['merchant_id'] = $this->merchant_id;
                MerchantSetting::insert_data($data);
            }
        }
        $rt['errcode']=0;
        return Response::json($rt);
    }

    public function isShow(Request $request){
        $param = $request->all();
        $ntime = isset($param['ntime'])?$param['ntime']:date("Y-m-d H:i:s"); //下单时间
        $detail = MerchantSetting::get_data_by_id($this->merchant_id);
        if($detail['appoint_enabled']==1 && $ntime){
            //可选配送日期
            $distribution_end_time= date("Y-m-d ".$detail['time_slot_end'].":00",strtotime("+ ".$detail['advance_max_day']." day",strtotime($ntime)));  //可选开始日期
            $distribution_start_time = date("Y-m-d ".$detail['time_slot_start'].":00",strtotime("+ ".$detail['advance_min_hour']." hour",strtotime($ntime)));

            $new_time = array();
            for($i=$distribution_start_time;$i<=$distribution_end_time;){
                $ymd = date("Y-m-d",strtotime($i));
                $hi = date("H:i",strtotime($i));
                $h = date("H",strtotime($i));
                if($i > date("Y-m-d H:i",strtotime("+".$detail['advance_min_hour']." hour",strtotime($ntime)))){
                    $new_time[$ymd][]=$hi;
                }
                if($h<$detail['time_slot_end'] && $h>=$detail['time_slot_start']){
                    $i = date('Y-m-d H:i',strtotime('+ 30 minute',strtotime($i)));
                }else{
                    $i = date("Y-m-d ".$detail['time_slot_start'].":00",strtotime('+ 1 day',strtotime($i)));
                }

            }
            $result['errcode'] = 0;
            $result['ntime'] = $new_time;
        }else{
            $result['errcode'] = 1;
            return $result;
        }
        return $result;
    }
	
	/*
    * 同城配送门店开启/关闭
    * */
    public function putEnabledStore(Request $request) {

        //参数
        $store_id = (int)$request['id'];
        $city_enabled = isset($request['city_enabled']) ? (int)$request['city_enabled'] : 0;
		
		if(!$store_id) {
			return ['errcode' => 10001, 'errmsg' => '缺失必要参数'];
		}

        $data_srevice = Store::update_data($store_id,$this->merchant_id,['city_enabled'=>$city_enabled]);
        if($data_srevice > 0 ){
            return ['errcode' => "0", 'errmsg' => "更新成功"];
        }else{
            return ['errcode' => "1002", 'errmsg' => "更新失败"];
        }
    }

}