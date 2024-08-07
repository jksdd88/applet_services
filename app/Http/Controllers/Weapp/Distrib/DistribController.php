<?php

namespace App\Http\Controllers\Weapp\Distrib;

use App\Models\DistribOrder;
use App\Models\DistribOrderDetail;
use App\Models\DistribOrderGoods;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use App\Facades\Member;
use App\Models\WeixinInfo;
use App\Models\Goods;
use App\Models\DistribPartner;
use App\Models\DistribSetting;
use App\Models\DistribCheckLog;
use App\Models\Member as MemberModel;
use App\Models\DistribBuyerRelation;
use App\Models\DistribCodeSetting;
use App\Models\DistribMemberFirstRecord;
use App\Models\WeixinTemplate;
use App\Models\DistribActivity;
use App\Models\DistribActivityRelation;
use Carbon\Carbon;
use App\Services\WeixinService;
use App\Services\DistribService;
use App\Utils\Encrypt;
use App\Jobs\WeixinMsgJob;
use GuzzleHttp\Client;
use App\Utils\CacheKey;
use Qiniu\Auth;
use Cache;
use DB;
use App\Utils\CommonApi;

class DistribController extends Controller
{
    private $data = [];

    public function __construct()
    {
        if (!app()->isLocal()) {
            $this->member_id = Member::id();
            $this->merchant_id = Member::merchant_id();
            $this->weapp_id = Member::weapp_id();
        } else {
            $this->member_id = 3;
            $this->merchant_id = 3;
            $this->weapp_id = 3;

        }
    }

    /**
     * 绑定会员与推客佣金关系
     *
     * @return \Illuminate\Http\Response
     */
    public function bindBuyerRelation(Request $request)
    {
        $member_id         = Member::id();
        $merchant_id       = Member::merchant_id();
        $distrib_member_id = $request->input('distrib_member_id', ''); //推客会员ID
        $share_member_id   = $request->input('share_member_id', ''); //分享者会员ID

        if (!$merchant_id) {
            return ['errcode' => 99001, 'errmsg' => '商户ID不存在'];
        }

        if (!$member_id) {
            return ['errcode' => 99001, 'errmsg' => '会员ID不存在'];
        }

        $distrib_member_id = !empty($distrib_member_id) ? encrypt($distrib_member_id, 'D') : 0;
        $share_member_id   = !empty($share_member_id) ? encrypt($share_member_id, 'D') : 0;

        //如果是总部审核的情况，推客正属于审核状态时，分享出去的链接distrib_member_id可能为空或者为上级推客ID，当用户点击我分享的链接进入时（这时候我已经被审核通过），再用distrib_member_id来绑定关系显然不准确了，通过分享者IDshare_member_id，先检查分享者是否已经成为推客，如果已成为推客(distrib_member_id = share_member_id)，如果分享者不是推客时distrib_member_id值不变
        $distrib_member_id = (new DistribService)->distribBuyerRelation($member_id, $merchant_id, $distrib_member_id, $share_member_id);
        //绑定成功返回绑定成功的推客ID
        if ($distrib_member_id) {
            $encrypt_distrib_member_id = encrypt($distrib_member_id, 'E');
            return ['errcode' => 0, 'errmsg' => '绑定成功', 'data' => ['distrib_member_id' => $encrypt_distrib_member_id]];
        }
    }

    /**
     * 获取当前会员的推客状态
     *
     * @return \Illuminate\Http\Response
     */
    public function getInfo(Request $request)
    {
        $member_id   = Member::id();
        $merchant_id = Member::merchant_id();
        $weapp_id    = Member::weapp_id();
        $appid       = Member::appid();
        $goods_id    = $request->input('goods_id', 0);

        if (!$merchant_id) {
            return ['errcode' => 99001, 'errmsg' => '商户ID不存在'];
        }

        if (!$member_id) {
            return ['errcode' => 99001, 'errmsg' => '会员ID不存在'];
        }

        if (!$weapp_id) {
            return ['errcode' => 99001, 'errmsg' => '小程序ID不存在'];
        }

        $setting = DistribSetting::get_data_by_merchant_id($merchant_id);
        if ($setting && $setting['status'] == 1) {
            $data['spread_name'] = !empty($setting['spread_name']) ? $setting['spread_name'] : '';

            $member = MemberModel::get_data_by_id($member_id, $merchant_id);
            $data['member_name']   = $member['name'];
            $data['member_avatar'] = $member['avatar'];

            $wxinfo = WeixinInfo::get_one('id', $weapp_id, 1);
            $data['nick_name'] = $wxinfo['nick_name'];

            //短加密串：生成推客二维码有长度限制，所以才使用短加密串
            $encrypt_member_id = encrypt($member_id, 'E');

            $data['is_partner'] = 0;
            $partner = DistribPartner::get_data_by_memberid($member_id, $merchant_id);
            if ($partner) {
                $data['is_partner']        = 1;
                $data['encrypt_member_id'] = $encrypt_member_id;
                $data['partner_status']    = $partner['status'];

                if (in_array($partner['status'], [1, 2])) {
                    //获取推客二维码
                    /* 二维码兼容7.12之前的老版本 */
                    $data['qrcode'] = '';
                    $version_id = WeixinTemplate::where('merchant_id', $merchant_id)->where('appid', $appid)->where('status', 1)->where('release', 1)->orderBy('id', 'desc')->value('version_id');
                    if($version_id >= 1531392695){
                        $qrcode_param = 'pages/decorate/decorate?distrib_member_id='.$encrypt_member_id.'&hyaline=1&v=713';
                        $create_qrcode = (new WeixinService())->qrcodeAll($appid, 1, $qrcode_param, null, ['hyaline' => true]);
                        if (isset($create_qrcode['errcode']) && $create_qrcode['errcode'] == 0) {
                            $data['qrcode'] = $create_qrcode['url'];
                        }
                    }else{
                        $qrcode_param = 'pages/decorate/decorate?distrib_member_id='.$encrypt_member_id.'&bg=white&v=713';
                        $create_qrcode = (new WeixinService())->qrcode($appid, $qrcode_param);
                        if (isset($create_qrcode['errcode']) && $create_qrcode['errcode'] == 0) {
                            $data['qrcode'] = $create_qrcode['url'];
                        }
                    }
                }
            }

            if ($goods_id) {
                $goods = Goods::get_data_by_id($goods_id, $merchant_id);
                if ($goods) {
                    $goods_info = [
                        'id'    => $goods['id'],
                        'title' => $goods['title'],
                        'price' => $goods['price'],
                        'img'   => $goods['img'],
                        'csale' => $goods['csale'] + $goods['base_csale']
                    ];
                    $data['goods_info'] = $goods_info;
                }
            }

            $data['qrcode_setting'] = [
                'template'       => 1,
                'background_img' => '2018/7/3/7Djxck7EDM2I99DKyUgyBlSOmyy6.png',
                'text1'          => '我是'.$member['name'],
                'text2'          => '我为'.$wxinfo['nick_name'].'代言'
            ];
            $qrcode_setting = DistribCodeSetting::get_data_by_merchantid($merchant_id);
            if($qrcode_setting){
                $data['qrcode_setting']['template'] = $qrcode_setting['template'];
                $data['qrcode_setting']['background_img'] = $qrcode_setting['background_img'];
                $data['qrcode_setting']['text1'] = str_replace("{{guider_name}}", $member['name'], $qrcode_setting['text1']);
                $data['qrcode_setting']['text2'] = str_replace("{{applet_name}}", $wxinfo['nick_name'], $qrcode_setting['text2']);
            }

            return ['errcode' => 0, 'data' => $data];
        } else {
            return ['errcode' => 200002, 'errmsg' => '暂未开启推广功能'];
        }
    }

