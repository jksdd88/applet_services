<?php

namespace App\Http\Controllers\Admin\Delivery;

use App\Http\Controllers\Controller;
use App\Models\MerchantDelivery;
use App\Models\MerchantSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class MerchantDeliveryController extends Controller {

    public function __construct() {
        $this->merchant_id = Auth::user()->merchant_id;
        //$this->merchant_id = 1;
    }

    /**
     * 添加物流
     * @return mixed
     */
    public function postMerchantDeliver(Request $request) {
        $requestData = $request->all();
        $id = isset($requestData['id']) ? $requestData['id'] : '';//物流公司id
        if(!$id){
            return Response::json(['errcode'=>1,'errmsg'=>'缺少必要参数']);
        }
        //判断是否添加
        $is_add = MerchantDelivery::where('merchant_id',$this->merchant_id)->where('delivery_company_id',$id)->first();
        if($is_add){
            if($is_add['is_delete']==1){
                return Response::json(['errcode'=>2,'errmsg'=>'请勿重复添加']);
            }else{
                $is_add->is_delete = 1;
            }
            if($is_add->save()){
                return Response::json(['errcode'=>0,'delivery_id'=>$id]);
            }else{
                return Response::json(['errcode'=>3,'errmsg'=>'操作失败']);
            }
        }else{
            $data = array(
                'merchant_id' => $this->merchant_id,
                'delivery_company_id' => $id,
                'is_delete'=>1,
                'created_time'=>Carbon::now(),
                'updated_time'=>Carbon::now()
           );
            MerchantDelivery::create($data);
            return Response::json(['errcode'=>0,'delivery_id'=>$id]);
        }

    }

    /**
     * 删除物流公司
     * @param $id
     * @return mixed
     */
    public function deleteMerchantDeliver($id) {
        MerchantDelivery::where('id',$id)->where('merchant_id',$this->merchant_id)->delete();
        return Response::json(['errcode'=>0,'errmsg'=>'操作成功']);
    }

    /**
     * 获取配送别名
     */
    public function getAliasSetting(){
        $info = MerchantSetting::get_data_by_id($this->merchant_id);//3 报错了,先注释掉
		$data = [
			'errcode'			=>	0,
			'delivery_alias'	=>	isset($info['delivery_alias']) ? $info['delivery_alias'] : '',
			'selffetch_alias'	=>	isset($info['selffetch_alias']) ? $info['selffetch_alias'] : '',
		];
        return Response::json($data);
    }
    /**
     * 设置配送别名
     */
    public function setAliasSetting(Request $request){
        $params = $request->all();

        $delivery_alias = isset($params['delivery_alias']) ? $params['delivery_alias'] : '';
        $selffetch_alias = isset($params['selffetch_alias']) ? $params['selffetch_alias'] : '';
        if(!$delivery_alias){
            return Response::json(['errcode'=>1,'errmsg'=>'物流配送别名必填']);
        }
        if(!$selffetch_alias){
            return Response::json(['errcode'=>1,'errmsg'=>'上门自提别名必填']);
        }
        $data = MerchantSetting::get_data_by_id($this->merchant_id);
        if(!$data){
            return Response::json(['errcode'=>2,'errmsg'=>'设置信息不存在']);
        }
		$udata = [
			'delivery_alias'	=>	$delivery_alias,
			'selffetch_alias'	=>	$selffetch_alias,
		];
		
        if(MerchantSetting::update_data($this->merchant_id,$udata)){
            return Response::json(['errcode'=>0,'errmsg'=>'设置成功']);
        }else{
            return Response::json(['errcode'=>3,'errmsg'=>'设置失败']);
        }
    }

}
