<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Auth;
use Carbon\Carbon;
use App\Models\NewUserGift;
use App\Models\NewUserGiftRecord;
use App\Models\Coupon;
use App\Models\CouponCode;
use App\Models\WeixinInfo;
use App\Services\CouponService;

/**
 * 新用户有礼
 *
 * @package default
 * @author 郭其凯
 **/

class NewUserGiftController extends Controller {

    public function __construct() 
    {
        $this->merchant_id = Auth::user()->merchant_id;
    }

    /**
     * 数据列表
     *
     * @return void
     * @author 
     **/
    public function lists(Request $request)
    {
        $merchant_id = $this->merchant_id;
        $offset      = $request->input('offset', 0);
        $limit       = $request->input('limit', 10);
        $keyword     = $request->input('keyword', '');
        $status      = $request->input('status', 'all');

        $query = NewUserGift::query();

        $query->where('merchant_id', $merchant_id)->where('is_delete', 1);

        if(!empty($keyword)){
            $query->where('title', 'like', '%'.$keyword.'%');
        }

        if(is_numeric($status)){
            switch ($status) {
                case 0:
                    $query->where('begin_time', '>', Carbon::now());
                    break;
                case 1:
                    $query->where('begin_time', '<', Carbon::now())->where('end_time', '>', Carbon::now());
                    break;
                case 3:
                    $query->where('end_time', '<', Carbon::now());
                    break;
            }
        }

        $total = $query->count();
        $data  = $query->orderBy('created_time', 'DESC')->skip($offset)->take($limit)->get();

        foreach($data as &$row){
            //活动当前状态
            if($row->begin_time > Carbon::now()){
                $status = 0;
            }
            if($row->begin_time < Carbon::now() && $row->end_time > Carbon::now()){
                $status = 1;
            }
            if($row->end_time < Carbon::now()){
                $status = 3;
            }
            $row->status = isset($status) && $row->status != 2 ? $status : $row->status;
            //参与人数
            $participants = NewUserGiftRecord::where('new_user_gift_id', $row->id)->count();
            $row->participants = $participants;
        }

        return ['errcode' => 0, 'count' => $total, 'data' => $data];
    }

    /**
     * 单条活动详情
     *
     * @return void
     * @author 
     **/
    public function detail(CouponService $CouponService, Request $request, $id)
    {
        $merchant_id = $this->merchant_id;
        $info = NewUserGift::get_data_by_id($id, $merchant_id);

        if($info){

            //活动当前状态
            if($info->begin_time > Carbon::now()){
                $status = 0;
            }
            if($info->begin_time < Carbon::now() && $info->end_time > Carbon::now()){
                $status = 1;
            }
            if($info->end_time < Carbon::now()){
                $status = 3;
            }

            $info->status = isset($status) && $info->status != 2 ? $status : $info->status;

            $coupon_ids = json_decode($info->coupon_ids, true);

            $coupon_list = [];

            if($coupon_ids){
                $coupon_list = Coupon::select('id', 'name', 'time_type', 'effect_time', 'period_time', 'dt_validity_begin', 'dt_validity_end', 'content_type', 'coupon_val', 'is_condition', 'condition_val', 'coupon_sum', 'send_num', 'is_delete')->whereIn('id', $coupon_ids)->get();
                foreach($coupon_list as &$row){
                    $row->stock = $row->coupon_sum - $row->send_num;

                    if($row->time_type == 1){
                        $row->effective_range = date('Y-m-d', strtotime($row->dt_validity_begin)).' 至 '.date('Y-m-d', strtotime($row->dt_validity_end));
                    }else{
                        $row->effective_range = '领取后'.$row->effect_time.'天生效，有效期'.$row->period_time.'天';
                    }

                    if($row->content_type == 1){
                        $row->coupon_val = '减'.$row->coupon_val.'元';
                    }else if($row->content_type == 2){
                        $row->coupon_val = '打'.floatval($row->coupon_val).'折';
                    }

                    if($row->is_condition == 1){
                        $row->is_condition = '满'.$row->condition_val.'元';
                    }else{
                        $row->is_condition = '无条件';
                    }
                    // 判断优惠券状态
                    $coupon_status = $CouponService->checkCouponStatus($row->toArray());
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
                    $row->status = $status;

                    $remind = '';
                    if($coupon_status == 2){
                        $remind = '已失效的券将无法发放';
                    }

                    if($row->is_delete != 1){
                        $remind = '已删除的券将无法发放';
                    }

                    $row->remind = $remind;
                }
            }

            $info->coupon_ids = json_decode($info->coupon_ids, true);
            $info->coupon_list = $coupon_list;

            return ['errcode' => 0, 'data' => $info];
        }

        return ['errcode' => 190001, 'errmsg' => '活动不存在'];
    }