    /**
     * 注册推客
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function register(Request $request)
    {
        $member_id         = Member::id();
        $merchant_id       = Member::merchant_id();
        $weapp_id          = Member::weapp_id();
        $appid             = Member::appid();
        $name              = $request->input('name', '');
        $mobile            = $request->input('mobile', '');
        $distrib_member_id = $request->input('distrib_member_id', ''); //推客会员ID
        $share_member_id   = $request->input('share_member_id', ''); //分享者会员ID
        $collect_fields    = $request->input('collect_fields', ' ');//信息采集字段,本期占坑

        if (empty($name) || mb_strlen($name) > 30) {
            return ['errcode' => 99001, 'errmsg' => '姓名长度为1-30'];
        }

        if (empty($mobile) || !preg_match('/^[1-9]\d{10}$/', $mobile)) {
            return ['errcode' => 99001, 'errmsg' => '手机号格式不正确'];
        }

        $distrib_member_id = !empty($distrib_member_id) ? encrypt($distrib_member_id, 'D') : 0;
        $share_member_id   = !empty($share_member_id) ? encrypt($share_member_id, 'D') : 0;

        $setting = DistribSetting::get_data_by_merchant_id($merchant_id);
        if ($setting && $setting['status'] == 1) {
            if($setting['hierarchy_mode'] == 1){ 
                //推客绑定关系为永久时，取会员第一次进入的推客ID
                $first_record = DistribMemberFirstRecord::get_data_by_memberid($member_id, $merchant_id);
                $distrib_member_id = $first_record ? $first_record['distrib_member_id'] : 0;
            }else{
                //如果是总部审核的情况，推客正属于审核状态时，分享出去的链接distrib_member_id可能为空或者为上级推客ID，当用户点击我分享的链接进入时（这时候我已经被审核通过），再用distrib_member_id来绑定关系显然不准确了，通过分享者IDshare_member_id，先检查分享者是否已经成为推客，如果已成为推客(distrib_member_id = share_member_id)，如果分享者不是推客时distrib_member_id值不变
                if ($share_member_id) {
                    //查询分享者是否是推客
                    $share_is_partner = DistribPartner::get_data_by_memberid($share_member_id, $merchant_id);
                    //推客状态0:未审核, 1:正常, 2:禁用, 3:审核失败
                    if ($share_is_partner && in_array($share_is_partner['status'], [1, 2])) {
                        $distrib_member_id = $share_member_id;
                    }
                }

                //父级推客id
                if ($distrib_member_id) {
                    $is_partner = DistribPartner::get_data_by_memberid($distrib_member_id, $merchant_id);
                    //推客状态0:未审核, 1:正常, 2:禁用, 3:审核失败
                    if (!$is_partner || !in_array($is_partner['status'], [1, 2])) {
                        $distrib_member_id = 0;
                    }
                    //自已的推客被删除后，下次通过自已分享的链接再注册时，会造成上级推客ID与自已相同，那就直接将上级设为0
                    if ($distrib_member_id == $member_id) {
                        $distrib_member_id = 0;
                    }
                }
            }

            if ($setting['check_type'] === 0) { //0: 系统自动审核, 1:总部审核
                $status = 1; //审核成功
            } else {
                $status = 0; //未审核
            }

            $data = [
                'parent_member_id' => $distrib_member_id,
                'merchant_id'      => $merchant_id,
                'member_id'        => $member_id,
                'status'           => $status,
                'name'             => $name,
                'mobile'           => $mobile,
                'wxinfo_id'        => $weapp_id,
                'appid'            => $appid,
                'collect_fields'   => $collect_fields
            ];
            //若是自动审核 则将审核时间同步
            $data['check_time'] = $status === 1 ? Carbon::now() : '0000-00-00 00:00:00';

            //查询推客信息是否已存在
            $is_exist = DistribPartner::get_data_by_memberid($member_id, $merchant_id);
            if ($is_exist) {
                $res = DistribPartner::update_data($member_id, $merchant_id, $data);
            } else {
                $res = DistribPartner::insert_data($data);//加入推客表
            }

            //加密member_id
            $encrypt_member_id = encrypt($member_id, 'E');

            if (!isset($res) || !$res) {
                return ['errcode' => 99001, 'errmsg' => '操作失败,请稍后再试'];
            } else {
                if ($status === 1) {
                    //获取推客二维码
                    $qrcode_param = 'pages/decorate/decorate?distrib_member_id='.$encrypt_member_id.'&hyaline=1';
                    $create_qrcode = (new WeixinService())->qrcodeAll($appid, 1, $qrcode_param, null, ['hyaline' => true]);
                    if (isset($create_qrcode['errcode']) && $create_qrcode['errcode'] == 0) {
                        $qrcode = $create_qrcode['url'];
                    } else {
                        $qrcode = '';
                    }
                }
            }

            //系统自动审核
            if ($setting['check_type'] == 0) {
                //插入推客关系
                $relation = DistribBuyerRelation::get_data_by_memberid($member_id, $merchant_id);
                if (!$relation) {
                    $relation_data = [
                        'merchant_id'       => $merchant_id,
                        'member_id'         => $member_id,
                        'distrib_member_id' => $member_id   //自已已经是推客了，关系就是与自已绑定
                    ];

                    DistribBuyerRelation::insert_data($relation_data);
                } elseif ($relation && $setting['buyer_period'] == 0) {
                    DistribBuyerRelation::update_data($member_id, $merchant_id, ['distrib_member_id' => $member_id]);
                }
                //插入验证表
                $check_data = array(
                    'member_id'    => $member_id,
                    'merchant_id'  => $merchant_id,
                    'mobile'       => $mobile,
                    'apply_time'   => Carbon::now(),
                    'check_time'   => Carbon::now(),
                    'check_uid'    => 0,//审核人id
                    'check_type'   => 1,//审核类型 0自动 1总部审核
                    'check_result' => 1,//审核状态 1成功 2禁用 3未通过审核
                );
                DistribCheckLog::insert_data($check_data);

                if ($distrib_member_id) {
                    //父级推客团队人数+1
                    DistribPartner::increment_data($distrib_member_id, $merchant_id, 'team_size', 1);
                    //通知上级有新成员加入
                    $this->dispatch(new WeixinMsgJob([
                        'type'        => 'distrib_partner_join',
                        'member_id'   => $member_id,
                        'merchant_id' => $merchant_id
                    ]));
                }
            } else {
                //公众号通知商家，有新推客申请加入
                $this->dispatch(new WeixinMsgJob([
                    'type'        => 'distrib_apply',
                    'member_id'   => $member_id,
                    'merchant_id' => $merchant_id
                ]));
            }

            $response = [
                'status'            => $status,
                'encrypt_member_id' => $encrypt_member_id,
                'qrcode'            => isset($qrcode) ? $qrcode : ''
            ];
            return ['errcode' => 0, 'errmsg' => '操作成功', 'data' => $response];
        } else {
            return ['errcode' => 200002, 'errmsg' => '暂未开启推广功能'];
        }
    }

    /**
     * 获取一条推广员信息
     *
     * @return \Illuminate\Http\Response
     */
    public function getPartner(Request $request, $id)
    {
        $merchant_id = Member::merchant_id();

        $distrib_partner = DistribPartner::select('member_id', 'name', 'mobile')->where('id', $id)->first();
        if (!$distrib_partner) {
            return ['errcode' => 200001, 'errmsg' => '推广员不存在'];
        }

        $data['name'] = $distrib_partner->name;
        $data['mobile'] = $distrib_partner->mobile;

        $member = MemberModel::get_data_by_id($distrib_partner->member_id, $merchant_id);
        if (!$member) {
            return ['errcode' => 200001, 'errmsg' => '推广员不存在'];
        }

        $data = [
            'id' => $id,
            'name' => $distrib_partner->name,
            'mobile' => $distrib_partner->mobile,
            'nickname' => $member->name,
            'avatar' => $member->avatar
        ];

        return ['errcode' => 0, 'data' => $data];
    }

