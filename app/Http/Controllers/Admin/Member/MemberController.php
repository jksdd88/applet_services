<?php

/*
 * 会员模块后台
 * shangyazhao@dodoca.com
 *
 */

namespace App\Http\Controllers\Admin\Member;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\Member;
use App\Models\MemberCard;
use App\Services\MemberDailyService;
use App\Services\VipcardService;
use App\Models\Merchant;
use App\Models\DistribPartner;
class MemberController extends Controller {

    protected $request;
    protected $params;
    protected $member;
    protected $member_card;
    protected $member_daily_service;
    protected $merchant_id;
    protected $version;

    public function __construct(Request $request,DistribPartner $distrib, Member $member, MemberCard $member_card, MemberDailyService $member_daily_service, Merchant $merchant) {
        $this->params = $request->all();
        $this->member = $member;
        $this->distrib = $distrib;
        $this->member_card = $member_card;
        $this->member_daily_service = $member_daily_service;
        if(app()->isLocal()){
            $this->merchant_id = 2;
        }else{
            $this->merchant_id = Auth::user()->merchant_id;
        }
        $result = $merchant->get_data_by_id($this->merchant_id);
        $this->version = 1;
        if (!empty($result)) {
            if (!empty($result['version_id'])) {
                $this->version = $result['version_id'];
            }
        }
    }

    /*
     * 获取会员卡列表
     */

    public function getCardList() {
        if (in_array($this->version, [1, 5, 6])) {
            $data['errcode'] = 20015;
            $data['errmsg'] = '您正在使用的版本尚未开通此功能，如需开通请联系您的销售顾问';
            return Response :: json($data);
        }
        $wheres[] = array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '=');
        $wheres[] = array('column' => 'is_delete', 'value' => 1, 'operator' => '=');
        $card_name = isset($this->params['card_name']) ? trim($this->params['card_name']) : '';
        $card_type = isset($this->params['card_type']) ? intval($this->params['card_type']) : 0;
        if (!empty($card_name)) {  //会员卡名称搜索
            $wheres[] = array('column' => 'card_name', 'value' => '%' . $card_name . '%', 'operator' => 'like');
        }
        if ($card_type > 0) {    //会员卡类型
            $wheres[] = array('column' => 'card_type', 'value' => $card_type, 'operator' => '=');
        }

