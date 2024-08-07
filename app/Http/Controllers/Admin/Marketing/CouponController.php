<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use App\Models\Coupon;
use App\Models\CouponCode;
use App\Models\CouponStatDay;
use App\Models\CouponGoods;
use App\Models\Goods;
use App\Models\Member;
use App\Models\MemberInfo;
use App\Models\MemberCard;
use App\Services\CouponService;
use App\Jobs\CouponCodel;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\WeixinQrcode;
use App\Models\CouponSend;
use App\Models\CouponDaily;

class CouponController extends Controller {

    function __construct(Coupon $coupon,Request $request) {
        $this->params      = $request->all();
        $this->model       = $coupon;
        $this->merchant_id = Auth::user()->merchant_id;
    }

    /**
     *  优惠券列表
     */
    function getCouponList() {
        $params   = $this->params;
        $offset   = isset($params['offset']) ? $params['offset'] : 0;
        $limit    = isset($params['limit']) ? $params['limit'] : 10;
        $status   = isset($params['status']) && is_numeric($params['status']) ? $params['status'] : 'all';
        $keywords = isset($params['keywords']) ? trim($params['keywords']) : '';
        $sort     = isset($params['sort']) && $params['sort'] ? trim($params['sort']) : '';
        $valid    = isset($params['valid']) ? $params['valid'] : 0;

        $query = $this->model->select('id', 'merchant_id', 'name','time_type', 'effect_time', 'period_time', 'dt_validity_begin', 'dt_validity_end', 'content_type', 'coupon_val', 'is_condition', 'condition_val', 'memo', 'is_close', 'coupon_sum', 'send_num')
            ->where(['is_delete' =>1,'merchant_id' =>$this->merchant_id]);

        // 根据优惠券名称搜索
        if ($keywords) {
            $query->where( 'name','like', '%' . $keywords . '%');
        }
        
        //只获取有效的优惠劵
        if($valid){
            if(is_numeric($status)){
                switch ($status) {
                    case 0: //未生效
                        $query->where('time_type', 1)->where('dt_validity_begin', '>', Carbon::now())->whereRaw('coupon_sum > send_num');
                        break;
                    case 1: //已生效
                        $query->where(function ($query){
                            $query->where('time_type', 0)->orWhere(function ($query){
                                $query->where('time_type', 1)->where('dt_validity_begin', '<', Carbon::now())->where('dt_validity_end', '>', Carbon::now());
                            });
                        })->whereRaw('coupon_sum > send_num');
                        break;
                }
            }else{
                $query->where(function ($query){
                    $query->where('time_type', 0)->orWhere(function ($query){
                        $query->where('time_type', 1)->where('dt_validity_begin', '<', Carbon::now())->where('dt_validity_end', '>', Carbon::now());
                    });
                })->whereRaw('coupon_sum > send_num');
            }
        }else{
            if(is_numeric($status)){
                switch ($status) {
                    case 0: //未生效
                        $query->where('time_type', 1)->where('dt_validity_begin', '>', Carbon::now())->whereRaw('coupon_sum > send_num');
                        break;
                    case 1: //已生效
                        $query->where(function ($query){
                            $query->where('time_type', 0)->orWhere(function ($query){
                                $query->where('time_type', 1)->where('dt_validity_begin', '<', Carbon::now())->where('dt_validity_end', '>', Carbon::now());
                            });
                        })->whereRaw('coupon_sum > send_num');
                        break;
                    case 2: //已失效
                        $query->where('time_type', 1)->where(function ($query){
                            $query->where('dt_validity_end', '<', Carbon::now())->orWhereRaw('coupon_sum = send_num');
                        });
                        break;
                }
            }
        }
        
        $count = $query->count();
        $info  = $query->orderBy('created_time', 'DESC')->orderBy('time_type', 'DESC')->skip($offset)->take($limit)->get()->toArray();

        $amount_sort = [];

        $CouponService = new CouponService;

        foreach ($info as $key => $value) {
            // 有效期范围
            switch ($value['time_type']) {
                case 0:
                    $info[$key]['effective_range'] = '领取后'.$value['effect_time'].'天生效，有效期'.$value['period_time'].'天';
                    break;
                case 1:
                    $info[$key]['effective_range'] = date('Y-m-d', strtotime($value['dt_validity_begin'])).' 至 '.date('Y-m-d', strtotime($value['dt_validity_end']));
            }
            // 生效条件
            switch ($value['is_condition']) {
                case 0:
                    $info[$key]['is_condition'] = '无条件';
                    break;
                case 1:
                    $info[$key]['is_condition'] = '满'.$value['condition_val'].'元';
            }
            // 优惠内容
            switch ($value['content_type']) {
                case 1:
                    $info[$key]['content_val'] = '减'.$value['coupon_val'].'元';
                    break;
                case 2:
                    $info[$key]['content_val'] = '打'.$value['coupon_val'].'折';
            }
            // 判断优惠券状态
            $coupon_status = $CouponService->checkCouponStatus($value);
            $status = '';
            switch ($coupon_status) {
                case 0:
                    $status = '未生效';
                    break;
                case 1:
                    $status = '已生效';
                    break;
                case 2:
                    $status = '已失效';
                    break;
                default:
                    $status = '';
                    break;
            }

            $info[$key]['status'] = $status;

            $stock = $value['coupon_sum'] - $value['send_num'];
            $info[$key]['stock'] = $stock;

            $amount_sort[$key] = $stock;

            //------------------------↓------------------------Qrcode start 
            //是否生成二维码 1 商品 2 优惠劵 3 拼团，4秒杀
            //author :renruiqi@dodoca.com
            if(isset($this->params['appid'])  && !empty(trim($this->params['appid'])) ){
                $appid_qr = trim($this->params['appid']);
                $info[$key]['is_qrcode'] = count(WeixinQrcode::check($value['id'],2,$appid_qr))>0 ? 1 :2; //二维码是否生成 1:是 2:否
            }
            //------------------------↑------------------------Qrcodeend  
        }

        if($amount_sort && $sort) {
            if($sort == 'desc') {
                array_multisort($amount_sort, SORT_DESC, $info);
            }else {
                array_multisort($amount_sort, SORT_ASC, $info);
            }
        }

        return ['errcode' => 0, 'count' => $count, 'data' => $info];
    }

