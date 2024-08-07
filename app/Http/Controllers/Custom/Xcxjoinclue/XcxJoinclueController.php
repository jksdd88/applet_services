<?php

/**
 * 小程序加盟线索
 * @author wangshen@dodoca.com
 * @cdate 2018-4-9
 * 
 */
namespace App\Http\Controllers\Custom\Xcxjoinclue;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Models\XcxJoinclue;
use App\Models\Region;



class XcxJoinclueController extends Controller {
    
    
    /**
     * 提交线索
     * @author wangshen@dodoca.com
     * @cdate 2018-4-9
     */
    public function postClue(Request $request){
        
        //参数
        $params = $request->all();
        
        $name          = isset($params['name'])          ? $this->repalce_str($params['name'])          : '';   //姓名
        $mobile        = isset($params['mobile'])        ? $this->repalce_str($params['mobile'])        : '';   //手机号
        $company       = isset($params['company'])       ? $this->repalce_str($params['company'])       : '';   //公司
        $province_name = isset($params['province_name']) ? $this->repalce_str($params['province_name']) : '';   //省份名称
        $city_name     = isset($params['city_name'])     ? $this->repalce_str($params['city_name'])     : '';   //城市名称
        $address       = isset($params['address'])       ? $this->repalce_str($params['address'])       : '';   //详细地址
        $type          = isset($params['type'])          ? (int)$params['type']                         : 0;    //来源：1->H5端（线下），2->小程序端（线下），3->H5端（线上），4->小程序端（线上）
        
        
        if($type == 1){
            $origin = 24;
        }elseif($type == 2){
            $origin = 25;
        }elseif($type == 3){
            $origin = 27;
        }elseif($type == 4){
            $origin = 28;
        }else{
            return ['errcode' => 99001,'errmsg' => '来源不正确'];
        }
        
        
        //origin
        //'24' => '线下渠道招商-H5'
        //'25' => '线下渠道招商-小程序'
        //'27' => '线上渠道招商-H5'
        //'28' => '线上渠道招商-小程序'
        
        //调用crm接口
        $crm_data = [
             'username' => $name,
             'province_str' => $province_name,
             'city_str' => $city_name,
             'address' => $address,
             'mobile' => $mobile,
             'guest_name' => $company,
             'clue_type' => 6,  //6 代表线索分类是软件代理
             'business_type' => 7,  //7 代表线索业务类别是渠道小程序
             'sem_user_visit_id'=>'',
             'origin' => $origin	//'24' => '渠道招商-H5','25' => '渠道招商-小程序'
        ];
        
        //接口地址
        if(ENV('APP_ENV')=='production'){
            $apiurl = 'http://crm.dodoca.com/sale/clueapi';
        }else{
            $apiurl = 'http://t.crm.dodoca.com/sale/clueapi';
        }
        
        $crm_rs = mxCurl($apiurl, $crm_data);
        $crm_rs = json_decode($crm_rs,true);
        
        
        $insert_data = [
            'name' => $name,
            'mobile' => $mobile,
            'company' => $company,
            'province_name' => $province_name,
            'city_name' => $city_name,
            'address' => $address,
            'type' => $type,
            'crm_post' => json_encode($crm_data,JSON_UNESCAPED_UNICODE),
            'crm_res' => json_encode($crm_rs,JSON_UNESCAPED_UNICODE)
        ];
        
        
        $rs = XcxJoinclue::insert_data($insert_data);
        
        if($rs){
            return ['errcode' => 0,'errmsg' => '提交成功'];
        }else{
            return ['errcode' => 99001,'errmsg' => '提交失败，请稍后再试'];
        }
        
    }
    
    
    public function repalce_str($str) {
        return str_replace(array("'",'"','>','<',"\\"),array('','','','',''),$str);
    }
    
    
    
    /**
     * 获取省市区数据
     *
     * @param string $member_id     会员ID
     * @param string $id            地址ID
     *
     * @return \Illuminate\Http\Response
     */
    public function getRegion()
    {
        //获取省份
        $provinces = Region::get_data_by_parentid(1);
    
        $cityArray     = [];
        $districtArray = [];
        foreach($provinces as $province){
            $citys = Region::get_data_by_parentid($province->id);
            $cityArray[$province->id] = $citys;
    
            foreach($citys as $city){
                $districts = Region::get_data_by_parentid($city->id);
                $districtArray[$city->id] = $districts;
            }
        }
    
        $data = [
            'provinces' => $provinces,
            'citys'     => $cityArray,
            'districts' => $districtArray
        ];
    
        return ['errcode' => 0, 'data' => $data];
    }
    
}