    /**
     * 修改推广员信息
     *
     * @return \Illuminate\Http\Response
     */
    public function putPartner(Request $request, $id)
    {
        $merchant_id = Member::merchant_id();
        $name = $request->input('name', '');
        $mobile = $request->input('mobile', '');

        $distrib_partner = DistribPartner::select('member_id')->where('id', $id)->first();
        if (!$distrib_partner) {
            return ['errcode' => 00, 'errmsg' => '推广员不存在'];
        }

        if (!empty($name)) {
            if (mb_strlen($name) > 30) {
                return ['errcode' => 99001, 'errmsg' => '姓名长度为1-30'];
            }

            DistribPartner::update_data($distrib_partner->member_id, $merchant_id, ['name' => $name]);
            return ['errcode' => 0, 'errmsg' => '修改成功'];
        }

        if (!empty($mobile)) {
            if (!preg_match('/^[1-9]\d{10}$/', $mobile)) {
                return ['errcode' => 99001, 'errmsg' => '手机号格式不正确'];
            }

            DistribPartner::update_data($distrib_partner->member_id, $merchant_id, ['mobile' => $mobile]);
            return ['errcode' => 0, 'errmsg' => '修改成功'];
        }
    }

    /**
     * 推广中心
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function getCenterInfo()
    {
        $distrib_partner = DistribPartner::get_data_by_memberid($this->member_id, $this->merchant_id);
        $this->data = $distrib_partner;
        //推客下级
        $team_size = DistribPartner::where('merchant_id', $this->merchant_id)->where('parent_member_id', $this->member_id)->whereIn('status', [1, 2])->count();
        $this->data['team_size'] = $team_size;
        //佣金下级
        $commission_num = DistribBuyerRelation::where('merchant_id', $this->merchant_id)->where('distrib_member_id', $this->member_id)->count();
        $this->data['commission_num'] = $commission_num;
        $this->data['order_count'] = DistribOrderDetail::where('merchant_id', $this->merchant_id)
            ->where('member_id', $this->member_id)
            ->count();
            
        $set = DistribSetting::get_data_by_merchant_id($this->merchant_id);
        $this->data['spread_name'] = isset($set['spread_name']) ? $set['spread_name'] : '-';

        //推广活动未读
        $results = DB::select('SELECT COUNT(1) AS unread FROM distrib_activity AS a LEFT JOIN (SELECT * FROM distrib_activity_relation WHERE merchant_id = ? AND distrib_member_id = ?) temp ON a.id = temp.distrib_activity_id WHERE a.merchant_id = ? AND a.send_time >= ? AND a.send_time <= ? AND a.is_delete=1 AND temp.distrib_member_id IS NULL', [$this->merchant_id, $this->member_id, $this->merchant_id, $distrib_partner['check_time'], date('Y-m-d H:i:s')]);
        $this->data['distrib_activity_unread'] = isset($results[0]) ? $results[0]->unread : 0;
        return ['errcode' => 0, 'errmsg' => 'OK', 'data' => $this->data];
    }

    /**
     * 推广订单列表
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function getOrder()
    {
        $this->data = ['_count' => 0, 'lists' => []];
        $page = request('page', 1);
        $pagesize = request('pagesize', 10);
        $status = request('status', null);
        $query = DistribOrderDetail::where('merchant_id', $this->merchant_id)
            ->where('member_id', $this->member_id);
        if (!is_null($status)) {
            $query->where('status', $status);
        }
        $this->data['_count'] = $query->count();
        $order_res = $query->forPage($page, $pagesize)->orderBy('id', 'desc')->get();
        if (!$order_res->isEmpty()) {
            foreach ($order_res as &$order) {
                $order['name'] = '-';
                $order['total_comission'] = $order['comission'];//未退款前的佣金
                $order['comission'] = $order['comission'] - $order['refund_comission'];//剩余佣金
                $dis_order_res = DistribOrder::get_data_by_orderid($order['order_id'], $order['merchant_id']);
                if (empty($dis_order_res)) continue;
                $member_res = MemberModel::get_data_by_id($dis_order_res['order_member_id'], $dis_order_res['merchant_id']);
                if (empty($member_res)) continue;
                $order['name'] = $member_res['name'];
            }
            $this->data['lists'] = $order_res;
        }
        $set = DistribSetting::get_data_by_merchant_id($this->merchant_id);
        $this->data['spread_name'] = isset($set['spread_name']) ? $set['spread_name'] : '-';
        return ['errcode' => 0, 'errmsg' => 'OK', 'data' => $this->data];
    }

    /**
     * 推广订单商品详情
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function getOrderDetail($order_id)
    {
//        distrib_order_goods表refund_info（退款佣金信息）字段，数据库数据存储格式
//        [{
//                "refund_comission_1": 0.36,
//            "refund_comission_2": 1.8,
//            "refund_comission_3": 3.6
//        }, {
//                "refund_comission_1": 0,
//            "refund_comission_2": 0,
//            "refund_comission_3": 0
//        }, ]
        $DistribOrderDetail = DistribOrderDetail::get_data_by_orderid($order_id, $this->member_id, $this->merchant_id);
        if (empty($DistribOrderDetail) || !isset($DistribOrderDetail['level'])) {
            return ['errcode' => 1, 'errmsg' => '查询订单佣金明细失败，订单不存在、Level错误。'];
        }

        $DistribOrderGoods_res = DistribOrderGoods::get_list_by_orderid($order_id, $this->merchant_id);
        if (empty($DistribOrderGoods_res)) return ['errcode' => 1, 'errmsg' => '查询推客订单商品分佣明细失败，订单不存在。'];
        $re_data = [];
        foreach ($DistribOrderGoods_res as $key => $order_goods) {
            $re_data[$key]['goods_name'] = $order_goods['goods_name'];
            $re_data[$key]['goods_quantity'] = $order_goods['goods_quantity'];
            $re_data[$key]['goods_img'] = $order_goods['goods_img'];
            $re_data[$key]['goods_amount'] = $order_goods['goods_amount'];

            $comission_info_arr = json_decode($order_goods['comission_info'], true);
            if (!isset($comission_info_arr) || !isset($comission_info_arr['comission_' . $DistribOrderDetail['level']]) || !isset($comission_info_arr['comission_' . $DistribOrderDetail['level']]['comission'])) {
                $re_data[$key]['comission'] = '-';
            } else {
                $re_data[$key]['comission'] = $comission_info_arr['comission_' . $DistribOrderDetail['level']]['comission'];//分佣
            }

            if (empty($order_goods['refund_info'])) continue;
            $tem = json_decode($order_goods['refund_info'], true);
            foreach ($tem as $k => $v) {
                if (isset($v['refund_comission_' . $DistribOrderDetail['level']])) {
                    $refund_comission['refund_comission'] = $v['refund_comission_' . $DistribOrderDetail['level']];//退佣
                    $tem[$k] = $refund_comission;
                }
            }
            unset($order_goods['comission_info']);
            $re_data[$key]['refund_info'] = $tem;
        }
        $this->data['lists'] = $re_data;
        return ['errcode' => 0, 'errmsg' => 'OK', 'data' => $this->data];
    }

    /**
     * 我的下级
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function getSuperior(Request $request)
    {
        $member_id   = Member::id();
        $merchant_id = Member::merchant_id();
        $page        = $request->input('page', 1);
        $pagesize    = $request->input('pagesize', 10);
        $offset      = ($page - 1) * $pagesize;

        $query = DistribPartner::query();
        $query->select('member_id', 'created_time');
        $query->where('merchant_id', $merchant_id);
        $query->where('parent_member_id', $member_id);
        $query->whereIn('status', [1, 2]);
        $count = $query->count();
        $lists = $query->orderBy('id', 'desc')->skip($offset)->take($pagesize)->get();

        foreach ($lists as &$row) {
            $member = MemberModel::get_data_by_id($row->member_id, $merchant_id);
            $row->name   = $member ? $member->name : '-';
            $row->avatar = $member ? $member->avatar : '';
            $row->created_time = date('Y.m.d', strtotime($row->created_time));
        }

        return ['errcode' => 0, 'data' => ['_count' => $count, 'lists' => $lists]];
    }

    /**
     * 我的下级
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function getCommission(Request $request)
    {
        $member_id   = Member::id();
        $merchant_id = Member::merchant_id();
        $page        = $request->input('page', 1);
        $pagesize    = $request->input('pagesize', 10);
        $offset      = ($page - 1) * $pagesize;

        $query = DistribBuyerRelation::query();
        $query->select('member_id', 'created_time');
        $query->where('merchant_id', $merchant_id);
        $query->where('distrib_member_id', $member_id);
        $count = $query->count();
        $lists = $query->orderBy('id', 'desc')->skip($offset)->take($pagesize)->get();

        foreach ($lists as &$row) {
            $member = MemberModel::get_data_by_id($row->member_id, $merchant_id);
            $row->name   = $member ? $member->name : '-';
            $row->avatar = $member ? $member->avatar : '';
            $row->created_time = date('Y.m.d', strtotime($row->created_time));
        }

        return ['errcode' => 0, 'data' => ['_count' => $count, 'lists' => $lists]];
    }

    /**
     * 素才列表
     * @return array
     * @author: guoqikai@dodoca.com
     */
    public function activityList(Request $request)
    {
        $member_id   = Member::id();
        $merchant_id = Member::merchant_id();
        $page        = $request->input('page', 1);
        $pagesize    = $request->input('pagesize', 10);
        $offset      = ($page - 1) * $pagesize;

        $distrib_partner = DistribPartner::get_data_by_memberid($member_id, $merchant_id);

        $query = DistribActivity::query();
        $query->select('id', 'title', 'created_time', 'send_type', 'send_time');
        $query->where('merchant_id', $merchant_id);
        $query->where('send_time', '<=', date('Y-m-d H:i:s'));
        $query->where('is_delete', 1);

        $count = $query->count();
        $data = $query->orderBy('send_time', 'desc')->skip($offset)->take($pagesize)->get();

        foreach ($data as &$row) {
            $row->unread = 0;
            if($row->send_time >= $distrib_partner->check_time){
                $unread = DistribActivityRelation::where('merchant_id', $merchant_id)->where('distrib_member_id', $member_id)->where('distrib_activity_id', $row->id)->first();
                if(!$unread){
                    $row->unread = 1;
                }
            }
            
            if($row->send_type == 2){
                $row->created_time = $row->send_time;
            }
        }

        return ['errcode' => 0, 'count' => $count, 'data' => $data];
    }