    /**
     *  添加优惠券
     */
    function postCoupon() {
        $params        = $this->params;
        $name          = isset($params['name']) && $params['name'] ? trim($params['name']) : '';
        $coupon_sum    = isset($params['coupon_sum']) && $params['coupon_sum'] ? trim($params['coupon_sum']) : '';
        $card_color    = isset($params['card_color']) && $params['card_color'] ? trim($params['card_color']) : '';
        $content_type  = isset($params['content_type']) && $params['content_type'] ? intval($params['content_type']) : 1;
        $coupon_val    = isset($params['coupon_val']) && $params['coupon_val'] ? trim($params['coupon_val']) : '';
        $is_condition  = isset($params['is_condition']) ? intval($params['is_condition']) : 0;
        $condition_val = isset($params['condition']) && $params['condition'] ? trim($params['condition']) : '';
        $time_type     = isset($params['time_type']) ? intval($params['time_type']) : 0;
        $get_type      = isset($params['get_type']) ? intval($params['get_type']) : 1;
        $get_num       = isset($params['get_num']) && $params['get_num'] ? intval($params['get_num']) : 1;
        $vipcard       = isset($params['vipcard']) && is_array($params['vipcard']) ? $params['vipcard'] : '';
        $is_preference = isset($params['is_preference']) ? intval($params['is_preference']) : 0;
        $rang_goods    = isset($params['rang_goods']) ? intval($params['rang_goods']) : 0;
        $memo          = isset($params['memo']) && $params['memo'] ? trim($params['memo']) : '';
        $qrcode        = isset($params['qrcode']) && $params['qrcode'] ? trim($params['qrcode']) : '';
        // 二次校驗必填參數
        if(!$name || !$card_color || !$coupon_val || !$get_type || !$coupon_sum) {
            return Response::json(['errcode'=>59000, 'errmsg'=>'参数错误']);
        }
        if($coupon_sum > 100000) {
            return Response::json(['errcode' => 59001, 'errmsg' => '总优惠码个数不能大于100000']);
        }
        $data = array(
            'merchant_id'   => $this->merchant_id,
            // 基本信息
            'name'          => $name,               // 優惠券名稱
            'coupon_sum'    => $coupon_sum,         // 優惠码总数
            'card_color'    => $card_color,         // 微信卡券顏色
            'content_type'  => $content_type,       // 優惠内容 1:減多少元 2:打幾折
            'coupon_val'    => $coupon_val,         // 優惠值
            'is_condition'  => $is_condition,       // 使用條件 0:無條件   1:有條件 滿多少元'
            'time_type'     => $time_type,          // 1:固定范围  2:領取后幾天生效，有效期幾天
            'is_preference' => $is_preference,      // 是否指定原價享受活動優惠 0:否 1:是
            'get_type'      => $get_type,           // 領取限制 1:無限制  2:有限制
            'memo'          => $memo,               // 優惠券詳情
            'rang_goods'    => $rang_goods,         // 是否是指定商品 0:所有商品  1:指定商品
            'is_delete'     => 1,
            'qrcode'        => $qrcode

        );
        // 若存在滿減條件，則執行之
        if($is_condition == 1) {
            if($condition_val) {
                if($content_type == 1 && $coupon_val > $condition_val) {
                    return Response::json(['errcode'=>59002,'errmsg'=>'优惠金额不能大于满减条件']);
                }
                $data = array_merge($data, array('condition_val' => $condition_val));
            }else {
                return Response::json(['errcode'=>59003,'errmsg'=>'请设置具体满多少元']);
            }
        }
        // 若存在限領，則執行之
        if($get_type == 2) {
            // 二次校驗必填參數
            if($get_num) {
                $data = array_merge($data,array('get_num'=> $get_num));
            }else {
                return Response::json(['errcode'=>59004,'errmsg'=>'请填写领取限制张数']);
            }
        }
        if($time_type == 1) {   // 活動有效期設置為固定範圍
            $dt_validity_begin = isset($params['dt_validity_begin']) && $params['dt_validity_begin'] ? trim($params['dt_validity_begin']) : '';
            $dt_validity_end   = isset($params['dt_validity_end']) && $params['dt_validity_end'] ? trim($params['dt_validity_end']) : '';

            if($dt_validity_begin && $dt_validity_end) {
                if($dt_validity_begin == '0000-00-00 00:00:00' || $dt_validity_end == '0000-00-00 00:00:00'){
                    return Response::json(['errcode'=>59005,'errmsg'=>'有效期时间设置错误']);
                }

                $dt_validity_end = $dt_validity_end." 23:59:59";

                if ($dt_validity_end <= Carbon::now()) {
                    // 既是即刻過期之優惠券，留着活動商品何用之有，不入庫，直接清除之
                    if(isset($params['goods_ids']) && $params['goods_ids']) {
                        unset($params['goods_ids']);
                    }
                }
                $data = array_merge($data, array('dt_validity_begin' => $dt_validity_begin, 'dt_validity_end' => $dt_validity_end));
            }else {
                return Response::json(['errcode'=>59005,'errmsg'=>'有效期时间设置错误']);
            }
        }else {     // 活動有效期設置為指定時間
            $effect_time = isset($params['effect_time']) && $params['effect_time'] ? trim($params['effect_time']) : 0;
            $period_time = isset($params['period_time']) && $params['period_time'] ? trim($params['period_time']) : '';

            if($period_time) {
                $data = array_merge($data, array('effect_time' => $effect_time, 'period_time' => $period_time));
            }else{
                return Response::json(['errcode'=>59005,'errmsg'=>'有效期指定时间设置错误']);
            }
        }
        // 會員等級限制
        if(!empty($vipcard)) {
            $vipcardInfo = MemberCard::where(['merchant_id'=>$this->merchant_id])->whereIn('id',$vipcard)->lists('id');
            if(!empty($vipcardInfo)) {
                $data = array_merge($data, array('vipcard' => serialize($vipcard)));
            }
        }

        $result = Coupon::insert_data($data);
        if(empty($result)) {
            return Response::json(['errcode' => 59006, 'errmsg' => '优惠券创建失败，请检查填写内容']);
        }
        // 若是指定商品
        if($rang_goods == 1) {
            $goods_ids = isset($params['goods_ids']) && $params['goods_ids'] ? explode(',', $params['goods_ids']) : '';
            if(!empty($goods_ids)) {
                foreach ($goods_ids as $value) {
                    $discount_goods = [
                        'merchant_id' => $this->merchant_id,
                        'coupon_id'   => $result,
                        'goods_id'    => $value,
                        'status'      => 1,
                        'is_delete'   => 1
                    ];
                    CouponGoods::insert_data($discount_goods);
                }
            }
        }
        return Response::json(['errcode' => 0, 'errmsg' => '添加成功']);
    }