        $column = isset($this->params['title']) ? trim($this->params['title']) : 'created_time';
        if (!in_array($column, array('id', 'merchant_id', 'title', 'created_time', 'updated_time'))) {
            $column = 'created_time';
        }
        $sort = isset($this->params['sort']) ? trim($this->params['sort']) : '';
        if (!in_array($sort, array('desc', 'asc'))) {
            $sort = 'desc';
        }
        //分页信息
        $page = isset($this->params['page']) ? intval($this->params['page']) : 1;
        $limit = isset($this->params['pagesize']) ? intval($this->params['pagesize']) : 10;
        $offset = ($page - 1) * $limit;
        $fields = 'id,merchant_id,card_name,card_level,card_type,amount_limit,trade_limit,points_limit,bg_color,
            bg_img,description,discount,is_postage_free,service_tel,created_time,updated_time';
        $result = $this->member_card->get_data_list($wheres, $fields, $sort, $offset, $limit);
        $data['errcode'] = 0;
        $data['_count'] = $this->member_card->get_data_count($wheres);
        $data['data'] = $result;
        return Response :: json($data);
    }

    /*
     * 会员卡添加
     */

    public function addCard() {
        if (in_array($this->version, [1, 5, 6])) {
            $data['errcode'] = 20015;
            $data['errmsg'] = '您正在使用的版本尚未开通此功能，如需开通请联系您的销售顾问';
            return Response :: json($data);
        }
        $data['errcode'] = 0;
        $card_name = isset($this->params['card_name']) ? trim($this->params['card_name']) : '';
        if (empty($card_name)) {
            $data['errcode'] = 20007;
            $data['errmsg'] = '会员卡名称不允许为空';
        }
        $card_level = isset($this->params['card_level']) ? intval($this->params['card_level']) : 0;
        if ($card_level == 0) {
            $data['errcode'] = 20008;
            $data['errmsg'] = '会员卡等级值必须为数字且不能为空';
        }
        $card_type = isset($this->params['card_type']) ? intval($this->params['card_type']) : 0;
        if (!in_array($card_type, array(1, 2))) {
            $data['errcode'] = 20011;
            $data['errmsg'] = '会员卡类型非法';
        }
        $amount_limit = 0;
        if ($card_type == 1) {
            $amount_limit = isset($this->params['amount_limit']) ? floatval($this->params['amount_limit']) : 0;
            if ($amount_limit <= 0) {
                $data['errcode'] = 20012;
                $data['errmsg'] = '累计消费金额必须大于0';
            }
        }
        $description = isset($this->params['description']) ? $this->params['description'] : 0;
        if (empty($description)) {
            $data['errcode'] = 20010;
            $data['errmsg'] = '会员卡使用须知不允许为空';
        }
        $discount = isset($this->params['discount']) ? floatval($this->params['discount']) : 0;        
        if ($data['errcode'] > 0) {
            return Response::json($data);
        }
        $bg_color = isset($this->params['bg_color']) ? $this->params['bg_color'] : '';
        $bg_img = isset($this->params['bg_img']) ? $this->params['bg_img'] : '';
        $is_postage_free = isset($this->params['is_postage_free']) ? $this->params['is_postage_free'] : 0;
        $service_tel = isset($this->params['service_tel']) ? $this->params['service_tel'] : '';
        $datas = array(
            "merchant_id" => $this->merchant_id,
            "card_name" => $card_name,
            "card_level" => $card_level,
            "card_type" => $card_type,
            "amount_limit" => $amount_limit,
            "bg_color" => $bg_color,
            "bg_img" => $bg_img,
            "description" => $description,
            "discount" => $discount,
            "is_postage_free" => $is_postage_free,
            "service_tel" => $service_tel,
            'is_delete' => 1
        );

        $res = $this->member_card->insert_data($datas);
        if ($res > 0) {
            $data['errcode'] = 0;
            $data['errmsg'] = '添加成功';
        } else {
            $data['errcode'] = 1;
            $data['errmsg'] = '添加失败';
        }
        return Response::json($data);
    }

    /*
     * 会员卡编辑
     */

    public function editCard($id) {
        if (in_array($this->version, [1, 5, 6])) {
            $data['errcode'] = 20015;
            $data['errmsg'] = '您正在使用的版本尚未开通此功能，如需开通请联系您的销售顾问';
            return Response :: json($data);
        }
        $data['errcode'] = 0;
        if ($id == 0) {
            $data['errcode'] = 20004;
            $data['errmsg'] = '会员卡不存在';
        }
        $card_info = $this->member_card->get_data_by_id($id, $this->merchant_id); //判断会员卡是否存在
        if (empty($card_info)) {
            $data['errcode'] = 20004;
            $data['errmsg'] = '会员卡不存在';
        }
        $card_name = isset($this->params['card_name']) ? trim($this->params['card_name']) : '';
        if (empty($card_name)) {
            $data['errcode'] = 20007;
            $data['errmsg'] = '会员卡名称不允许为空';
        }
        $card_level = isset($this->params['card_level']) ? intval($this->params['card_level']) : 0;
        if ($card_level == 0) {
            $data['errcode'] = 20008;
            $data['errmsg'] = '会员卡等级值必须为数字且不能为空';
        }
        $card_type = isset($this->params['card_type']) ? intval($this->params['card_type']) : 0;
        if (!in_array($card_type, array(1, 2))) {
            $data['errcode'] = 20011;
            $data['errmsg'] = '会员卡类型非法';
        }
        $amount_limit = 0;
        if ($card_type == 1) {
            $amount_limit = isset($this->params['amount_limit']) ? floatval($this->params['amount_limit']) : 0;
            if ($amount_limit <= 0) {
                $data['errcode'] = 20011;
                $data['errmsg'] = '会员卡类型非法';
            }
        }
        $description = isset($this->params['description']) ? $this->params['description'] : 0;
        if (empty($description)) {
            $data['errcode'] = 20010;
            $data['errmsg'] = '会员卡使用须知不允许为空';
        }
        $discount = isset($this->params['discount']) ? floatval($this->params['discount']) : 0;        
        if ($data['errcode'] > 0) {
            return Response::json($data);
        }
        $bg_color = isset($this->params['bg_color']) ? $this->params['bg_color'] : '';
        $bg_img = isset($this->params['bg_img']) ? $this->params['bg_img'] : '';
        $is_postage_free = isset($this->params['is_postage_free']) ? $this->params['is_postage_free'] : 0;
        $service_tel = isset($this->params['service_tel']) ? $this->params['service_tel'] : '';
        $datas = array(
            "merchant_id" => $this->merchant_id,
            "card_name" => $card_name,
            "card_level" => $card_level,
            "card_type" => $card_type,
            "amount_limit" => $amount_limit,
            "bg_color" => $bg_color,
            "bg_img" => $bg_img,
            "description" => $description,
            "discount" => $discount,
            "is_postage_free" => $is_postage_free,
            "service_tel" => $service_tel,
            'is_delete' => 1
        );

        $res = $this->member_card->update_data($id, $this->merchant_id, $datas);
        if ($res > 0) {
            $data['errcode'] = 0;
            $data['errmsg'] = '编辑成功';
        } else {
            $data['errcode'] = 1;
            $data['errmsg'] = '编辑失败';
        }
        return Response::json($data);
    }

    /*
     * 会员卡删除
     */

    public function deleteCard($id) {
        if (in_array($this->version, [1, 5, 6])) {
            $data['errcode'] = 20015;
            $data['errmsg'] = '您正在使用的版本尚未开通此功能，如需开通请联系您的销售顾问';
            return Response :: json($data);
        }
        if (empty($id)) {
            $data['errcode'] = 20004;
            $data['errmsg'] = '会员卡不存在';
            return Response::json($data);
        }
        //会员卡持有会员数
        $member_count = $this->member->get_data_count(array(
            array('column' => 'member_card_id', 'operator' => '=', 'value' => $id),
            array('column' => 'merchant_id', 'operator' => '=', 'value' => $this->merchant_id),
        ));  //已绑定会员的会员卡不允许删除
        if ($member_count > 0) {
            $data['errcode'] = 20005;
            $data['errmsg'] = '会员卡已绑定会员，不允许删除';
            return Response::json($data);
        }
        $res = $this->member_card->delete_data($id, $this->merchant_id);
        if ($res === false) {
            $data['errcode'] = 1;
            $data['errmsg'] = '删除失败';
        } else {
            $data['errcode'] = 0;
            $data['errmsg'] = '成功删除';
        }
        return Response::json($data);
    }

    /*
     * 会员卡查看
     */

    public function getCard() {
        if (in_array($this->version, [1, 5, 6])) {
            $data['errcode'] = 20015;
            $data['errmsg'] = '您正在使用的版本尚未开通此功能，如需开通请联系您的销售顾问';
            return Response :: json($data);
        }
        $id = isset($this->params['id']) ? intval($this->params['id']) : 0;
        if (empty($id)) {
            $data['errcode'] = 20004;
            $data['errmsg'] = '会员卡不存在';
            $data['data'] = [];
            return Response::json($data);
        }

        $res = $this->member_card->get_data_by_id($id, $this->merchant_id);
        if (empty($res)) {
            $data['errcode'] = 20004;
            $data['errmsg'] = '会员卡不存在';
            $data['data'] = [];
            return Response::json($data);
        }
        $data['errcode'] = 0;
        $data['data'] = $res;
        return Response::json($data);
    }

    /*
     * 数据统计
     */

    public function statisticsInfo() {
        $wheres[] = array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '=');
        $num_member = $this->member->get_data_count($wheres); //会员数
        $wheres[] = array('column' => 'purchased_count', 'value' => 0, 'operator' => '>');
        $num_trade_member = $this->member->get_data_count($wheres); //成交会员数
        $wheres = array();
        $wheres[] = array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '=');
        $wheres[] = array('column' => 'created_time', 'value' => date("Y-m-d"), 'operator' => '<');
        $wheres[] = array('column' => 'created_time', 'value' => date("Y-m-d", strtotime("-1 day")), 'operator' => '>=');
        $num_today = $this->member->get_data_count($wheres); //昨日新增会员数
        $wheres[] = array('column' => 'purchased_count', 'value' => 1, 'operator' => '=');
        $num_yestoday = $this->member->get_data_count($wheres); //昨日新增成交会员数
        $data['errcode'] = 0;
        $data['data'] = array(
            'count' => array("cturnover" => $num_trade_member, "cmember" => $num_member),
            "yesterday" => array("cturnover" => $num_yestoday, "cmember" => $num_today)
        );
        return Response :: json($data);
    }

    /*
     * 增长趋势
     */

    public function addTrends() {
        $result = $this->member_daily_service->getMemberStatDays($this->merchant_id);
        $data['errcode'] = 0;
        $data['data'] = $result;
        return Response :: json($data);
    }

    /*
     * 等级分布
     */

    public function levelInfo() {
        $data['errcode'] = 0;
        $where = array(
            array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '='),
            array('column' => 'is_delete', 'value' => 1, 'operator' => '=')
        );
        $data['data'] = array();
        if ($this->version == 1) {
            $result=array();
            $where = array(
                array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '=')               
            );
            $num = $this->member->get_data_count($where);  //会员卡会员数
            $data['data'][] = array("level_name" =>'默认等级', "cmember" => $num);
        } else {
            $result = $this->member_card->get_data_list($where, 'id,card_name', '', 0, 1000);
        }        
        foreach ($result as $key => $value) {
            $where = array(
                array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '='),
                array('column' => 'member_card_id', 'value' => $value['id'], 'operator' => '=')
            );
            $num = $this->member->get_data_count($where);  //会员卡会员数
            $data['data'][] = array("level_name" => $value['card_name'], "cmember" => $num);
        }
        return Response :: json($data);
    }

    /*
     * 设置等级
     */

    public function setLevel(VipcardService $vipcardService) {
        $id = isset($this->params['id']) ? $this->params['id'] : 0;
        $member_card_id = isset($this->params['member_card_id']) ? intval($this->params['member_card_id']) : 0;
        $member_card_overtime = isset($this->params['member_card_overtime']) ? $this->params['member_card_overtime'] : '';

        $param = [
            'merchant_id' => $this->merchant_id,
            'member_ids' => $id,
            'member_card_id' => $member_card_id,
            'member_card_overtime' => $member_card_overtime
        ];

        return $vipcardService->allocation($param);
        //验证会员卡是否存在
        /* if ($member_card_id == 0) {
          $data['errcode'] = 20018;
          $data['errmsg'] = '会员等级不存在';
          return Response :: json($data);
          }

          if (!is_array($id)) {
          $id = intval($id);
          if ($id == 0) {
          $data['errcode'] = 20013;
          $data['errmsg'] = '会员id不存在';
          return Response :: json($data);
          }
          $wheres[] = array('column' => 'id', 'value' => $id, 'operator' => '=');
          } else {
          $wheres[] = array('column' => 'id', 'value' => $id, 'operator' => 'in');
          }
          $card= $this->member_card->get_data_by_id($member_card_id, $this->merchant_id);
          if(!empty($card)){
          if(isset($card['card_type'])&&$card['card_type']!=2){
          $data['errcode'] = 20017;
          $data['errmsg'] = '会员等级必须是手动升级类型的';
          return Response :: json($data);
          }
          }
          else{
          $data['errcode'] = 20018;
          $data['errmsg'] = '会员等级不存在';
          return Response :: json($data);
          }
          if (empty($member_card_overtime)) {
          $data['errcode'] = 20016;
          $data['errmsg'] = '请输入过期时间';
          return Response :: json($data);
          }

          $result = $this->member->update_data_by_where($id, $this->merchant_id, $wheres, array('member_card_id' => $member_card_id,'member_card_overtime'=>$member_card_overtime));
          if ($result) {
          $data['errcode'] = 0;
          $data['errmsg'] = '操作成功';
          } else {
          $data['errcode'] = 1;
          $data['errmsg'] = '操作失败';
          }
          return Response :: json($data); */
    }

    /*
     * 获取会员列表
     */

    public function memberList() {
        $wheres[] = array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '=');
        $public_open_id = isset($this->params['account']) ? trim($this->params['account']) : '';
        $id=0;
        if (!empty($public_open_id)) {//会员账号等于会员开好常量+会员id
            $id = intval($public_open_id) - MEMBER_CONST;
        }
        $mobile = isset($this->params['mobile']) ? trim($this->params['mobile']) : '';
        $name = isset($this->params['name']) ? trim($this->params['name']) : '';
        $member_card_id = isset($this->params['member_card_id']) ? intval($this->params['member_card_id']) : 0;
        if ($id > 0) {
            $wheres[] = array('column' => 'id', 'value' => $id, 'operator' => '=');
        }
        else if(!empty ($public_open_id)){
            $wheres[] = array('column' => 'id', 'value' => 0, 'operator' => '=');
        }
        if ($member_card_id > 0) {
            $wheres[] = array('column' => 'member_card_id', 'value' => $member_card_id, 'operator' => '=');
        }
        if (!empty($mobile)) {
            $wheres[] = array('column' => 'mobile', 'value' => $mobile, 'operator' => '=');
        }
        if (!empty($name)) {
            $wheres[] = array('column' => 'name', 'value' => $name, 'operator' => '=');
        }
        $offset = isset($this->params['page']) ? intval($this->params['page']) : 1;
        $limit = isset($this->params['pagesize']) ? intval($this->params['pagesize']) : 10;
        $offset = ($offset - 1) * $limit;
        $fields = 'id,merchant_id,public_open_id,member_card_id,member_card_overtime,total_amount,purchased_count,credit,total_credit,
            latest_buy_time,latest_access_time,name,avatar,gender,mobile,is_verify_mobile,country,province,city,created_time,updated_time';
        $result = $this->member->get_data_list($wheres, $fields, $offset, $limit);
        $data['errcode'] = 0;
        $data['_count'] = $this->member->get_data_count($wheres);
        if ($data["_count"] > 0) {
            foreach ($result as $key => $value) {
                $result[$key]['account'] = MEMBER_CONST + $value['id']; //会员账号等于会员开好常量+会员id
            }
        }
        $data['data'] = $result;


        $where = array(
            array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '='),
            array('column' => 'is_delete', 'value' => 1, 'operator' => '=')
        );
        if ($this->version == 1) {
            $result = array();
        } else {
            $result = $this->member_card->get_data_list($where, 'id,card_name,card_type', '', 0, 1000);
        }
        foreach ($data['data'] as $key => $value) {
            $up_type = '自动升级';
            $card_id = $value['member_card_id'];
            $data['data'][$key]['member_card_name'] = '';           
            $data['data'][$key]['member_card_name']='默认等级';
            $data['data'][$key]['card_type'] = 1;           
            foreach ($result as $ke => $val) {
                if ($val['id'] == $card_id) {
                    $data['data'][$key]['member_card_name'] = $val['card_name'];
                    $data['data'][$key]['card_type'] = $val['card_type'];
                    break;
                }
            }
            if($value['latest_buy_time']=='0000-00-00 00:00:00'){
                $data['data'][$key]['latest_buy_time'] = '--';
            }
            //是不是推客(用于推客列表 2018-03-01)
            $is_sure =  $this->distrib->get_data_by_memberid($value['id'] , $this->merchant_id);
            $data['data'][$key]['is_distrib'] = count($is_sure)>0 ? 1 : 0;
        }
        $data['member_card'] = $result;
        $data['version'] = $this->version;
        return Response :: json($data);
    }

}
