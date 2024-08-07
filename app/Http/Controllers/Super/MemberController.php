<?php

/*
 * 会员模块后台
 * shangyazhao@dodoca.com
 *
 */

namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\User;
use App\Models\Merchant;

class MemberController extends Controller {
    protected $request;
    protected $params;
    protected $member;
    protected $member_card;
    protected $user;

    public function __construct(Request $request, Member $member, MemberCard $member_card, User $user) {
        $this->request = $request;
        $this->params = $request->all();
        $this->member = $member;
        $this->member_card = $member_card;
        $this->user = $user;
    }

    /*
     * 获取会员卡列表
     */
    public function getCardList() {
        $offset = isset($this->params['offset']) ? $this->params['offset'] : 0;

        $limit = isset($this->params['limit']) ? $this->params['limit'] : 10;

        $card_name = isset($this->params['card_name']) ? trim($this->params['card_name']) : '';

        $card_type = isset($this->params['card_type']) ? intval($this->params['card_type']) : 0;

        $query = $this->member_card->select('member_card.id','member_card.merchant_id','member_card.card_name','member_card.card_level','member_card.card_type','member_card.amount_limit','member_card.trade_limit','member_card.points_limit','member_card.bg_color',
            'member_card.bg_img','member_card.description','member_card.discount','member_card.is_postage_free','member_card.service_tel','member_card.created_time','member_card.updated_time');

        $query->where('is_delete','=',1);

        if (!empty($card_name)) {  //会员卡名称搜索
            $query->where('card_name','like','%' . $card_name . '%');
        }

        if ($card_type > 0) {    //会员卡类型
            $query->where('card_type','=',$card_type);
        }

        $column = isset($this->params['column']) ? trim($this->params['column']) : 'created_time';

        if (!in_array($column, array('id', 'merchant_id', 'title', 'created_time', 'updated_time'))) {
            $column = 'created_time';
        }
        $sort = isset($this->params['sort']) ? trim($this->params['sort']) : '';

        if (!in_array($sort, array('desc', 'asc'))) {
            $sort = 'desc';
        }

        $company =  isset($this->params['company']) ? trim($this->params['company']) : '';

        $merchantid = isset($this->params['merchantid']) ? trim($this->params['merchantid']) : '';

        //根据商家名称搜索
        if(!empty($company)){
            $query->leftJoin("merchant","merchant.id","=","member_card.merchant_id");
            $query->where( 'merchant.company', "like", '%' . $company . '%');
        }

        //根据商家id搜索
        if(!empty($merchantid)){
            $query->where( 'member_card.merchant_id', "=", $merchantid);
        }

        $data['_count'] = $query->count();

        $result = $query->orderBy('member_card.created_time', 'DESC')->skip($offset)->take($limit)->get()->toArray();

        $data['errcode'] = 0;

        $data['data'] = $result;

        $where = [];

        $public_open_id = isset($this->params['account']) ? trim($this->params['account']) : '';

        $id=0;

        if (!empty($public_open_id)) {//会员账号等于会员开好常量+会员id
            $id = intval($public_open_id) - MEMBER_CONST;
        }

        if ($public_open_id > 0) {
            $where[] = array('column' => 'id', 'value' => $id, 'operator' => '=');
        }

        $offset = ($offset - 1) * $limit;

        $fields = 'id,merchant_id,public_open_id,member_card_id,member_card_overtime,total_amount,purchased_count,credit,total_credit,
            latest_buy_time,latest_access_time,name,avatar,gender,mobile,is_verify_mobile,country,province,city,created_time,updated_time';

        $member_result = $this->member->get_data_list($where, $fields, $offset, $limit);

        $member_data['errcode'] = 0;

        $member_data['_count'] = $this->member->get_data_count($where);

        if($member_data["_count"] > 0){
            foreach ($result as $key =>$value){
                $data['data'][$key]['account'] = '';
                $data['data'][$key]['company'] = '';
                foreach ($member_result as $k => $val) {
                    if($value['id']==$val['member_card_id']){
                        $data['data'][$key]['account'] = MEMBER_CONST + $val['id']; //会员账号等于会员开好常量+会员id
                        break;
                    }
                }
                //商户名称字段
                $merchant=Merchant::get_data_by_id($value["merchant_id"]);
                if($merchant){
                    $data['data'][$key]['company']=$merchant["company"];
                }
            }
        }
        return Response :: json($data);
    }