    /**
     *  优惠券详情
     */
    function getCouponDetail($id = '') {
        $info = $this->model->where(array('id' => $id, 'merchant_id' => $this->merchant_id,'is_delete'=>1))->first();
        if(!$info) {
            return Response::json(['errcode'=>59007,'errmsg'=>'当前优惠券不存在或已删除']);
        }
        if($info['vipcard']) {
            $info['vipcard'] = unserialize($info['vipcard']);
        }
        if($info['rang_goods'] == 1) {
            $info['goods_ids'] = CouponGoods::where(array('coupon_id' => $id, 'status' => 1))->lists('goods_id');
            if(!empty($info['goods_ids'])) {
                $goods = array();
                foreach($info['goods_ids'] as $value) {
                    $goods[] = Goods::select('id','img')->where(array('id'=>$value,'merchant_id'=>$this->merchant_id))->first();
                }
                $info['goods'] = $goods;
            }
        }else{
            $info['goods'] = [];
        }
        return Response::json(['errcode'=>0,'data'=>$info]);
    }

    /**
     *  修改优惠券
     */
    function putCoupon($id) {
        $res = $this->model->select('id', 'name', 'coupon_sum')->where(array('id'=>$id,'merchant_id'=>$this->merchant_id))->first();
        if(!$res){
            return Response::json(['errcode'=>59007,'errmsg'=>'当前优惠券不存在或已删除']);
        }

        $params     = $this ->params;
        $name       = isset($params['name']) ? trim($params['name']) : '';
        $rang_goods = isset($params['rang_goods']) ? intval($params['rang_goods']) : 0;
        $supply_sum = isset($params['supply_sum']) ? intval($params['supply_sum']) : 0; //补充库存
        $qrcode     = isset($params['qrcode']) ? trim($params['qrcode']) : '';

        $data = [];
        $data['rang_goods'] = $rang_goods;
        $data['qrcode']     = $qrcode;
        if(!empty($name)){
            $data['name'] = $name;
        }
        if($supply_sum > 0){ //追加库存
            $data['coupon_sum'] = $res['coupon_sum'] + $supply_sum;
        }
        // 編輯自選商品
        if($rang_goods == 1) {
            // 假刪除操作，防止日後出現優化計算錯誤的情況下，有歷史活動商品可查
            CouponGoods::where('coupon_id', $id)->update(['status' => 0, 'is_delete' => -1]);
            $goods_ids = isset($params['goods_ids']) && $params['goods_ids'] ? explode(',', $params['goods_ids']) : '';

            if($goods_ids) {
                foreach ($goods_ids as $goods_id) {
                    // 重新添加指定的當前活動商品
                    $coupon_goods = [
                        'merchant_id' => $this->merchant_id,
                        'coupon_id'   => $id,
                        'goods_id'    => $goods_id,
                        'status'      => 1,
                        'is_delete'   => 1
                    ];
                    CouponGoods::insert_data($coupon_goods);
                }
            }
        }
		Coupon::update_data($id, $this->merchant_id, $data);
        return Response::json(['errcode'=>0,'errmsg'=>'修改成功']);
    }

