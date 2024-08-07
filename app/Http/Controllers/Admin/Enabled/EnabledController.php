<?php

/**
 * 门店自提相关代码控制器
 * changzhixian
 */
namespace App\Http\Controllers\Admin\Enabled;


use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\MerchantSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;


class EnabledController extends Controller
{

    private $merchant_id;

    public function __construct()
    {
        $this->merchant_id = Auth::user()->merchant_id;
    }

    /*
    * 上门自提别名读取
    * */
    /*public function getEnabledName(Request $request) {


        $params['merchant_id'] = $this->merchant_id;//商户id
        $datas = MerchantSetting::get_data_by_id($params['merchant_id']);
        if($datas){
            $data =array();
            $datas_array = $datas->toArray();
            $data['selffetch_alias'] =  $datas_array['selffetch_alias'];
            return ['errcode' => "0", 'errmsg' => "获取上门自提别名成功",'data' => $data];
        }else{
            return ['errcode' => "1002", 'errmsg' => "获取上门自提别名失败",'data' => ""];
        }
    }*/

    /*
     * 上门自提别名设置
     * */
    public function putEnabledName(Request $request) {

        //参数
        $params['selffetch_alias'] = $request['selffetch_alias'];

        $rules = [
            'selffetch_alias'           => 'required|string'
        ];
        $messages = [
            'selffetch_alias.required'   => 'selffetch_alias为必填',
            'selffetch_alias.string'    => '非法的selffetch_alias'
        ];
        $validator = Validator::make( $params ,$rules ,$messages );
        if($validator->fails()){
            $error = $validator->errors()->first();
            return ['errcode' => 10001, 'errmsg' => $error];
            exit;
        }

        $params['merchant_id'] = $this->merchant_id;//商户id
        $data_srevice = MerchantSetting::update_data($params['merchant_id'],$params);
        if($data_srevice > 0 ){
            return ['errcode' => "0", 'errmsg' => "成功"];
        }else{
            return ['errcode' => "1002", 'errmsg' => "失败"];
        }
    }

    /*
    * 商户上门自提功能开启/关闭
    * */
    public function putEnabled(Request $request) {

        //参数
        $params['store_enabled'] = $request['store_enabled'];

        $rules = [
            'store_enabled'           => 'required|boolean'
        ];
        $messages = [
            'store_enabled.required'   => 'store_enabled为必填',
            'store_enabled.boolean'    => '非法的store_enabled'
        ];
        $validator = Validator::make( $params ,$rules ,$messages );
        if($validator->fails()){
            $error = $validator->errors()->first();
            return ['errcode' => 10001, 'errmsg' => $error];
            exit;
        }

        $params['merchant_id'] = $this->merchant_id;//商户id
        $datas = MerchantSetting::get_data_by_id($params['merchant_id']);

        if($params['store_enabled'] == 0 && $datas['appoint_enabled']==0 && $datas['delivery_enabled']==0){
			return ['errcode' => "1002", 'errmsg' => "关闭失败：物流配送、同城配送和上门自提不能全关闭",'data' => []];
		}
		
        //更新数据

        $data_srevice = MerchantSetting::update_data($params['merchant_id'],$params);
        if($data_srevice > 0 ){
            return ['errcode' => "0", 'errmsg' => "成功"];
        }else{
            return ['errcode' => "1002", 'errmsg' => "失败"];
        }
    }

    /*
    * 门店上门自提功能开启/关闭
    * */
    public function putEnabledStore(Request $request) {

        //参数
        $params['id'] = $request['id'];
        $params['extract_enabled'] = $request['extract_enabled'];

        $rules = [
            'id'               => 'required|integer',
            'extract_enabled'  => 'required|boolean'
        ];
        $messages = [
            'id.required'                => 'id为必填',
            'id.integer'                 => '非法的id',
            'extract_enabled.required'   => 'extract_enabled为必填',
            'extract_enabled.boolean'    => '非法的extract_enabled'
        ];
        $validator = Validator::make( $params ,$rules ,$messages );
        if($validator->fails()){
            $error = $validator->errors()->first();
            return ['errcode' => 10001, 'errmsg' => $error];
            exit;
        }

        $params['merchant_id'] = $this->merchant_id;//商户id
        //更新数据

        $data_srevice = Store::update_data($params['id'],$params['merchant_id'],$params);
        if($data_srevice > 0 ){
            return ['errcode' => "0", 'errmsg' => "成功"];
        }else{
            return ['errcode' => "1002", 'errmsg' => "失败"];
        }
    }
	
	 /*
    * 物流配送开启/关闭
    * */
    public function putDelivery(Request $request) {

        //参数
        $params['delivery_enabled'] = isset($request['delivery_enabled']) ? (int)$request['delivery_enabled'] : 0;
        $setinfo = MerchantSetting::get_data_by_id($this->merchant_id);
		
        if($setinfo){
            if($params['delivery_enabled'] == 0 && $setinfo['store_enabled']==0 && $setinfo['appoint_enabled']==0) {
				return ['errcode' => "1002", 'errmsg' => "关闭失败：物流配送、同城配送和上门自提不能全关闭",'data' => []];
			}
			$data_srevice = MerchantSetting::update_data($this->merchant_id,$params);
			if($data_srevice > 0 ){
				return ['errcode' => "0", 'errmsg' => "更新成功"];
			}else{
				return ['errcode' => "1002", 'errmsg' => "更新失败"];
			}
        } else {
			$data['merchant_id'] = $this->merchant_id;
            MerchantSetting::insert_data($data);
			return ['errcode' => "0", 'errmsg' => "更新成功"];
		}
    }

}
