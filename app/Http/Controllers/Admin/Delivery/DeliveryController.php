<?php
/**
 * auther:beller
 * date:2015-7-16
 */

namespace App\Http\Controllers\Admin\Delivery;


use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Models\DeliveryCompany;
use App\Models\MerchantDelivery;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class DeliveryController extends Controller
{
    public function __construct()
    {
        $this->merchant_id = Auth::user()->merchant_id;
        //$this->merchant_id = 1;
    }

    /**
     * 获取物流列表
     * @return mixed
     */
    public function getDelivery(){
        $query = DeliveryCompany::where('id','>',0);
        $data['errcode'] = 0;
        $data['_count'] = $query->count();
        $data['data'] = array();
        if($data['_count'] > 0){
            $data['data'] = $query->get();
            foreach ($data['data'] as &$v){
                $v['print_default'] = json_decode($v['print_default'],true);
            }
        }
        $data['onDelivery'] = $this->getMerchantDelivery();
        return Response::json($data, 200);
    }

    //商家已选物流
    private function getMerchantDelivery(){
        $list = MerchantDelivery::where('merchant_id',$this->merchant_id)->where('is_delete',1)->get();
        foreach ($list as &$v){
            $info = DeliveryCompany::where('id',$v['delivery_company_id'])->first();
            $v['name'] = $info['name'];
            $v['code'] = $info['code'];
            $v['print_default'] = json_decode($info['print_default'],true);
            $v['waybill_bg'] = $info['waybill_bg'];
            $v['waybill_width'] = $info['waybill_width'];
            $v['waybill_height'] = $info['waybill_height'];
        }
        return $list;
    }

}
