<?php

namespace App\Http\Controllers\Weapp\Vipcard;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Models\MemberCard;
use App\Models\Member as MemberModel;
use App\Models\Shop;
use App\Facades\Member;

class VipcardController extends Controller
{
    /**
     * 获取会员的信息
     **/
    public function getVipCard(Request $request)
    {
        $member_id   = Member::id();
        $merchant_id = Member::merchant_id();

        if(!$merchant_id){
            return ['errcode' => 99001, 'errmsg' => '商户ID不存在'];
        }

        if(!$member_id){
            return ['errcode' => 99001, 'errmsg' => '会员ID不存在'];
        }

        $shop_name = Shop::where('merchant_id', $merchant_id)->value('name');

        $member = MemberModel::get_data_by_id($member_id, $merchant_id);

        $member_account = $member['id'] + MEMBER_CONST;
        //会员卡ID
        $member_card_id = $member['member_card_id'];

        if($member_card_id > 0){
            $member_card = MemberCard::get_data_by_id($member_card_id, $merchant_id);

            $member_card['member_account'] = $member_account;
            $member_card['shop_name']      = $shop_name;
            $member_card['discount']       = floatval($member_card['discount']);

            if($member_card['card_type'] == 2){
                $member_card['member_card_overtime'] = $member['member_card_overtime'];
            }

            return ['errcode' => 0, 'data' => $member_card];
        }

        $data = [
            'card_name'      => '默认会员',
            'bg_color'       => '',
            'bg_img'         => '',
            'member_account' => $member_account,
            'shop_name'      => $shop_name
        ];
        return ['errcode' => 0, 'data' => $data];
    }
}
