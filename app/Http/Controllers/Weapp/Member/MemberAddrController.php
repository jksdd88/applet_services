<?php

namespace App\Http\Controllers\Weapp\Member;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Models\MemberAddress;
use App\Models\Region;
use Illuminate\Support\Facades\Response;
use App\Facades\Member;

class MemberAddrController extends Controller
{   
    protected $member_id;

    public function __construct(){
        $this->member_id = Member::id();
    }
	
     /**
     * 获取会员收货地址列表
     *
     * @param string $page     第几页
     * @param string $pagesize 每页数量
     * @param string $member_id  会员ID
     *
     * @return \Illuminate\Http\Response
     */
    public function getAddress(Request $request){
        $params = $request->all();
        $merchant_id = Member::merchant_id();
        $pagesize    = $request->input('pagesize', 20);
        $page        = $request->input('page', 1);
        $offset      = ($page - 1) * $pagesize;

        if(!$merchant_id){
            return ['errcode' => 99001, 'errmsg' => '商户ID不存在'];
        }
        if(!$this->member_id){
            return ['errcode' => 99001, 'errmsg' => '会员ID不存在'];
        }

        $wheres = array(
            array('column'=>'member_id', 'value'=>$this->member_id, 'operator'=>'='),
            array('column'=>'is_delete', 'value'=>1, 'operator'=>'='),
        );

        $total = MemberAddress::get_data_count($wheres);

        //只有1条数据时，自动设置默认地址
        if($total){
            $where[] = array('column'=>'is_default', 'value'=>1, 'operator'=>'=');
            $whereall = array_merge($wheres,$where);
            $default_cnt = MemberAddress::get_data_count($whereall);
            if(!$default_cnt){
                $address = MemberAddress::where(['member_id'=>$this->member_id,'is_delete'=>1])->orderBy('created_time', 'desc')->first();
                MemberAddress::update_data($address['id'], $this->member_id, ['is_default'=>1]);
            }
        }

        $data  = MemberAddress::get_data_list($wheres, '*', $offset, $pagesize);
        if($data){
            return ['errcode' => 0, 'errmsg' => '获取数据成功', '_count' => $total, 'data' => $data];
        }else{
            return ['errcode' => 0, 'errmsg' => '暂无数据！'];
        }
    }
	
    /**
     * 获取会员收货地址详情
     *
     * @param string $id  地址ID
     * @param string $member_id  会员ID
     *
     * @return \Illuminate\Http\Response
     */
    public function getAddr($id){
        $merchant_id = Member::merchant_id();
        if(!$merchant_id){
            return ['errcode' => 99001, 'errmsg' => '商户ID不存在'];
        }
        if(!$id){
            return ['errcode' => 99001, 'errmsg' => '地址id不能为空'];
        }
        if(!$this->member_id){
            return ['errcode' => 99001, 'errmsg' => '会员ID不存在'];
        }
        $data = MemberAddress::get_data_by_id($id,$this->member_id);
        if($data){
            return ['errcode' => 0, 'errmsg' => '获取数据成功', 'data' => $data];
        }else{
            return ['errcode' => 0, 'errmsg' => '暂无数据！'];
        }
        
    }
	