    /**
     * 保存一条活动数据
     *
     * @return void
     * @author 
     **/
    public function store(Request $request)
    {
        $merchant_id = $this->merchant_id;
        $title       = $request->input('title', '');
        $begin_time  = $request->input('begin_time');
        $end_time    = $request->input('end_time');
        $banner      = $request->input('banner', '');
        $coupon_ids  = $request->input('coupon_ids');

        if(empty($title)){
            return ['errcode' => 99001, 'errmsg' => '活动名称不能为空'];
        }

        if(!$begin_time || $begin_time == '0000-00-00 00:00'){
            return ['errcode' => 99001, 'errmsg' => '活动开始时间必须'];
        }

        if(!$end_time || $end_time == '0000-00-00 00:00'){
            return ['errcode' => 99001, 'errmsg' => '活动结束时间必须'];
        }

        $is_exist = NewUserGift::where('merchant_id', $merchant_id)
            ->where(function ($query) use ($begin_time, $end_time){
                $query->where(function ($query) use ($begin_time, $end_time){
                    $query->where('begin_time', '<=', $end_time)->where('end_time', '>=', $end_time);
                });
                $query->orWhere(function ($query) use ($begin_time, $end_time){
                    $query->where('begin_time', '<=', $begin_time)->where('end_time', '>=', $begin_time);
                });
                $query->orWhere(function ($query) use ($begin_time, $end_time){
                    $query->where('begin_time', '<=', $begin_time)->where('end_time', '>=', $end_time);
                });
                $query->orWhere(function ($query) use ($begin_time, $end_time){
                    $query->where('begin_time', '>=', $begin_time)->where('end_time', '<=', $end_time);
                });
            })
            ->where('is_delete', 1)
            ->first();

        if($is_exist){
            return ['errcode' => 190002, 'errmsg' => '该时段已有新用户有礼活动，请重新选择活动时间'];
        }

        $status = 0;
        if($begin_time < Carbon::now() && $begin_time > Carbon::now()){
            $status = 1;
        }

        $data = [
            'merchant_id' => $merchant_id,
            'title'       => $title,
            'begin_time'  => $begin_time,
            'end_time'    => $end_time,
            'banner'      => $banner,
            'coupon_ids'  => json_encode($coupon_ids),
            'status'      => $status
        ];

        if(NewUserGift::create($data)){
            return ['errcode' => 0, 'errmsg' => '创建成功'];
        }else{
            return ['errcode' => 99003, 'errmsg' => '创建失败'];
        }
    }

    /**
     * 编辑一条活动数据
     *
     * @return void
     * @author 
     **/
    public function edit(Request $request, $id)
    {
        $merchant_id = $this->merchant_id;
        $title       = $request->input('title', '');
        $begin_time  = $request->input('begin_time');
        $end_time    = $request->input('end_time');
        $banner      = $request->input('banner', '');
        $coupon_ids  = $request->input('coupon_ids');

        $info = NewUserGift::get_data_by_id($id, $merchant_id);
        if($info){
            if(empty($title)){
                return ['errcode' => 99001, 'errmsg' => '活动名称不能为空'];
            }

            if(!$begin_time || $begin_time == '0000-00-00 00:00'){
                return ['errcode' => 99001, 'errmsg' => '活动开始时间必须'];
            }

            if(!$end_time || $end_time == '0000-00-00 00:00'){
                return ['errcode' => 99001, 'errmsg' => '活动结束时间必须'];
            }

            $is_exist = NewUserGift::where('id', '<>', $id)
                ->where('merchant_id', $merchant_id)
                ->where(function ($query) use ($begin_time, $end_time){
                    $query->where(function ($query) use ($begin_time, $end_time){
                        $query->where('begin_time', '<=', $end_time)->where('end_time', '>=', $end_time);
                    });
                    $query->orWhere(function ($query) use ($begin_time, $end_time){
                        $query->where('begin_time', '<=', $begin_time)->where('end_time', '>=', $begin_time);
                    });
                    $query->orWhere(function ($query) use ($begin_time, $end_time){
                        $query->where('begin_time', '<=', $begin_time)->where('end_time', '>=', $end_time);
                    });
                    $query->orWhere(function ($query) use ($begin_time, $end_time){
                        $query->where('begin_time', '>=', $end_time)->where('end_time', '<=', $end_time);
                    });
                })
                ->where('is_delete', 1)
                ->first();

            if($is_exist){
                return ['errcode' => 190002, 'errmsg' => '该时段已有新用户有礼活动，请重新选择活动时间'];
            }

            $data = [
                'title'      => $title,
                'begin_time' => $begin_time,
                'end_time'   => $end_time,
                'banner'     => $banner,
                'coupon_ids' => json_encode($coupon_ids)
            ];

            NewUserGift::update_data($id, $merchant_id, $data);

            return ['errcode' => 0, 'errmsg' => '操作成功'];
        }else{
            return ['errcode' => 190001, 'errmsg' => '活动不存在'];
        }
    }