    /**
     *  开启/关闭优惠券
     */

    function putCloseCoupon($id) {
        $is_close = Coupon::where(array('id' => $id, 'merchant_id' => $this->merchant_id))->pluck('is_close');
        if (!$is_close)
            return Response::json(['errcode'=>59008,'errmsg'=>'操作失败']);
        if ($is_close == 1) {
            $status = 2;
        } else {
            $status = 1;
        }
		Coupon::update_data($id, $this->merchant_id, ['is_close' => $status]);
        return Response::json(['errcode'=>0,'errmsg'=>'操作成功']);
    }

    /**
     *  删除优惠券
     */
    function deleteCoupon($id = '') {
        $res = $this->model->select('wxcard_id', 'wxcard_status')->where(array('merchant_id' => $this->merchant_id, 'id' => $id))->first();
        if (!$res) return array('error' => 50001, 'msg' => '无此优惠券');

        $has_member = CouponCode::where('coupon_id', '=', $id)->where('status', 0)->where('member_id', '<>', 0)->pluck('id');
        if ($has_member){
            return Response::json(['errcode'=>59009,'errmsg'=>'此券已有用户领取不可删除']);
        }
        // 假刪除
        Coupon::update_data($id,$this->merchant_id,array('is_delete' => -1));
        return Response::json(['errcode'=>0,'errmsg'=>'删除成功']);
    }