    /**
     * 素才详情
     * @return array
     * @author: guoqikai@dodoca.com
     */
    public function activityDetails(Request $request, $id)
    {
        $member_id   = Member::id();
        $merchant_id = Member::merchant_id();
        $weapp_id    = Member::weapp_id();

        $data = DistribActivity::get_data_by_id($id, $merchant_id);
        if($data){
            $data = $data->toArray();
            $is_exist = DistribActivityRelation::where('merchant_id', $merchant_id)->where('distrib_member_id', $member_id)->where('distrib_activity_id', $id)->first();
            if(!$is_exist){
                DistribActivityRelation::insert([
                    'merchant_id'         => $merchant_id,
                    'distrib_member_id'   => $member_id,
                    'distrib_activity_id' => $id
                ]);
            }
            if($data['send_type'] == 2){
                $data['created_time'] = $data['send_time'];
            }

            $wxinfo = WeixinInfo::get_one('id', $weapp_id, 1);
            $data['qrcode'] = $wxinfo['qrcode'];

            $member = MemberModel::get_data_by_id($member_id, $merchant_id);
            $data['text1'] = str_replace('{{guider_name}}', $member['name'], $data['text1']);
            $data['text2'] = str_replace('{{applet_name}}', $wxinfo['nick_name'], $data['text2']);
        }

        return ['errcode' => 0, 'data' => $data];
    }

