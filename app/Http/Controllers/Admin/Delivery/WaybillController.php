<?php

namespace App\Http\Controllers\Admin\Delivery;

use App\Http\Controllers\Controller;
use App\Models\DeliveryCompany;
use App\Models\MerchantDelivery;
use App\Models\Waybill;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;

//运单模块
class WaybillController extends Controller {

    public function __construct() {
        $this->merchant_id = Auth::user()->merchant_id;
    }

    //查询列表
    public function getWaybills(Request $request) {
        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 0;

        $query = Waybill::where('merchant_id',$this->merchant_id)->where('is_delete',1);
        $result['_count'] = $query->count();
        $result['data'] = array();
        if ($result['_count'] > 0) {
            $result['data'] = $query->skip($offset)->take($limit)->get();
            foreach ($result['data'] as &$v) {
                $v['print_items'] = json_decode($v['print_items'],true);
                $v['delivery_company_name'] = DeliveryCompany::where('id',$v['delivery_company_id'])->value('name');
            }
        }
        return Response::json(['errcode'=>0,'_count'=>$result['_count'],'data'=>$result['data']]);
    }

    //单运单查询
    public function getWaybill($id) {
        $data = Waybill::where('id',$id)->where('merchant_id',$this->merchant_id)->first();
        if($data){
            $data['print_items'] = json_decode($data['print_items'],true);
            $delivery_company_id = $data['delivery_company_id'];
            $data['is_exist'] = MerchantDelivery::where('delivery_company_id',$delivery_company_id)->where('merchant_id',$this->merchant_id)->where('is_delete',1)->count();
        }
        return Response::json(['errcode'=>0,'data'=>$data]);
    }

    //添加运单
    public function postWaybill(Request $request) {
        $param = $request->all();
        $name = isset($param['name']) ? $param['name'] : '';
        if(!$name){
            return Response::json(['errcode'=>1,'errmsg'=>'缺少必要参数']);
        }
        $data = array(
            'merchant_id' => $this->merchant_id,
            'company' => isset($param['company']) && $param['company'] ? $param['company'] : '',
            'name' => $name,
            'delivery_company_id' => isset($param['delivery_company_id']) ? $param['delivery_company_id'] : '',
            'img' => isset($param['img']) ? $param['img'] : '',
            'width' => isset($param['width']) ? $param['width'] : '',
            'height' => isset($param['height']) ? $param['height'] : '',
            'size' => isset($param['size']) ? $param['size'] : '',
            'print_items' => isset($param['print_items']) ? json_encode($param['print_items']) : '',
            'created_time' => Carbon::now(),
            'updated_time' => Carbon::now(),
            'is_delete'=>1
        );
        Waybill::create($data);
        return Response::json(['errcode'=>0,'errmsg'=>'操作成功']);
    }

    //修改运单
    public function putWaybill(Request $request, $id) {
        $param = $request->all();
        $name = isset($param['name']) ? $param['name'] : '';
        if(!$name){
            return Response::json(['errcode'=>1,'errmsg'=>'缺少必要参数']);
        }
        $data = array(
            'company' => isset($param['company']) && $param['company'] ? $param['company'] : '',
            'name' => $name,
            'delivery_company_id' => isset($param['delivery_company_id']) ? $param['delivery_company_id'] : '',
            'img' => isset($param['img']) ? $param['img'] : '',
            'width' => isset($param['width']) ? $param['width'] : '',
            'height' => isset($param['height']) ? $param['height'] : '',
            'size' => isset($param['size']) ? $param['size'] : '',
            'print_items' => isset($param['print_items']) ? json_encode($param['print_items']) : '',
            'updated_time' => Carbon::now()
        );
        $isset = Waybill::where('id',$id)->where('merchant_id',$this->merchant_id)->update($data);
        if($isset){
            return Response::json(['errcode'=>0,'errmsg'=>'操作成功']);
        }else{
            return Response::json(['errcode'=>2,'errmsg'=>'操作失败']);
        }
    }

    //删除运单
    public function deleteWaybill($id) {
        $reuslt = Waybill::where('id',$id)->where('merchant_id',$this->merchant_id)->delete();
        if($reuslt){
            return Response::json(['errcode'=>0,'errmsg'=>'操作成功']);
        }else{
            return Response::json(['errcode'=>2,'errmsg'=>'操作失败']);
        }
    }

}