    /**
     *  批量 - 派发优惠码
     */
    function postBatchDistributeCode() {
        $params = $this->params;
        $merchant_id = $this->merchant_id;
        $coupon_id  = isset($params['coupon_id']) ? intval($params['coupon_id']) : '';
        $member_ids = isset($params['member_ids']) && $params['member_ids'] ? explode(',', $params['member_ids']) : '';

        if(!$coupon_id) {
            return ['errcode' => 99001, 'errmsg' => '未选择优惠劵'];
        }

        if(!$member_ids) {
            return ['errcode' => 99001, 'errmsg' => '未选择会员'];
        }

        $coupon = Coupon::where('id', $coupon_id)->where('merchant_id', $merchant_id)->first();

        if(!$coupon) {
            return ['errcode' => 50001, 'errmsg' => '当前优惠券不存在'];
        }

        if($coupon['time_type'] == 1 && $coupon['dt_validity_end'] <= Carbon::now()) {
            return ['errcode' => 50004, 'errmsg' => '优惠券已过期'];
        }

        // 會員等級限制
        $newIds = [];
        if($coupon['vipcard']) {
            $vipcards = unserialize($coupon['vipcard']);
            foreach($member_ids as $member_id) {
                $merber = Member::select('id','member_card_id')->where(['id' => $member_id, 'merchant_id' => $merchant_id])->first();
                if($merber){
                    $member_card =MemberCard::where(['id' => $merber['member_card_id'], 'merchant_id' => $merchant_id])->first();
                    if($member_card && in_array($member_card['id'], $vipcards)) {
                        $newIds[] = $member_id;
                    }
                }
            }

            if(empty($newIds)) {
                return ['errcode' => 50012, 'errmsg' => '暂无符合会员等级条件的派发'];
            }
        }else{
            foreach($member_ids as $member_id){
                $exist = Member::get_data_by_id($member_id, $merchant_id);
                if($exist){
                    $newIds[] = $member_id;
                }
            }

            if(empty($newIds)) {
                return ['errcode' => 50012, 'errmsg' => '没有选中会员'];
            }
        }

        $sent_quantity_wheres = [
            [
                'column'   => 'merchant_id',
                'operator' => '=',
                'value'    => $merchant_id,
            ],
            [
                'column'   => 'coupon_id',
                'operator' => '=',
                'value'    => $coupon_id,
            ],
            [
                'column'   => 'is_delete',
                'operator' => '=',
                'value'    => 1,
            ],
        ];
        //获取已派发数量
        $sent_quantity = CouponCode::get_data_count($sent_quantity_wheres);

        if($sent_quantity < $coupon['coupon_sum']){
            //已发数加上本次要发的数据
            $total = $sent_quantity + count($newIds);
            //如果劵数足够继续
            if($total <= $coupon['coupon_sum']){
                $success = 0;
                foreach($newIds as $member_id){
                    //优惠劵使用有效期 0->指定时间 1->固定范围
                    if($coupon['time_type'] == 1){
                        $start_time = $coupon['dt_validity_begin'];
                        $end_time   = $coupon['dt_validity_end'];
                    }else{
                        //领取后几天生效
                        $effect_time = $coupon['effect_time'];
                        //有效天数
                        $period_time = $coupon['period_time'];

                        if($effect_time > 0){
                            $start_time = Carbon::now()->addDays($effect_time)->toDateString();
                        }else{
                            $start_time = Carbon::now()->toDateString();
                        }
                        $valid_days = $effect_time + $period_time;
                        if($valid_days > 0){
                            $end_time   = Carbon::now()->addDays($valid_days)->toDateString().' 23:59:59';
                        }else{
                            $end_time   = Carbon::now()->toDateString().' 23:59:59';
                        }
                    }

                    //写入一条领取记录
                    $data = [
                        'merchant_id' => $merchant_id,
                        'member_id'   => $member_id,
                        'coupon_id'   => $coupon_id,
                        'code'        => str_random(),
                        'get_time'    => Carbon::now(),
                        'get_type'    => 1,
                        'start_time'  => $start_time,
                        'end_time'    => $end_time,
                        'is_delete'   => 1
                    ];
                    
                    if(CouponCode::insert_data($data)){
                        //记录发放数量
                        Coupon::where('id', $coupon_id)->increment('send_num');
                        $success++;
                    }
                }

                return ['errcode' => 0, 'errmsg' => '成功派发'.$success.'张优惠劵'];
                
            }else{
                return ['errcode' => 50013, 'errmsg' => "优惠劵数量不足"];
            }
        }else{
            return ['errcode' => 50007, 'errmsg' => '没有可派发的优惠码'];
        }
    }