    /**
     * 下载海报
     * @return array
     * @author: guoqikai@dodoca.com
     */
    public function downPoster(Request $request, $id)
    {
        $member_id   = Member::id();
        $merchant_id = Member::merchant_id();
        $weapp_id    = Member::weapp_id();

        $cacheKey = CacheKey::distrib_poster_card_key($id, $merchant_id, $weapp_id, $member_id);
        $imgData  = Cache::tags('distrib_activity_merchant_'.$merchant_id)->get($cacheKey);

        if(!$imgData){
            $data = DistribActivity::get_data_by_id($id, $merchant_id);
            if($data){
                $width  = 900;
                $height = 1100;

                $wxinfo = WeixinInfo::get_one('id', $weapp_id, 1);
                $appid  = $wxinfo['appid'];
                $member = MemberModel::get_data_by_id($member_id, $merchant_id);

                $canvas = new \Imagick();
                $canvas->newImage($width, $height, new \ImagickPixel('white'));
                $canvas->setImageFormat('png');

                $bg_img = env('QINIU_STATIC_DOMAIN').'/'.ltrim($data->image, '/');;
                $back = new \Imagick();
                $back->readImageBlob(file_get_contents($bg_img));
                $back->thumbnailImage($width, 900);
                $canvas->compositeImage($back, \Imagick::COMPOSITE_OVER, 0, 0);

                //昵称
                $name = str_cut(str_replace('{{guider_name}}', $member['name'], $data->text1), 36);
                $name_draw = new \ImagickDraw();
                $name_draw->setFont(realpath('font/Microsoft-Yahei.ttf'));
                $name_draw->setFontSize(40);
                $name_draw->setFillColor('black');
                $name_font_metrics = $canvas->queryFontMetrics($name_draw, $name);
                $canvas->annotateImage($name_draw, 40, 990, 0, $name);

                //描述
                $desc = str_cut(str_replace('{{applet_name}}', $wxinfo['nick_name'], $data->text2), 54);
                $desc_draw = new \ImagickDraw();
                $desc_draw->setFont(realpath('font/Microsoft-Yahei.ttf'));
                $desc_draw->setFontSize(28);
                $desc_draw->setFillColor('black');
                $canvas->annotateImage($desc_draw, 40, 1040, 0, $desc);

                //二维码
                //二维码地址
                $qrcode_url = env('QINIU_STATIC_DOMAIN').'/'.ltrim($wxinfo['qrcode'], '/'); //默认小程序码
                if($member_id){
                    $encrypt_member_id = encrypt($member_id, 'E');
                    $qrcode_type = env('APP_ENV') == 'production' ? 1 : 4;
                    $create_qrcode = (new WeixinService())->qrcodeAll($appid, $qrcode_type, 'pages/decorate/decorate?distrib_member_id='.$encrypt_member_id);
                    if (isset($create_qrcode['errcode']) && $create_qrcode['errcode'] == 0) {
                        $qrcode_url = env('QINIU_STATIC_DOMAIN').'/'.ltrim($create_qrcode['url'], '/');
                    }
                }
                
                $qrcode = new \Imagick();
                $qrcode->readImageBlob(file_get_contents($qrcode_url));
                $qrcode->thumbnailImage(160, 160, true);
                $canvas->compositeImage($qrcode, \Imagick::COMPOSITE_OVER, $width-160-40, ($height-200)+((200-160)/2));

                $content = $canvas->getImageBlob();
                $canvas->clear(); //释放资源
                $content = base64_encode($content);
                $imgData = $this->upload($content);
                Cache::tags('distrib_activity_merchant_'.$merchant_id)->forever($cacheKey, $imgData);
            }
        }

        if(!empty($imgData)){
            return ['errcode' => 0, 'data' => $imgData];
        }else{
            return ['errcode' => -1, 'errmsg' => '下载失败'];
        }
    }

