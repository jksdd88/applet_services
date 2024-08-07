<?php

/**
 * 优惠买单控制器
 * changzhixian
 */
namespace App\Http\Controllers\Admin\Salepay;

use App\Http\Controllers\Controller;
use App\Models\MerchantSetting;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class SalepayController extends Controller
{

    private $merchant_id;

    public function __construct()
    {
        $this->merchant_id = Auth::user()->merchant_id;
//        $this->merchant_id = 2;
    }


    /*
     * 优惠买单方式列表
     * */
    public function getSalepay() {

        $params['merchant_id'] = $this->merchant_id;//商户id
        $datas = MerchantSetting::get_data_by_id($params['merchant_id']);

        if($datas){
            $data =array();
            $datas_array = $datas->toArray();
            $data['salepay_member'] =  $datas_array['salepay_member'];
            $data['salepay_coupon'] =  $datas_array['salepay_coupon'];
            $data['salepay_credit'] =  $datas_array['salepay_credit'];
            return ['errcode' => "0", 'errmsg' => "获取优惠买单方式设置成功",'data' => $data];
        }else{
            return ['errcode' => "1002", 'errmsg' => "获取优惠买单方式设置失败",'data' => ""];
        }
    }
    /**
     * 编辑优惠买单方式信息
     * chang
     * 20171025 12:00
     */
    public function putSalepay(Request $request)
    {

        //参数
        $params['salepay_member'] = $request['salepay_member'];
        $params['salepay_coupon'] = $request['salepay_coupon'];
        $params['salepay_credit'] = $request['salepay_credit'];

        $rules = [
            'salepay_member'           => 'required|boolean',
            'salepay_coupon'           => 'required|boolean',
            'salepay_credit'           => 'required|boolean'
        ];
        $messages = [
            'salepay_member.required'   => 'salepay_member为必填',
            'salepay_member.boolean'    => '非法的salepay_member',
            'salepay_coupon.required'   => 'salepay_coupon为必填',
            'salepay_coupon.boolean'    => '非法的salepay_coupon',
            'salepay_credit.required'   => 'salepay_credit为必填',
            'salepay_credit.boolean'    => '非法的salepay_credit'
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
            return ['errcode' => "0", 'errmsg' => "编辑成功"];
        }else{
            return ['errcode' => "1002", 'errmsg' => "编辑失败"];
        }
    }



}
