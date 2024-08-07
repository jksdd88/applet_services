<?php

namespace App\Http\Controllers\Super;
use DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use App\Models\Coupon;
use App\Models\User;
use App\Models\Merchant;
use App\Models\CouponCode;
use App\Models\CouponStatDay;
use App\Models\CouponUsageRecord;
use App\Models\CouponGoods;
use App\Models\Goods;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class CouponController extends Controller {

    function __construct(Coupon $coupon,Request $request) {
        $this->params = $request->all();
        $this->model = $coupon;
    }

    /**
     *  优惠券列表
     */
    function getCouponList() {
        $params   =$this->params;
        $offset   = isset($params['offset']) && $params['offset']? $params['offset'] : 0;
        $limit    = isset($params['limit']) && $params['limit']? $params['limit'] : 10;
        $status   = isset($params['status']) ? trim($params['status']) : 'all';
        $keywords = isset($params['keywords']) && $params['keywords'] ? trim($params['keywords']) : '';
        $sort     = isset($params['sort']) && $params['sort'] ? trim($params['sort']) : '';
        $company     = isset($params['company']) && $params['company'] ? trim($params['company']) : '';
        $merchantid     = isset($params['merchantid']) && $params['merchantid'] ? trim($params['merchantid']) : 0;
        $query = $this->model->select('coupon.id','coupon.merchant_id', 'coupon.name','coupon.time_type', 'coupon.effect_time', 'coupon.period_time', 'coupon.dt_validity_begin', 'coupon.dt_validity_end', 'coupon.content_type', 'coupon.coupon_val', 'coupon.is_condition', 'coupon.condition_val', 'coupon.memo', 'coupon.status', 'coupon.is_close','coupon.coupon_sum');


        //根据商家公司名称搜索
        if($company){
            $query->leftJoin("merchant","merchant.id","=","coupon.merchant_id");
            $query->where( 'merchant.company', "like", '%' . $company . '%');
        }
        //根据商家id搜索
        if($merchantid){
            $query->where( 'coupon.merchant_id', "=", $merchantid);
        }
        // 根据优惠券名称搜索
        if ($keywords) {
            $query->where( 'coupon.name','like', '%' . $keywords . '%');
        }
        // 根据优惠券状态搜索
        if (in_array($status,[0, 1, 2, 3]) && is_numeric($status)) {
            $query->where( 'coupon.status','=', $status);
        }
        $query->where(['coupon.is_delete' =>1]);
        $count = $query->count();
        $info  = $query->orderBy('coupon.created_time', 'DESC')->skip($offset)->take($limit)->get()->toArray();

        $amount_sort = [];
        foreach ($info as $key => $value) {
            //商户名称字段
            $merchant=Merchant::get_data_by_id($value["merchant_id"]);
            if($merchant){
                $info[$key]['company']=$merchant["company"];
            }
            //有效期范围
            switch ($value['time_type']) {
                case 0:
                    $info[$key]['effective_range'] = '领取后'.$value['effect_time'].'天生效，有效期'.$value['period_time'].'天';
                    break;
                case 1:
                    $a=strtotime($value['dt_validity_begin']);
                    $a=date('Y-m-d',$a);
                    $b=strtotime($value['dt_validity_end']);
                    $b=date('Y-m-d',$b);
                    $info[$key]['effective_range'] = $a.' 至 '.$b;
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
            // 优惠券状态
            switch ($value['status']) {
                case '0':
                    $info[$key]['status'] = '未生效';
                    break;
                case '1';
                    $info[$key]['status'] = '已生效';
                    break;
                case '2':
                    $info[$key]['status'] = '已过期';
                    break;
                case '3':
                    //$info[$key]['status'] = '随优惠码';
                    $info[$key]['status'] = '已生效';
                    break;
            }

            $sent_quantity_wheres = [
                [
                    'column'   => 'coupon_id',
                    'operator' => '=',
                    'value'    => $value['id'],
                ],
                [
                    'column'   => 'is_delete',
                    'operator' => '=',
                    'value'    => 1,
                ],
            ];
            //获取已派发数量
            $sent_quantity = CouponCode::get_data_count($sent_quantity_wheres);
            $info[$key]['count'] = $sent_quantity;
            $amount_sort[$key]   = $sent_quantity;
        }
        /*if($amount_sort && $sort) {
            if($sort == 'desc') {
                array_multisort($amount_sort,SORT_DESC,$info);
            }else {
                array_multisort($amount_sort,SORT_ASC,$info);
            }
        }*/
        return ['errcode' => 0, 'count' => $count, 'data' => $info];
    }

    /**
     *  优惠券详情
     */
    function getCouponDetail($id = '') {
        $info = $this->model->where(array('id' => $id,'is_delete'=>1))->first();
        if(!$info) {
            return Response::json(['errcode'=>59007,'errmsg'=>'当前优惠券不存在或已删除']);
        }
        //优惠券领取量
        $info['get_count'] =  CouponStatDay::where(['coupon_id'=>$id])->sum('get_count');

        $info['get_count'] = empty($info['get_count']) ? 0 : $info['get_count'];

        //优惠券使用量
        $info['use_count'] = CouponUsageRecord::get_data_count([['column'=>'coupon_id','operator'=>'=','value'=>$id],['column'=>'type','operator'=>'=','value'=>1]]);

        return Response::json(['errcode'=>0,'data'=>$info]);
    }
}