    /*
     * 下载装修码
     * @return array
     * @author: guoqikai@dodoca.com
     */
    public function downQrcode(Request $request)
    {
        $member_id   = Member::id();
        $merchant_id = Member::merchant_id();
        $weapp_id    = Member::weapp_id();

        $template = 1;
        $qrcode_setting = DistribCodeSetting::get_data_by_merchantid($merchant_id);
        if($qrcode_setting){
            $template = $qrcode_setting['template'];
        }

        $cacheKey = CacheKey::distrib_qrcode_card_key($merchant_id, $weapp_id, $member_id, $template);
        $imgData  = Cache::tags('distrib_merchant_'.$merchant_id)->get($cacheKey);

        if(!$imgData){
            $wxinfo = WeixinInfo::get_one('id', $weapp_id, 1);
            $appid  = $wxinfo['appid'];
            //二维码
            $qrcode_img = env('QINIU_STATIC_DOMAIN').'/'.ltrim($wxinfo['qrcode'], '/'); //默认小程序码
            $encrypt_member_id = encrypt($member_id, 'E');
            $qrcode_type = env('APP_ENV') == 'production' ? 1 : 4;
            $qrcode_param = 'pages/decorate/decorate?distrib_member_id='.$encrypt_member_id.'&hyaline=1';
            $create_qrcode = (new WeixinService())->qrcodeAll($appid, $qrcode_type, $qrcode_param, null, ['hyaline' => true]);
            if (isset($create_qrcode['errcode']) && $create_qrcode['errcode'] == 0) {
                $qrcode_img = env('QINIU_STATIC_DOMAIN').'/'.ltrim($create_qrcode['url'], '/');
            }

            $member_info = MemberModel::get_data_by_id($member_id, $merchant_id);
            //头像
            $avatar_img = $member_info['avatar']; 
            //默认数据
            $bg_img = 'https://xcx.wrcdn.com/2018/7/3/7Djxck7EDM2I99DKyUgyBlSOmyy6.png';
            $text1  = '我是'.$member_info['name'];
            $text2  = '我为'.$wxinfo['nick_name'].'代言';
            if($qrcode_setting){
                $bg_img = env('QINIU_STATIC_DOMAIN').'/'.ltrim($qrcode_setting['background_img'], '/');
                $text1  = str_replace("{{guider_name}}", $member_info['name'], $qrcode_setting['text1']);
                $text2  = str_replace("{{applet_name}}", $wxinfo['nick_name'], $qrcode_setting['text2']);
            }
            $text1 = str_cut($text1, 36);
            $text2 = str_cut($text2, 108);

            $width  = 900;
            $height = 1160;

            //创建画布
            $canvas = new \Imagick();
            $canvas->readImageBlob(file_get_contents($bg_img));
            $canvas->thumbnailImage($width, $height);
            $canvas->setImageFormat('png');
            $canvas->setCompressionQuality(100);
            $canvas->enhanceImage();

            //风格一
            if($template == 1){
                //头像白色背景
                $avatar_back = new \Imagick();
                $avatar_back->newImage(152, 152, 'white');
                $avatar_back->roundCorners(360, 360);
                $canvas->compositeImage($avatar_back, \Imagick::COMPOSITE_OVER, (($width-152)/2), 120);
                //头像
                $avatar = new \Imagick();
                $avatar->readImageBlob(file_get_contents($avatar_img));
                $avatar->thumbnailImage(140, 140, true);
                $avatar->roundCorners(360, 360);
                $canvas->compositeImage($avatar, \Imagick::COMPOSITE_OVER, (($width-140)/2), 126);
                //昵称
                $text1_draw = new \ImagickDraw();
                $text1_draw->setFont(realpath('font/Microsoft-Yahei.ttf'));
                $text1_draw->setFontSize(48);
                $text1_draw->setFillColor('black');
                $text1_font_metrics = $canvas->queryFontMetrics($text1_draw, $text1);
                $canvas->annotateImage($text1_draw, (($width-$text1_font_metrics['textWidth'])/2), 350, 0, $text1);
                //描述
                $text2_draw = new \ImagickDraw();
                $text2_draw->setFont(realpath('font/Microsoft-Yahei.ttf'));
                $text2_draw->setFontSize(32);
                $text2_draw->setFillColor('black');
                //文字换行
                $text2_wrap = $this->autoWrap(32, 0, realpath('font/Microsoft-Yahei.ttf'), $text2, 800);
                if($text2_wrap){
                    $text_x = 410;
                    foreach($text2_wrap as $str){
                        $font_metrics = $canvas->queryFontMetrics($text2_draw, $str);
                        $canvas->annotateImage($text2_draw, (($width-$font_metrics['textWidth'])/2), $text_x, 0, $str);
                        $text_x += $font_metrics['textHeight'] + 3;
                    }
                }
                //二维码
                $qrcode = new \Imagick();
                $qrcode->readImageBlob(file_get_contents($qrcode_img));
                $qrcode->thumbnailImage(520, 520, true);
                $canvas->compositeImage($qrcode, \Imagick::COMPOSITE_OVER, (($width-520)/2), 520);
            }

            //风格二
            if($template == 2){
                //二维码
                $qrcode = new \Imagick();
                $qrcode->readImageBlob(file_get_contents($qrcode_img));
                $qrcode->thumbnailImage(520, 520, true);
                $canvas->compositeImage($qrcode, \Imagick::COMPOSITE_OVER, (($width-520)/2), ((($height-350)-520)/2));
                //昵称
                $text1_draw = new \ImagickDraw();
                $text1_draw->setFont(realpath('font/Microsoft-Yahei.ttf'));
                $text1_draw->setFontSize(48);
                $text1_draw->setFillColor('black');
                $name_font_metrics = $canvas->queryFontMetrics($text1_draw, $text1);
                $canvas->annotateImage($text1_draw, 60, 950, 0, $text1);
                //描述
                $text2_draw = new \ImagickDraw();
                $text2_draw->setFont(realpath('font/Microsoft-Yahei.ttf'));
                $text2_draw->setFontSize(32);
                $text2_draw->setFillColor('black');
                //文字换行
                $desc_wrap = $this->autoWrap(32, 0, realpath('font/Microsoft-Yahei.ttf'), $text2, 800);
                if($desc_wrap){
                    $text_x = 1010;
                    foreach($desc_wrap as $str){
                        $font_metrics = $canvas->queryFontMetrics($text2_draw, $str);
                        $canvas->annotateImage($text2_draw, 60, $text_x, 0, $str);
                        $text_x += $font_metrics['textHeight'] + 5;
                    }
                }
                //头像白色背景
                $avatar_back = new \Imagick();
                $avatar_back->newImage(152, 152, 'white');
                $avatar_back->roundCorners(360, 360);
                $canvas->compositeImage($avatar_back, \Imagick::COMPOSITE_OVER, $width-216, 910);
                //头像
                $avatar = new \Imagick();
                $avatar->readImageBlob(file_get_contents($avatar_img));
                $avatar->thumbnailImage(140, 140, true);
                $avatar->roundCorners(360, 360);
                $canvas->compositeImage($avatar, \Imagick::COMPOSITE_OVER, $width-210, 916);
            }

            //风格三
            if($template == 3){
                //头像白色背景
                $avatar_back = new \Imagick();
                $avatar_back->newImage(152, 152, 'white');
                $avatar_back->roundCorners(360, 360);
                $canvas->compositeImage($avatar_back, \Imagick::COMPOSITE_OVER, 60, 100);
                //头像
                $avatar = new \Imagick();
                $avatar->readImageBlob(file_get_contents($avatar_img));
                $avatar->thumbnailImage(140, 140, true);
                $avatar->roundCorners(360, 360);
                $canvas->compositeImage($avatar, \Imagick::COMPOSITE_OVER, 66, 106);
                //昵称
                $text1_draw = new \ImagickDraw();
                $text1_draw->setFont(realpath('font/Microsoft-Yahei.ttf'));
                $text1_draw->setFontSize(48);
                $text1_draw->setFillColor('white');
                $name_font_metrics = $canvas->queryFontMetrics($text1_draw, $text1);
                $canvas->annotateImage($text1_draw, 250, 145, 0, $text1);
                //描述
                $text2_draw = new \ImagickDraw();
                $text2_draw->setFont(realpath('font/Microsoft-Yahei.ttf'));
                $text2_draw->setFontSize(32);
                $text2_draw->setFillColor('white');
                //文字换行
                $desc_wrap = $this->autoWrap(32, 0, realpath('font/Microsoft-Yahei.ttf'), $text2, 800);
                if($desc_wrap){
                    $text_x = 200;
                    foreach($desc_wrap as $str){
                        $font_metrics = $canvas->queryFontMetrics($text2_draw, $str);
                        $canvas->annotateImage($text2_draw, 250, $text_x, 0, $str);
                        $text_x += $font_metrics['textHeight'] + 3;
                    }
                }
                //二维码
                $qrcode = new \Imagick();
                $qrcode->readImageBlob(file_get_contents($qrcode_img));
                $qrcode->thumbnailImage(520, 520, true);
                $canvas->compositeImage($qrcode, \Imagick::COMPOSITE_OVER, (($width-520)/2), ((($height+355)-520)/2));
            }

            //风格四
            if($template == 4){
                //二维码
                $qrcode = new \Imagick();
                $qrcode->readImageBlob(file_get_contents($qrcode_img));
                $qrcode->thumbnailImage(520, 520, true);
                $canvas->compositeImage($qrcode, \Imagick::COMPOSITE_OVER, (($width-520)/2), 100);
                $qrcode->clear();
                //头像白色背景
                $avatar_back = new \Imagick();
                $avatar_back->newImage(152, 152, 'white');
                $avatar_back->roundCorners(360, 360);
                $canvas->compositeImage($avatar_back, \Imagick::COMPOSITE_OVER, (($width-152)/2), 700);
                //头像
                $avatar_img = $member_info['avatar'];
                $avatar = new \Imagick();
                $avatar->readImageBlob(file_get_contents($avatar_img));
                $avatar->thumbnailImage(140, 140, true);
                $avatar->roundCorners(360, 360);
                $canvas->compositeImage($avatar, \Imagick::COMPOSITE_OVER, (($width-140)/2), 706);
                //昵称
                $text1_draw = new \ImagickDraw();
                $text1_draw->setFont(realpath('font/Microsoft-Yahei.ttf'));
                $text1_draw->setFontSize(48);
                $text1_draw->setFillColor('white');
                $name_font_metrics = $canvas->queryFontMetrics($text1_draw, $text1);
                $canvas->annotateImage($text1_draw, (($width-$name_font_metrics['textWidth'])/2), 930, 0, $text1);
                //描述
                $text2_draw = new \ImagickDraw();
                $text2_draw->setFont(realpath('font/Microsoft-Yahei.ttf'));
                $text2_draw->setFontSize(32);
                $text2_draw->setFillColor('white');
                //文字换行
                $desc_wrap = $this->autoWrap(32, 0, realpath('font/Microsoft-Yahei.ttf'), $text2, 800);
                if($desc_wrap){
                    $text_x = 990;
                    foreach($desc_wrap as $str){
                        $font_metrics = $canvas->queryFontMetrics($text2_draw, $str);
                        $canvas->annotateImage($text2_draw, (($width-$font_metrics['textWidth'])/2), $text_x, 0, $str);
                        $text_x += $font_metrics['textHeight'] + 3;
                    }
                }
            }

            $content = $canvas->getImageBlob();
            $canvas->clear(); //释放资源
            $content = base64_encode($content);
            $imgData = $this->upload($content);
            Cache::tags('distrib_merchant_'.$merchant_id)->forever($cacheKey, $imgData);
        }

        if(!empty($imgData)){
            return ['errcode' => 0, 'data' => $imgData];
        }else{
            return ['errcode' => -1, 'errmsg' => '获取推广码失败'];
        }
    }