     /**
     * 添加收货地址
     *
     * @param string $member_id     会员ID
     * @param string $consignee     收货人
     * @param string $mobile        联系电话
     * @param string $country       国家
     * @param string $province      省份
     * @param string $city          城市
     * @param string $district      区域
     * @param string $address       详细地址
     * @param string $zipcode       邮编
     *
     * @return \Illuminate\Http\Response
     */
    public function postAddr(Request $request){
        $params = $request->all();
        $merchant_id = Member::merchant_id();
        if(!$merchant_id){
            return ['errcode' => 99001, 'errmsg' => '商户ID不存在'];
        }
        if(!$this->member_id){
            return ['errcode' => 99001, 'errmsg' => '会员ID不存在'];
        }

        $create['member_id'] = $this->member_id;
        $create['consignee'] = isset($params['consignee']) ? $params['consignee'] : '';
        $create['mobile'] = isset($params['mobile']) ? $params['mobile'] : '';

        $create['country'] = isset($params['country']) ? $params['country'] : 1;
        $create['country_name'] = Region::get_title_by_id($create['country']);

        $province = isset($params['province']) ? $params['province'] : '';
        $city = isset($params['city']) ? $params['city'] : '';
        $district = isset($params['district']) ? $params['district'] : '';
        $create['address'] = isset($params['address']) ? $params['address'] : '';
        $create['zipcode'] = isset($params['zipcode'])?$params['zipcode']:'';
        $create['is_delete'] = 1;

        if(!$create['consignee'] || !$create['mobile'] || !$create['country'] ||!$province || !$city || !$district || !$create['address']){
            return ['errcode' => 99001, 'errmsg' => '请填写完整收货信息'];
        }
        //截取'市'
        $sub_string = mb_substr($province, -1);
        if($sub_string == '市'){
            $province = mb_substr($province, 0, -1);
        }

        $provincedata = Region::get_data_by_title($province,$create['country']);
        if(!$provincedata){
            return ['errcode' => 110001, 'errmsg' => '省份不存在！'];
        }
        $create['province'] = $provincedata['id'];
        $create['province_name'] = Region::get_title_by_id($create['province']);

        if(!in_array($create['province'],array(900000,710000,810000,820000)) )
        {
            /*if(!preg_match('#^13[\d]{9}$|^14[0-9]{1}\d{8}$|^15[^4]{1}\d{8}$|^17[0-9]{1}\d{8}$|^18[\d]{9}$#',$create['mobile'])){
                return array('errcode' => -1, 'errmsg' => '手机格式错误！');
            }*/
			 if(!preg_match('/^\d{11}$/',$create['mobile'])){
                return array('errcode' => -1, 'errmsg' => '手机格式错误！');
            }
        }
        
        $citydata = Region::get_data_by_title($city,$create['province']);
        if(!$citydata){
            return ['errcode' => 110001, 'errmsg' => '城市不存在！'];
        }
        $create['city'] = $citydata['id'];
        $create['city_name'] = Region::get_title_by_id($create['city']);

        $districtdata = Region::get_data_by_title($district,$create['city']);
        if(!$districtdata){
            return ['errcode' => 110001, 'errmsg' => '区域不存在！'];
        }
        $create['district'] = $districtdata['id'];
        $create['district_name'] = Region::get_title_by_id($create['district']);

        $result = MemberAddress::insert_data($create);
        if($result){
            return ['errcode' => 0, 'errmsg' => '添加成功', 'data' => $result];
        }else{
            return ['errcode' => 110002, 'errmsg' => '添加失败！'];
        }
    }
	