    /*
     * 会员卡查看
     */
    public function getCard() {
        $id = $this->request->id;
        if (!$id) {
            $data['errcode'] = 20004;
            $data['errmsg'] = '会员卡不存在';
            $data['data'] = [];
            return Response::json($data);
        }
        $result = MemberCard::where(['id'=>$id,'is_delete'=>1])->first();
        if (!$result) {
            $data['errcode'] = 20004;
            $data['errmsg'] = '会员卡不存在';
            $data['data'] = [];
            return Response::json($data);
        }
        $data['errcode'] = 0;
        $data['data'] = $result;
        return Response::json($data);
    }

    /*
     * 获取会员列表
     */
    public function memberList() {
        $offset = isset($this->params['offset']) ? $this->params['offset'] : 0;
        $limit = isset($this->params['limit']) ? $this->params['limit'] : 10;
        $public_open_id = isset($this->params['account']) ? trim($this->params['account']) : '';

        $id=0;
        if (!empty($public_open_id)) {//会员账号等于会员开好常量+会员id
            $id = intval($public_open_id) - MEMBER_CONST;
        }
        $mobile = isset($this->params['mobile']) ? trim($this->params['mobile']) : '';

        $member_card_id = isset($this->params['member_card_id']) ? intval($this->params['member_card_id']) : 0;

        $query = $this->member->select('member.id','member.merchant_id','member.public_open_id','member.member_card_id','member.member_card_overtime','member.total_amount','member.purchased_count','member.credit','member.total_credit',
            'member.latest_buy_time','member.latest_access_time','member.name','member.avatar','member.gender','member.mobile','member.is_verify_mobile','member.country','member.province','member.city','member.created_time','member.updated_time');


        if ($id > 0) {
            $query->where('id','=',$id);

        } else if(!empty ($public_open_id)){
            $query->where('id','=',0);
        }
        if ($member_card_id > 0) {
            $query->where('member_card_id','=',$member_card_id);
        }
        if (!empty($mobile)) {
            $query->where('mobile','=',$mobile);
        }

        $company =  isset($this->params['company']) ? trim($this->params['company']) : '';

        $merchantid = isset($this->params['merchantid']) ? trim($this->params['merchantid']) : '';

        //根据商家名称搜索
        if(!empty($company)){
            $query->leftJoin("merchant","merchant.id","=","member.merchant_id");
            $query->where( 'merchant.company', "like", '%' . $company . '%');
        }

        //根据商家id搜索
        if(!empty($merchantid)){
            $query->where( 'member.merchant_id', "=", $merchantid);
        }

        $data['_count'] = $query->count();

        $result = $query->orderBy('member.created_time', 'DESC')->skip($offset)->take($limit)->get()->toArray();

        $data['errcode'] = 0;

        if ($data["_count"] > 0) {
            foreach ($result as $key => $value) {
                $result[$key]['account'] = MEMBER_CONST + $value['id']; //会员账号等于会员开好常量+会员id
            }
        }
        $data['data'] = $result;

        $where = array(
            array('column' => 'is_delete', 'value' => 1, 'operator' => '=')
        );

        $result = $this->member_card->get_data_list($where, 'id,card_name,card_type', '', 0, 1000);

        foreach ($data['data'] as $key => $value) {
            $up_type = '自动升级';
            $card_id = $value['member_card_id'];
            $data['data'][$key]['member_card_name'] = '';           
            $data['data'][$key]['member_card_name']='默认等级';
            $data['data'][$key]['card_type'] = 1;
            $data['data'][$key]['company'] = '';
            foreach ($result as $ke => $val) {
                if ($val['id'] == $card_id) {
                    $data['data'][$key]['member_card_name'] = $val['card_name'];
                    $data['data'][$key]['card_type'] = $val['card_type'];
                    break;
                }
            }
            //商户名称字段
            $merchant=Merchant::get_data_by_id($value["merchant_id"]);
            if($merchant){
                $data['data'][$key]['company']=$merchant["company"];
            }

        }
        $data['member_card'] = $result;
        return Response :: json($data);
    }

}