    /**
     *  数据（领取记录&&使用记录）
     */

    function getRecord($coupon_id) {
        $params = $this ->params;
        $offset = isset($params['offset']) ? $params['offset'] : 0;
        $limit  = isset($params['limit']) ? $params['limit'] : 10;

        $res = $this->model->select('id', 'condition_val', 'is_condition', 'content_type', 'coupon_val')
            ->where(array('id' => $coupon_id, 'merchant_id' => $this->merchant_id))
            ->first();
        if(!$res)
            return Response::json(['errcode'=>59012,'msg'=>'此优惠券无记录']);

        if($res['content_type'] == 1){
            $content = '减'.$res['coupon_val'].'元';
        }else {
            $content = '打'.$res['coupon_val'].'折';
        }
        if($res['is_condition'] == 1) {
            $memo = '满'.$res['condition_val'].'元'.$content;
        }else {
            $memo = '无条件'.$content;
        }

        $query = CouponCode::select('member_id','member_info_id','code','get_time','use_time','get_type');
        $query->where('coupon_id',$coupon_id)
            ->where('get_time','!=','0000-00-00 00:00:00')
            ->orderBy('get_time', 'DESC')
            ->skip($offset)
            ->take($limit);
        $info = $query->get();
        $count = CouponCode::where('coupon_id',$coupon_id)->where('get_time','!=','0000-00-00 00:00:00')->count();

        foreach($info as &$value) {
            if($value['member_id']) {
                $value['name'] = Member::where(['id'=>$value['member_id'],'merchant_id' => $this->merchant_id])->pluck('name');
                $value['member_id'] =  $value['member_id'] + MEMBER_CONST;
            }

            $value['memo'] = $memo;
            if($value['get_time'] == '0000-00-00 00:00:00') {
                $value['get_time'] = '';
            }
            if($value['use_time'] == '0000-00-00 00:00:00') {
                $value['use_time'] = '';
            }
            switch ($value['get_type']) {
                case 1:
                    $value['get_type'] = '商户派发';
                    break;
                case 2:
                    $value['get_type'] = '用户领取';
                    break;
                case 3:
                    $value['get_type'] = '新用户有礼';
                    break;
                default:
                    $value['get_type'] = '直接使用';
                    break;
            }
        }
        return Response::json(['errcode'=>0,'_count'=>$count,'data'=>$info]);
    }

    /**
     *  自选商品
     */
    function getActivityGoods() {
        $query = Goods::select('id','title','price')->where(['is_delete'=>1,'merchant_id'=>$this->merchant_id])->get();
        return Response::json(['errcode'=>0,'data'=>$query]);
    }