    /**
     * 修改收货地址
     *
     * @param string $member_id     会员ID
     * @param string $id            地址ID
     * @param string $consignee     收货人
     * @param string $mobile        联系电话
     * @param string $country       国家
     * @param string $province      省份
     * @param string $city          城市
     * @param string $district      区域
     * @param string $address       详细地址
     * @param string $zipcode       邮编
     *
     * @return \Illuminate\Http\Response
     */
    public function putAddr($id,Request $request){
        $merchant_id = Member::merchant_id();
        if(!$merchant_id){
            return ['errcode' => 99001, 'errmsg' => '商户ID不存在'];
        }
        if(!$id){
            return ['errcode' => 99001, 'errmsg' => '地址id不能为空'];
        }
        if(!$this->member_id){
            return ['errcode' => 99001, 'errmsg' => '会员ID不存在'];
        }
        $params = $request->all();

        $addr = MemberAddress::get_data_by_id($id,$this->member_id);                     
        if(!$addr){
            return ['errcode' => 110001, 'errmsg' => '暂无数据！'];
        }
        $addr['consignee'] = isset($params['consignee']) ? $params['consignee'] : '';
        $addr['mobile'] = isset($params['mobile']) ? $params['mobile'] : '';

        $addr['country'] = isset($params['country']) ? $params['country'] : 1;
        $addr['country_name'] = Region::get_title_by_id($addr['country']);

        $province = isset($params['province']) ? $params['province'] : '';
        $city = isset($params['city']) ? $params['city'] : '';
        $district = isset($params['district']) ? $params['district'] : '';
        $addr['address'] = isset($params['address']) ? $params['address'] : '';
        $addr['zipcode'] = isset($params['zipcode'])?$params['zipcode']:'';
        $addr['is_delete'] = 1;

        if(!$addr['consignee'] || !$addr['mobile'] || !$addr['country'] ||!$province || !$city || !$district || !$addr['address']){
            return ['errcode' => 99001, 'errmsg' => '请填写完整收货信息'];
        }

        //截取'市'
        $sub_string = mb_substr($province, -1);
        if($sub_string == '市'){
            $province = mb_substr($province, 0, -1);
        }
        
        $provincedata = Region::get_data_by_title($province,$addr['country']);
        if(!$provincedata){
            return ['errcode' => 110001, 'errmsg' => '省份不存在！'];
        }
        $addr['province'] = $provincedata['id'];
        $addr['province_name'] = Region::get_title_by_id($addr['province']);

        if(!in_array($addr['province'],array(900000,710000,810000,820000)) )
        {
            //if(!preg_match('#^13[\d]{9}$|^14[5,7]{1}\d{8}$|^15[^4]{1}\d{8}$|^17[0-9]{1}\d{8}$|^18[\d]{9}$#',$addr['mobile'])){
			if(!preg_match('/^\d{11}$/',$addr['mobile'])){
                return array('errcode' => -1, 'errmsg' => '手机格式错误！');
            }
        }

        $citydata = Region::get_data_by_title($city,$addr['province']);
        if(!$citydata){
            return ['errcode' => 110001, 'errmsg' => '城市不存在！'];
        }
        $addr['city'] = $citydata['id'];
        $addr['city_name'] = Region::get_title_by_id($addr['city']);

        $districtdata = Region::get_data_by_title($district,$addr['city']);
        if(!$districtdata){
            return ['errcode' => 110001, 'errmsg' => '区域不存在！'];
        }
        $addr['district'] = $districtdata['id'];
        $addr['district_name'] = Region::get_title_by_id($addr['district']);

        $result = MemberAddress::update_data($id,$this->member_id,$addr);

        if($result){
            return ['errcode' => 0, 'errmsg' => '修改成功', 'data' => $result];
        }else{
            return ['errcode' => 110002, 'errmsg' => '修改失败！'];
        }
    }
	
    /**
     * 删除收货地址
     *
     * @param string $member_id     会员ID
     * @param string $id            地址ID
     *
     * @return \Illuminate\Http\Response
     */
    public function deleteAddr($id){
        $merchant_id = Member::merchant_id();
        if(!$merchant_id){
            return ['errcode' => 99001, 'errmsg' => '商户ID不存在'];
        }
        if(!$id){
            return ['errcode' => 99001, 'errmsg' => '地址id不能为空'];
        }
        if(!$this->member_id){
            return ['errcode' => 99001, 'errmsg' => '会员ID不存在'];
        }
        $addr = MemberAddress::get_data_by_id($id,$this->member_id);                     
        if(!$addr){
            return ['errcode' => 110001, 'errmsg' => '暂无数据！'];
        }

        $result = MemberAddress::delete_data($id,$this->member_id);
        if($result){
            return ['errcode' => 0, 'errmsg' => '删除成功了', 'data' => $result];
        }else{
            return ['errcode' => 110002, 'errmsg' => '删除失败！'];
        }
    }
	
    /**
     * 设置默认地址
     *
     * @param string $member_id     会员ID
     * @param string $id            地址ID
     *
     * @return \Illuminate\Http\Response
     */
    public function putAddrDefault($id){
        $merchant_id = Member::merchant_id();
        if(!$merchant_id){
            return ['errcode' => 99001, 'errmsg' => '商户ID不存在'];
        }
        if(!$id){
            return ['errcode' => 99001, 'errmsg' => '地址id不能为空'];
        }
        if(!$this->member_id){
            return ['errcode' => 99001, 'errmsg' => '会员ID不存在'];
        }
        $all = MemberAddress::select('id','is_default')->where(['member_id' => $this->member_id,'is_delete'=>1])->get();
        foreach($all as $addr){
            MemberAddress::update_data($addr['id'], $this->member_id, ['is_default' => 0]);
        }
        $result = MemberAddress::update_data($id, $this->member_id, ['is_default' => 1]);
        if($result){
            return ['errcode' => 0, 'errmsg' => '设置成功', 'data' => $result];
        }else{
            return ['errcode' => 110002, 'errmsg' => '设置失败'];
        }
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