    /**
     * 编辑一条活动数据
     *
     * @return void
     * @author 
     **/
    public function changeStatus(Request $request, $id)
    {
        $merchant_id = $this->merchant_id;
        $status = $request->input('status', 0);

        $info = NewUserGift::get_data_by_id($id, $merchant_id);
        if($info){
            if(in_array($status, [1, 2])){
                $update = NewUserGift::update_data($id, $merchant_id, ['status' => $status]);
                if($update){
                    return ['errcode' => 0, 'errmsg' => '操作成功'];
                }else{
                    return ['errcode' => 99003, 'errmsg' => '操作失败'];
                }
            }
        }else{
            return ['errcode' => 190001, 'errmsg' => '活动不存在'];
        }
    }

    /**
     * 删除一条活动数据
     *
     * @return void
     * @author 
     **/
    public function delete(Request $request, $id)
    {
        $merchant_id = $this->merchant_id;
        $info = NewUserGift::get_data_by_id($id, $merchant_id);
        if($info){
            $update = NewUserGift::update_data($id, $merchant_id, ['is_delete' => -1]);
            if($update){
                return ['errcode' => 0, 'errmsg' => '操作成功'];
            }else{
                return ['errcode' => 99003, 'errmsg' => '操作失败'];
            }
        }else{
            return ['errcode' => 190001, 'errmsg' => '活动不存在'];
        }
    }

    /**
     * 活动数据
     *
     * @return void
     * @author 
     **/
    public function data(Request $request, $id)
    {
        $merchant_id = $this->merchant_id;
        $info = NewUserGift::get_data_by_id($id, $merchant_id);
        if($info){
            $data = [
                'start_time' => date('Y-m-d', strtotime($info->begin_time)),
                'end_time'   => date('Y-m-d', strtotime($info->end_time))
            ];

            $daily_data = [];

            for($i = strtotime($data['start_time']); $i <= strtotime($data['end_time']); $i += 86400) {
                $date = date('Y-m-d', $i);
                $get_start_time = $date;
                $get_end_time   = $date.' 23:59:59';

                $amount = NewUserGiftRecord::where('new_user_gift_id', $id)
                    ->where('created_time', '>', $get_start_time)
                    ->where('created_time', '<', $get_end_time)
                    ->count();

                $daily_data[] = [
                    'date'   => $date,
                    'amount' => $amount
                ];
            }

            $data['daily_data'] = $daily_data;

            return ['errcode' => 0, 'data' => $data];
        }else{
            return ['errcode' => 190001, 'errmsg' => '活动不存在'];
        }
    }

    /**
     * 活动数据分析
     *
     * @return void
     * @author 
     **/
    public function couponData(Request $request, $id)
    {
        $merchant_id = $this->merchant_id;
        $info = NewUserGift::get_data_by_id($id, $merchant_id);
        if($info){
            $merchant_id = $info['merchant_id'];
            //优惠劵数据
            $coupon_ids = json_decode($info['coupon_ids'], true);

            $coupon_data = [];

            if($coupon_ids){
                foreach($coupon_ids as $coupon_id){
                    $coupon = Coupon::get_data_by_id($coupon_id, $merchant_id);
                    if($coupon){
                        $giving_amount = CouponCode::where('coupon_id', $coupon_id)->where('get_type', 3)->count();
                        $usage_amount  = CouponCode::where('coupon_id', $coupon_id)->where('get_type', 3)->where('status', 1)->count();

                        $coupon_data[] = [
                            'name'          => $coupon['name'],
                            'coupon_sum'    => $coupon['coupon_sum'],
                            'giving_amount' => $giving_amount,
                            'usage_amount'  => $usage_amount
                        ];
                    }
                }
            }
            return ['errcode' => 0, 'data' => $coupon_data];
        }else{
            return ['errcode' => 190001, 'errmsg' => '活动不存在'];
        }
    }

    /**
     * 活动数据分析
     *
     * @return void
     * @author 
     **/
    public function dailyData(Request $request, $id)
    {
        $merchant_id = $this->merchant_id;
        $start_time = $request->start_time;
        $end_time   = $request->end_time;

        $info = NewUserGift::get_data_by_id($id, $merchant_id);
        if($info){
            if(empty($start_time) || $start_time == '0000-00-00'){
                $start_time = $info['begin_time'];
            }

            if(empty($end_time) || $start_time == '0000-00-00'){
                $end_time = $info['end_time'];
            }
            
            $daily_data = [];

            for($i = strtotime($start_time); $i <= strtotime($end_time); $i += 86400) {
                $date = date('Y-m-d', $i);
                $get_start_time = $date;
                $get_end_time   = $date.' 23:59:59';

                $amount = NewUserGiftRecord::where('new_user_gift_id', $id)
                    ->where('created_time', '>', $get_start_time)
                    ->where('created_time', '<', $get_end_time)
                    ->count();

                $daily_data[] = [
                    'date'   => $date,
                    'amount' => $amount
                ];
            }
            
            return ['errcode' => 0, 'data' => $daily_data];
        }else{
            return ['errcode' => 190001, 'errmsg' => '活动不存在'];
        }
    }
} // END class 
