<?php

namespace App\Http\Controllers\Weapp\Merchant;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\WeixinInfo;
use App\Facades\Member;

class MerchantController extends Controller
{
    /**
     * 获取商户信息
     *
     * @return \Illuminate\Http\Response
     */
    public function info(Request $request)
    {
        $merchant_id = Member::merchant_id();
        $weapp_id    = Member::weapp_id();

        if(!$merchant_id){
            return ['errcode' => 99001, 'errmsg' => '商户ID是必须的'];
        }

        $info = Merchant::get_data_by_id($merchant_id);

        if($info){
            if(in_array($info['version_id'], [2, 3, 4, 6])  ){
                if(strtotime($info['expire_time']) < time()){
                    return ['errcode' => 99007, 'errmsg' => '商家服务已到期'];
                }
            }

            $data = [
                'version_id' => $info['version_id'],
                'company'    => $info['company'],
                'logo'       => $info['logo'],
                'is_demo'    => $info['is_demo']
            ];

            if($weapp_id){
                $wxinfo = WeixinInfo::check_one_id($weapp_id);
                if($wxinfo){
                    $data['weapp_name']   = $wxinfo['nick_name'];
                    $data['weapp_icon']   = $wxinfo['head_img'];
                    $data['weapp_qrcode'] = $wxinfo['qrcode'];
                }
            }

            return ['errcode' => 0, 'errmsg' => '获取数据成功', 'data' => $data];
        }

        return ['errcode' => 99005, 'errmsg' => '商家不存在'];
    }
}