    //文字自动换行
    private function autoWrap($fontSize, $angle, $fontFile, $string, $width) 
    {
        $content = "";
        // 将字符串拆分成一个个单字 保存到数组 letter 中
        for ($i = 0; $i < mb_strlen($string); $i ++) {
            $letter[] = mb_substr($string, $i, 1);
        }

        foreach ($letter as $l) {
            $teststr = $content."".$l;
            $testbox = imagettfbbox($fontSize, $angle, $fontFile, $teststr);
            // 判断拼接后的字符串是否超过预设的宽度
            if (($testbox[2] > $width) && ($content !== "")) {
                $content .= "\r\n";
            }
            $content .= $l;
        }
       
        return explode("\r\n", $content);
    }

    /**
     * 上传图片源码到七牛
     * @return array
     * @author: tangkang@dodoca.com
     */
    private function upload($content)
    {
        $bucket    = env('QINIU_BUCKET');
        $accessKey = env('QINIU_ACCESS_KEY');
        $secretKey = env('QINIU_SECRET_KEY');

        $auth = new Auth($accessKey, $secretKey);
        $upToken = $auth->uploadToken($bucket);//获取上传所需的token

        $filename = $this->getRandChar().'.png';
        $key      = date('Y').'/'.date('m').'/'.date('d').'/'.date('H').'/'.date('i').'/'.date('s').'/'.$filename;
        $base_key = \Qiniu\base64_urlSafeEncode($key);

        $client = new Client();
        $res = $client->request('POST', 'http://upload.qiniu.com/putb64/-1/key/'.$base_key, [
            'body' => $content,
            'headers' => [
                'Content-Type'  => 'image/png',
                'Authorization' => 'UpToken '.$upToken
            ]
        ]);

        $result = json_decode($res->getBody(), true);

        if(isset($result['key'])){
            return $result['key'];
        }
    }

    /**
     * 生成文件名称
     * @return array
     * @author: tangkang@dodoca.com
     */
    private function getRandChar(){
        $str = null;
        $strPol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $max = strlen($strPol)-1;

        for($i=0;$i<28;$i++){
            $str.=$strPol[rand(0,$max)];
        }

        $wlog = [
            'custom'  => 'rand_filename', //标识字段数据
            'content' => $str //日志内容
        ];
        CommonApi::wlog($wlog);

        return $str;
    }
}
