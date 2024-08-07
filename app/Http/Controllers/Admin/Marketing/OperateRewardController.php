<?php

namespace App\Http\Controllers\Admin\marketing;

use App\Models\Merchant;
use App\Models\MerchantSetting;
use App\Models\OperateRewardDetail;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use \Milon\Barcode\DNS2D;

use App\Utils\Encrypt;

class OperateRewardController extends Controller
{
    private $merchant_id;

    function __construct(OperateRewardDetail $OperateRewardDetail, Request $request)
    {
        $this->params = $request->all();
        $this->model = $OperateRewardDetail;
        if (app()->isLocal()) {
            $this->merchant_id = 2;
        } else {
            $this->merchant_id = Auth::user()->merchant_id;
        }

    }
    /**
     *  邀请链接
     */
    function getoperateRewardLink()
    {
        if (empty($this->merchant_id)) {
            return Response::json(['errcode' => 100001, 'errmsg' => '非法操作']);
        }
        $merchant_id = $this->merchant_id;
        if(isset($merchant_id) && !empty($merchant_id)){

            /*
                开发联调域名（develop）：develop
                开发联调域名（applet）：develop-applet
                开发联调域名（xcx）：develop-xcx
                QA环境域名  ：test
                集成环境域名：release
                生产环境域名：production
            */

            $APP_ENV = ENV('APP_ENV');
            $ulr_pc = "";
            $url_m = "";
            //dd($APP_ENV);
            if($APP_ENV == 'production'){
                $ulr_pc = "http://we.dodoca.com/";
                $url_m = "http://m.dodoca.com/xiaochengxu";
            }elseif ($APP_ENV == 'test'){
                $ulr_pc = "http://twe.dodoca.com/";
                $url_m = "http://tm.dodoca.com/xiaochengxu";
            }elseif ($APP_ENV == 'develop' || $APP_ENV == 'develop-xcx' || $APP_ENV == 'develop-applet' || $APP_ENV == 'local'){
                $ulr_pc = "http://twe.dodoca.com/";
                $url_m = "http://tm.dodoca.com/xiaochengxu";
            }
            if ($ulr_pc == "" || $url_m=="") {
                return Response::json(['errcode' => 100003, 'errmsg' => '非法操作:未获取到APP_ENV']);
            }
            $data['link'] = $ulr_pc."?operateReward=".$merchant_id;
            $data['link_m'] = $url_m."?operateReward=".$merchant_id.'&m=m#form-box';
            $data['qrcode'] = 'data:image/png;base64,' . DNS2D::getBarcodePNG($data['link_m'], "QRCODE", "10", "10");
            $merchant = Merchant::where(['id'=>$this->merchant_id,])->whereRaw('status!=-1')->first();
            if($merchant['version_id']!=1){//免费版
                $data['link'] = "";
                $data['link_m'] = "";
                $data['qrcode'] = "";
                return Response::json(['errcode' => 0, 'errmsg' => '获取邀请链接失败：此商户版本不是免费版', 'data' => $data]);
            }
            return Response::json(['errcode' => 0, 'errmsg' => '获取邀请链接成功', 'data' => $data]);
        }
        return Response::json(['errcode' => 100002, 'errmsg' => '获取邀请链接失败']);
    }

    /**
     *  奖励记录
     */
    function getoperateRewardList(Request $request)
    {
        if (empty($this->merchant_id)) {
            return Response::json(['errcode' => 100001, 'errmsg' => '非法操作']);
        }
        //参数
        $params['act_type'] = $request['act_type'];
        $params['offset'] = $request['offset'];
        $params['limit'] = $request['limit'];
        $rules = [
            'act_type'        => 'required|in:1',
            'offset'        => 'integer|min:0',
            'limit'         => 'integer|min:1'
        ];
        $messages = [
            'act_type.required'         => 'act_type是必传的',
            'act_type.in'               => 'act_type不在允许区间内',
            'offset.integer'            => '非法的offset',
            'offset.min'                => '非法的offset',
            'limit.integer'             => '非法的limit',
            'limit.min'                 => '非法的limit'
        ];
        $validator = Validator::make( $params ,$rules ,$messages );
        if($validator->fails()){
            $error = $validator->errors()->first();
            return ['errcode' => 10001, 'errmsg' => $error];
            exit;
        }

        //奖励统计
        $referee_count = Merchant::where(['referee_merchant_id' => $this->merchant_id])->count();
        $reward_number = $this->model->where(['referee_merchant_id' => $this->merchant_id,'act_type' => 1])->sum('reward');
        $data['statistics']['referee_count'] = $referee_count > 0 ? $referee_count : 0;//累计邀请商户数
        $data['statistics']['reward_number'] = $reward_number > 0 ? $reward_number : 0;//累计获得商品上架数量（包含已失效奖励）

        $offset = isset($params['offset']) ? $params['offset'] : 0;
        $limit = isset($params['limit']) ? $params['limit'] : 10;
        $query = $this->model->where(['referee_merchant_id' => $this->merchant_id,'act_type' => 1]);
        $count = $query->count();
        $info = $query->orderBy('created_time', 'DESC')
            ->skip($offset)
            ->take($limit)
            ->get();
        foreach ($info as $key => $value) {
            $company= Merchant::where(['id' => $value['merchant_id']])->pluck('company');
            $info[$key]['company'] = isset($company) ? $company : "";
        }
        $data['list_info']['count'] = $count > 0 ? $count : 0;
        $data['list_info']['list'] = isset($info) ? $info : "";
        return Response::json(['errcode' => 0, 'errmsg' => '成功', 'data' => $data]);
    }

}
