<?php

namespace App\Http\Controllers\Admin\Shop;


use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Http\Request;
use App\Models\Shop;

class ShopController extends Controller
{
    public function postShop(Request $request)
    {
        $param = $request->all();
        $merchant_id = Auth::user()->merchant_id;
        $data = array(
            'merchant_id' => $merchant_id,
            'cate_id' => $param['cate_id'],
            'name' => $param['name'],
            'logo' => $param['logo'],
            'price_field_alias' => $param['price_field_alias'],
            'kefu_mobile' => $param['kefu_mobile'],
            'csale_show' => $param['csale_show']
        );
        $first = Shop::where(['merchant_id'=>$merchant_id])->first();
        if(!$first){
            $result = Shop::create($data);
        }else{
            //$result = Shop::where(['id'=>$param['id'],'merchant_id'=>$merchant_id])->update($data);
            $result = Shop::update_data($merchant_id,$data);
        }
        return $result ? Response::json(['errcode'=>0]) : Response::json(['errcode'=>100001,'errmsg'=>'设置失败']);
    }

    public function putShop(Request $request)
    {
        $param = $request->all();
        $merchant_id = Auth::user()->merchant_id;
        $shop = Shop::where(['merchant_id'=>$merchant_id])->first();
        if(!$shop){
            return Response::json(['errcode'=>100002,'errmsg'=>'店铺不存在']);
        }
        $data = array(
            'merchant_id' => $merchant_id,
            'cate_id' => $param['cate_id'],
            'name' => $param['name'],
            'logo' => $param['logo'],
            'price_field_alias' => $param['price_field_alias'],
            'kefu_mobile' => $param['kefu_mobile'],
            'csale_show' => $param['csale_show']
        );
        //$result = Shop::where(['merchant_id'=>$merchant_id])->update($data);
        $result = Shop::update_data($merchant_id,$data);
        return $result ? Response::json(['errcode'=>0]) : Response::json(['errcode'=>100003,'errmsg'=>'更新失败']);
    }

    public function getShop()
    {
        $merchant_id = Auth::user()->merchant_id;
        $shop = Shop::where(['merchant_id'=>$merchant_id])->first();
        if(!$shop){
            return Response::json(['errcode'=>100004,'errmsg'=>'店铺不存在']);
        }
        $shop['errcode'] = 0;
        return Response::json($shop);
    }
}