    /**
     *  优惠券领取使用量
     */
    public function getCouponStatDays(Request $request)
    {
        $coupon_id = $request->input('coupon_id', 0);
        $startdate = $request->input('startdate', Carbon::now()->subDays(7)->toDateString());
        $enddate   = $request->input('enddate', Carbon::now()->subDays(1)->toDateString());

        if(!$coupon_id){
            return ['errcode' => 59000, 'errmsg' => '参数错误'];
        }

        $data = [];
        while (strtotime($startdate) <= strtotime($enddate)){
            $daily = CouponDaily::where('merchant_id', $this->merchant_id)->where('coupon_id', $coupon_id)->where('day_time', $startdate)->first();
            $data[] = [
                'date'           => $startdate,
                'get_count'      => $daily['get_count'] ? $daily['get_count'] : 0,
                'use_count'      => $daily['use_count'] ? $daily['use_count'] : 0,
                'get_count_user' => $daily['get_count_user'] ? $daily['get_count_user'] : 0,
                'use_count_user' => $daily['use_count_user'] ? $daily['use_count_user'] : 0
            ];
            $startdate = date('Y-m-d', strtotime('+1 day', strtotime($startdate)));
        }

        return ['errcode' => 0,'data' => $data];
    }

    /**
     *  优惠券领取日报下载
     */
    public function downDaily(Request $request)
    {
        $coupon_id = $request->input('coupon_id', 0);
        $startdate = $request->input('startdate', Carbon::now()->subDays(7)->toDateString());
        $enddate   = $request->input('enddate', Carbon::now()->subDays(1)->toDateString());

        if(!$coupon_id){
            return ['errcode' => 59000, 'errmsg' => '参数错误'];
        }

        $filename = '优惠劵日报.csv';

        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="'.$filename.'"');
        header('Cache-Control: max-age=0');

        //打开PHP文件句柄，php://output表示直接输出到浏览器
        $fp = fopen('php://output', 'a');
        //表格列表
        $head = ['日期','优惠券领取量','优惠券使用量','优惠券领取人数','优惠券使用人数'];
        foreach($head as $key => $val){
            $head[$key] = @iconv('UTF-8', 'GBK//IGNORE', $val);
        }
        fputcsv($fp, $head);

        while (strtotime($startdate) <= strtotime($enddate)){
            $daily = CouponDaily::where('merchant_id', $this->merchant_id)->where('coupon_id', $coupon_id)->where('day_time', $startdate)->first();
            $row = [
                'date'           => $startdate,
                'get_count'      => $daily['get_count'] ? $daily['get_count'] : 0,
                'use_count'      => $daily['use_count'] ? $daily['use_count'] : 0,
                'get_count_user' => $daily['get_count_user'] ? $daily['get_count_user'] : 0,
                'use_count_user' => $daily['use_count_user'] ? $daily['use_count_user'] : 0
            ];
            foreach($row as $k => $v){
                $row[$k] = @iconv('UTF-8', 'GBK//IGNORE', $v);
            }
            fputcsv($fp, $row);
            $startdate = date('Y-m-d', strtotime('+1 day', strtotime($startdate)));
        }
    }

    /**
     * 群发优惠券（生成群发优惠券记录）
     * @author wangshen@dodoca.com
     * @cdate 2018-7-3
     */
    public function couponSend($id,Request $request){
    
        //参数
        $params = $request->all();
    
        $merchant_id = $this->merchant_id;//商户id
    
        if(!$merchant_id){
            return ['errcode' => 99001,'errmsg' => '商户ID不存在'];
        }
    
        //优惠券id
        if(!$id){
            return ['errcode' => 99001,'errmsg' => '优惠券ID不存在'];
        }
        
        $coupon_info = Coupon::get_data_by_id($id, $merchant_id);
        
        if(!$coupon_info){
            return ['errcode' => 99001,'errmsg' => '优惠券不存在'];
        }
        
        //新增记录
        $insert_data = [
            'merchant_id' => $merchant_id,
            'coupon_id' => $id,
            'status' => 1,//群发优惠券状态：1->未执行，2->执行中，3->执行完毕
        ];
        
        $rs = CouponSend::insert_data($insert_data);
        
        if($rs){
            return ['errcode' => 0,'errmsg' => '操作成功，优惠券将陆续派发给用户'];
        }else{
            return ['errcode' => 60001,'errmsg' => '派发失败'];
        }
    }
    
}
