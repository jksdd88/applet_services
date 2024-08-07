<?php

namespace App\Http\Controllers\Admin\marketing;

use App\Models\DiscountActivity;
use App\Models\OrderGoodsUmp;
use App\Models\Member;
use App\Models\DiscountGoods;
use App\Models\DiscountItem;
use App\Models\Goods;
use App\Models\OrderInfo;
use App\Models\UserLog;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
//use function Qiniu\json_decode;

class DiscountActivityController extends Controller
{
    private $merchant_id;

    function __construct(DiscountActivity $discountActivity, Request $request)
    {
        $this->params = $request->all();
        $this->model = $discountActivity;
        if (app()->isLocal()) {
            $this->user_id = 1;
            $this->merchant_id = 2;
        } else {
            $this->user_id = Auth::user()->id;
            $this->merchant_id = Auth::user()->merchant_id;
        }

    }

    /**
     *  满就减活动列表
     */
    function getActivity()
    {
        if (empty($this->merchant_id)) {
            return Response::json(['errcode' => 100001, 'errmsg' => '非法操作']);
        }
        $params = $this->params;
        $offset = isset($params['offset']) ? $params['offset'] : 0;
        $limit = isset($params['limit']) ? $params['limit'] : 10;
        $title = isset($params['title']) ? trim($params['title']) : '';
        $query = $this->model->where(['is_delete' => 1, 'merchant_id' => $this->merchant_id]);
        if ($title) {
            $query->where('title', 'like', '%' . $title . '%');
        }

        $count = $query->count();
        $info = $query->orderBy('created_time', 'DESC')
            ->skip($offset)
            ->take($limit)
            ->get();
        foreach ($info as $key => $value) {
            switch ($value['status']) {
                case '0':
                    $info[$key]['status'] = '未生效';
                    break;
                case '1':
                    $info[$key]['status'] = '已生效';
                    break;
                case '2':
                    $info[$key]['status'] = '已过期';
                    break;
                default:
                    break;
            }
        }
        return Response::json(['errcode' => 0, '_count' => $count, 'data' => $info]);
    }

    /**
     *  删除 - 满就减活动
     */
    function deleteActivity($id)
    {
        $res = DiscountActivity::where(array('id' => $id, 'merchant_id' => $this->merchant_id, 'is_delete' => 1))->first();
        if (!$res)
            return Response::json(['errcode' => 100002, 'errmsg' => '此满减不存在或已删除']);
        // 假删除
        DiscountActivity::where(array('id' => $id, 'merchant_id' => $this->merchant_id))->update(array('is_delete' => -1));
        DiscountGoods::where(array('discount_id' => $id, 'merchant_id' => $this->merchant_id))->update(array('status' => 0));
        return Response::json(['errcode' => 0, 'errmsg' => '删除成功']);
    }

    /**
     *  添加 - 满就减活动
     */
    function postActivity()
    {
        $params = $this->params;
        $title = isset($params['title']) && $params['title'] ? trim($params['title']) : '';
        $range = isset($params['range']) ? intval($params['range']) : 2;
        $start_time = isset($params['start_time']) && $params['start_time'] ? trim($params['start_time']) : '';
        $end_time = isset($params['end_time']) && $params['end_time'] ? trim($params['end_time']) : '';

        // 二次校验必填参数
        if (!$title || !$start_time || !$end_time ) {
            return Response::json(['errcode' => 100003, 'errmsg' => '参数错误']);
        }
        $check_res = $this->getDiscountCheck(array('start_time' => $start_time, 'end_time' => $end_time,'range'=>$range));
        //----------日志 start----------
        $data_UserLog['merchant_id']=$this->merchant_id;
        $data_UserLog['user_id']=$this->user_id;
        $data_UserLog['type']=44;
        $data_UserLog['url']='merchant/merchant.json';
        $data_UserLog['content']=json_encode(array(
            '$check_res'=>json_encode($check_res),
        ));
        $data_UserLog['ip']=get_client_ip();
        $data_UserLog['created_time']=date('Y-m-d H:i:s');
        $data_UserLog['updated_time']=date('Y-m-d H:i:s');
        UserLog::insertGetId($data_UserLog);
        //----------日志 end----------
        if (!$check_res['status']) {
            if (!empty($check_res['msg'])) {
                $errmsg = $check_res['msg'];
            } else {
                $errmsg = '该商品此时间段不能参与满减';
            }
            return Response::json(['errcode' => 100004, 'errmsg' => $errmsg]);
        }
        $start_time = $start_time . " 00:00:00";
        $end_time = $end_time . " 23:59:59";
        $data = array(
            'merchant_id' => $this->merchant_id,
            'title' => $title,               // 活动标题
            'start_time' => $start_time,          // 开始时间
            'end_time' => $end_time,            // 结束时间
            'range' => $range,               // 活动范围 1:自选商品 2:全部商品
            'is_delete' => 1                     // 活动范围 1:自选商品 2:全部商品
        );
        $nowtime = date('Y-m-d H;i;s', time());
        if ($start_time <= $nowtime && $nowtime < $end_time) {
            $data = array_merge($data, array('status' => 1));// 其所添加之时间若在当前有效期内，则即刻生效之
        } elseif ($end_time <= $nowtime) {
            $data = array_merge($data, array('status' => 2));// 其所添加之结束时间若小于当前时间，直接过期
            if (isset($params['goods_ids']) && $params['goods_ids']) {// 过期活动 直接删除
                unset($params['goods_ids']);
            }
        }
        $result = DiscountActivity::create($data);
        $last_id = $result->id;
        // 若是自选商品
        if ($range == 1) {
            $goods_ids = isset($params['goods_ids']) && $params['goods_ids'] ? explode(',', $params['goods_ids']) : '';

            if (!empty($goods_ids)) {
                foreach ($goods_ids as $value) {
                    $discount_goods = array(
                            'merchant_id' => $this->merchant_id,
                            'discount_id' => $last_id,
                            'goods_id' => $value,
                            'status' => 1
                        );
                    DiscountGoods::create($discount_goods);
                }
            } else {
                DiscountActivity::where(['id' => $last_id, 'merchant_id' => $this->merchant_id])->delete();
                return Response::json(['errcode' => 100005, 'errmsg' => '请选择要添加的活动商品']);
            }
        }
        // 添加优惠条件
        if (isset($params['items']) && $params['items']) {
            // 冒泡法校驗是否出現多個重複條件
            //dd($params);
            foreach ($params['items'] as $check) {
                $ids[] = $check['condition'];
            }
            $count_range = count($ids);
            for ($i = 0; $i < $count_range; $i++) {
                if (!preg_match('/^([+-]?[1-9][\d]{0,3}|0)([.]?[\d]{1,2})?$/', $ids[$i])) {
                    DiscountActivity::where('id', $last_id)->delete();
                    DiscountGoods::where('discount_id', $last_id)->delete();
                    return Response::json(['errcode' => 100006, 'errmsg' => '优惠条件金额格式不符合规范']);

                }
                for ($j = $i + 1; $j < $count_range; $j++) {
                    if ($ids[$i] == $ids[$j]) {
                        DiscountActivity::where('id', $last_id)->delete();
                        DiscountGoods::where('discount_id', $last_id)->delete();
                        return Response::json(['errcode' => 100007, 'errmsg' => '多个优惠条件不能重复']);
                    }
                }
            }
            foreach ($params['items'] as $value) {
                $condition = isset($value['condition']) && $value['condition'] ? trim($value['condition']) : '';    // 满多少元
                $cash_val = isset($value['cash_val']) && $value['cash_val'] ? trim($value['cash_val']) : '';    // 减现金多少
                $reduction = isset($value['reduction']) && $value['reduction'] ? trim($value['reduction']) : '';    // 满减开关
                if(isset($value['reduction']) && $value['reduction']== 2){
                    $cash_val = 0;
                }
                $postage = isset($value['postage']) && $value['postage'] ? trim($value['postage']) : '';    // 满包邮开关

                if ($cash_val > $condition) {
                    DiscountActivity::where('id', $last_id)->delete();
                    DiscountGoods::where('discount_id', $last_id)->delete();
                    return Response::json(['errcode' => 100008, 'errmsg' => '减现金的金额不能大于满多少元的条件']);
                }
                $discount_item = array(
                    'merchant_id' => $this->merchant_id,
                    'discount_id' => $last_id,
                    'condition' => $condition,      // 优惠条件 满多少元2
                    'cash_val' => $cash_val,       // 减多少或打几折
                    'reduction' => $reduction,
                    'postage' => $postage,
                    'created_time' => $nowtime,
                    'updated_time' => $nowtime
                );
                $item_id = DiscountItem::insertGetId($discount_item);
                if (empty($item_id)) {
                    return Response::json(['errcode' => 100009, 'errmsg' => '添加失败']);

                }
            }
        }
        return Response::json(['errcode' => 0, 'errmsg' => '添加成功']);

    }

    /**
     *  满就减活动详情
     */
    function getActivityDetail($id)
    {
        $info = DiscountActivity::select('title', 'start_time', 'end_time', 'status', 'range')
            ->where(['id' => $id, 'merchant_id' => $this->merchant_id, 'is_delete' => 1])->first();
        if (!$info) {
            return Response::json(['errcode' => 100010, 'errmsg' => '此满减不存在或已删除']);
        }
        if ($info['range'] == 1) {
            $status = 1;
            if ($info['status'] == 2) {
                $status = 0;
            }
            $info['goods_ids'] = DiscountGoods::where(array('discount_id' => $id, 'status' => $status))->lists('goods_id');
            if (!empty($info['goods_ids'])) {
                $goods = array();
                foreach ($info['goods_ids'] as $value) {
                    $goods[] = Goods::select('id', 'title', 'img', 'price', 'stock', 'csale')->where(array('id' => $value, 'merchant_id' => $this->merchant_id))->first();
                }
                $info['goods'] = $goods;
            }
        }
        $items = DiscountItem::select('id', 'condition', 'cash_val', 'reduction', 'postage')
            ->where('discount_id', $id)
            ->orderBy('id', 'ASC')
            ->get()
            ->toArray();
        $info['items'] = $items;
        return Response::json(['errcode' => 0, 'data' => $info]);
    }

    /**
     *  满就减活动 优惠记录列表
     */
    function getPreferentialRecord()
    {
        $params = $this->params;
        $offset = isset($params['offset']) ? $params['offset'] : 0;
        $limit = isset($params['limit']) ? $params['limit'] : 10;
        $ids = DiscountActivity::where(['merchant_id' => $this->merchant_id, 'is_delete' => 1])->lists('id');
        $query = OrderGoodsUmp::select('order_id', 'memo')->whereIn('ump_id', $ids)->whereIn('ump_type', [7,8])->where('order_id', '>' , 0);
        $count = $query->count();
        $info = $query->skip($offset)
            ->take($limit)
            ->orderBy('id', 'desc')
            ->get();
        foreach ($info as &$value) {
            $orderInfo = OrderInfo::select('member_id', 'order_sn', 'amount', 'status', 'created_time', 'order_goods_type')->where(['id' => $value['order_id'], 'merchant_id' => $this->merchant_id])->first();
            //$value['status'] = $this->convertOrderStatus($orderInfo['status']);
            $value['status'] = $this->orderStatus($orderInfo['status'],1,$orderInfo);
            $value['name'] = Member::where('id', $orderInfo['member_id'])->pluck('name');
            $value['order_sn'] = $orderInfo['order_sn'];
            $value['amount'] = $orderInfo['amount'];
            $value['created_time'] = $orderInfo['created_time'];
        }
        return Response::json(['errcode' => 0, 'count' => $count, 'data' => $info]);

    }

    /**
     *  满就减活动时间校验
     */
    function getDiscountCheck()
    {
        $params = $this->params;
        $start_time = isset($params['start_time']) && $params['start_time'] ? trim($params['start_time']) : '';
        $end_time = isset($params['end_time']) && $params['end_time'] ? trim($params['end_time']) : '';
        if (!$start_time ) {
            return array('status' => false, 'msg' => '开始时间错误');
        }else if (!$end_time) {
            return array('status' => false, 'msg' => '截止时间错误');
        }
        $start_time = $start_time . " 00:00:00";
        $end_time = $end_time . " 23:59:59";
        if (strtotime($start_time) > strtotime($end_time)) {
            return array('status' => false, 'msg' => '结束时间必须大于开始时间');
        }

        $goods_ids = isset($params['goods_ids']) && $params['goods_ids'] ? explode(',', $params['goods_ids']) : '';
        
        $date = date('Y-m-d H:i:s');
        // 查询当前时间区间内是否存在其它满就送活动
        //dd($goods_ids);
        $res = DiscountActivity::select('discount_activity.id', 'discount_activity.range', 'discount_activity.start_time', 'discount_activity.end_time', 'discount_activity.status', 'discount_activity.is_delete')
            ->leftjoin('discount_goods','discount_goods.discount_id','=','discount_activity.id')
            ->where(array('discount_activity.merchant_id' => $this->merchant_id, 'discount_activity.is_delete' => 1))
            //->whereIn('discount_goods.goods_id',$goods_ids)
//            ->whereIn('status', [0, 1])
            ->where('discount_activity.end_time', '>', $date)//未结束的满减活动
            ->orderBy('discount_activity.id', 'DESC')
            ->get();
        
        if ($res->isEmpty()) 
            return array('status' => true, 'range' => 'all');//无任何满减活动
        $range1 = array();  // 自選商品
        $range2 = array();  // 全店參與
        foreach ($res as $key => $value) {
            if ($value['range'] == 2) {
                $range2 = array_merge(array($key => $value), $range2);
            } else {
                $range1 = array_merge(array($key => $value), $range1);
            }
        }
        
        //----------日志 start----------
        //$data_UserLog['merchant_id']=Auth::user()->merchant_id;
        //$data_UserLog['user_id']=Auth::user()->id;
        $data_UserLog['merchant_id']=$this->merchant_id;
        $data_UserLog['user_id']=$this->user_id;
        $data_UserLog['type']=42;
        $data_UserLog['url']='merchant/merchant.json';
        $data_UserLog['content']=json_encode(array(
            'range=1'=>json_encode($range1),
            'range=2'=>json_encode($range2),
        ));
        $data_UserLog['ip']=get_client_ip();
        $data_UserLog['created_time']=date('Y-m-d H:i:s');
        $data_UserLog['updated_time']=date('Y-m-d H:i:s');
        UserLog::insertGetId($data_UserLog);
        //----------日志 end----------
        
        //已有活动包含全店商品参加的活动
        if (!empty($range2)) {
            foreach ($range2 as $val) {
                if (strtotime($end_time) < strtotime($val['start_time']) || strtotime($start_time) > strtotime($val['end_time'])) {
                    continue;
                } else {
                    return array('status' => false, 'msg' => '当前活动有效期内有其它正在进行的活动，不得重复创建.');
                }
            }
        }
        //已有活动包含自选商品参加的活动
        //dd($range1);
        if (!empty($range1)) {
            $arr = array();
            foreach ($range1 as $list) {
                //echo $end_time.'~~~'.$list['start_time'].'~~~~'.$list['end_time']."<br />\r\n";
                if (strtotime($end_time) < strtotime($list['start_time']) || strtotime($start_time) > strtotime($list['end_time'])) {
                    continue;
                } else {
                    //新建活动为全店参与
                    //dd($params);
                    if( isset($params['range']) && $params['range']==2 ) {
                        return array('status' => false, 'msg' => '当前活动有效期内有其它正在进行的活动，不得重复创建!');
                    }
                    //新建活动为自选商品,保存活动id,再查商品id
                    $arr[] = $list['id'];
                }
            }
            $ids_lists = $arr;
            $goods_ids_res = DiscountGoods::whereIn('discount_id', $ids_lists)->lists('goods_id');
            
            //新建活动为自选商品,保存活动id,再查商品id
            foreach ($goods_ids_res as $value) {
                if (!empty($value) && !empty($goods_ids) && in_array($value, $goods_ids)) {
                    //echo $value;dd($goods_ids);
                    return array('status' => false, 'msg' => '当前活动有效期内有其它正在进行的活动，不得重复创建;');
                }
            }
            
            //----------日志 start----------
            $data_UserLog['merchant_id']=Auth::user()->merchant_id;
            $data_UserLog['user_id']=Auth::user()->id;
            $data_UserLog['type']=43;
            $data_UserLog['url']='merchant/merchant.json';
            $data_UserLog['content']=json_encode(array(
                '$goods_ids_res'=>json_encode($goods_ids_res),
            ));
            $data_UserLog['ip']=get_client_ip();
            $data_UserLog['created_time']=date('Y-m-d H:i:s');
            $data_UserLog['updated_time']=date('Y-m-d H:i:s');
            UserLog::insertGetId($data_UserLog);
            //----------日志 end----------
            
        }
        return array('status' => true, 'range' => 'all');
    }

    /**
     *  满就减活动商品列表
     */
    function getGoodsList($params)
    {
        $discount_id = isset($params['discount_id']) && $params['discount_id'] ? intval($params['discount_id']) : '';
        $start_time = isset($params['start_time']) && $params['start_time'] ? trim($params['start_time']) : '';
        $end_time = isset($params['end_time']) && $params['end_time'] ? trim($params['end_time']) : '';
        if (!$discount_id) {
            if (!$start_time || !$end_time) {
                return array('status' => false, 'msg' => '参数错误');
            }
        }
        $offset = isset($params['offset']) ? $params['offset'] : 0;
        $limit = isset($params['limit']) ? $params['limit'] : 10;
        $title = isset($params['title']) && $params['title'] ? trim($params['title']) : '';
        $tag_id = isset($params['tag_id']) && $params['tag_id'] ? intval($params['tag_id']) : '';
        $sort = isset($params['sort']) && $params['sort'] ? trim($params['sort']) : 'desc';
        $column = isset($params['column']) && $params['column'] ? trim($params['column']) : 'csale';
        $query = Goods::select('id', 'title', 'img', 'price', 'stock', 'csale')
            ->where(array('merchant_id' => $this->merchant_id, 'onsale' => 1, 'is_deleted' => 1));

        // 搜索标题
        if ($title) {
            $query->where('title', 'like', "%{$title}%");
        }
        // 搜索分组
        if ($tag_id) {
            $query->whereRaw('(select count(`goods_tag_rs`.`goods_id`) from `goods_tag_rs` where `goods_tag_rs`.`goods_id` = `goods`.`id` and `tag_id` = ?) >= 1', [$tag_id]);
        }
        $result['_count'] = $query->count();
        $info = $query->orderBy($column, $sort)
            ->skip($offset)
            ->take($limit)
            ->get();

        if (isset($discount_id)) {
            $query = DiscountGoods::select('discount_activity.start_time', 'discount_activity.end_time', 'discount_goods.id', 'discount_goods.goods_id', 'discount_goods.status');
            $query->where('discount_goods.discount_id', '!=', $discount_id);
            $discount_goods = $query->join('discount_activity', 'discount_activity.id', '=', 'discount_goods.discount_id')
                ->where(array('discount_activity.merchant_id' => $this->merchant_id, 'discount_activity.shop_id' => $this->shop_id, 'discount_activity.status' => 1, 'discount_goods.status' => 1))
                ->get();

            $ids = $editIds = array();

            foreach ($discount_goods as $val) {
                if ($val['status'] == 1) {
                    $editIds[] = $val['goods_id'];
                }

                if (strtotime($start_time) > strtotime($val['end_time']) || strtotime($end_time) < strtotime($val['start_time'])) {
                    continue;
                } else {
                    $ids[] = $val['goods_id'];
                }
            }

            if (!empty($ids)) {
                foreach ($info as &$item) {
                    if (in_array($item['id'], $ids)) {
                        $item['type'] = 1;
                    }
                }
            }

            if (!empty($editIds)) {
                foreach ($info as &$list) {
                    if (in_array($list['id'], $editIds)) {
                        $list['has_recode'] = 1;
                        $list['type'] = 1;
                    }
                }
            }
        }
        $result['data'] = $info;
        return $result;
    }


    /**
     *  订单状态
     */
    private function convertOrderStatus($status)
    {
        switch ($status) {
            case 0:
                $statusDesc = '未付款';
                break;
            case 10:
                $statusDesc = '自动取消';
                break;
            case 11:
                $statusDesc = '买家取消';
                break;
            case 12:
                $statusDesc = '商家取消';
                break;
            case 13:
                $statusDesc = '维权完成/已关闭';
                break;
            case 19:
                $statusDesc = '已下单';
                break;
            case 20:
                $statusDesc = '待付款';
                break;
            case 30:
                $statusDesc = '待发货';
                break;
            case 31:
                $statusDesc = '货到付款/待发货';
                break;
            case 32:
                $statusDesc = '上门自提/待提货';
                break;
            case 40:
                $statusDesc = '商家发货/买家待收货';
                break;
            case 50:
                $statusDesc = '已完成';
                break;
            default:
                $statusDesc = '';
                break;
        }
        return $statusDesc;
    }

    /**
     * 获取订单状态
     * date 2018-03-01
     * status 订单状态
     * type 类型：1-返回前端状态文字，2-返回前端状态id
     * data 订单数据
     */
    private function orderStatus($status,$type=1,$data='') {
		$order_type = isset($data['order_type']) ? $data['order_type'] : 0;
		$order_goods_type = isset($data['order_goods_type']) ? $data['order_goods_type'] : 0;
		
        $str = '';
		if($type==1) {
            switch($status) {
                case ORDER_AUTO_CANCELED:
                case ORDER_BUYERS_CANCELED:
                case ORDER_MERCHANT_CANCEL:
                case ORDER_REFUND_CANCEL:
                    $str = '已关闭';
                    break;
                case ORDER_SUBMIT:
                case ORDER_TOPAY:
                    $str = '待付款';
                    break;
                case ORDER_TOSEND:
                    $str = '待发货';
                    break;
                case ORDER_SUBMITTED:
                case ORDER_SEND:
                    if($order_type==ORDER_APPOINT) {	//预约订单
                        $str = '待核销';
                    } else {
                        $str = '待收货';
                    }
                    
                    if($order_goods_type == ORDER_GOODS_VIRTUAL){  //虚拟商品订单
                        $str = '待核销';
                    }
                    
                    break;
                case ORDER_FORPICKUP:
                    $str = '待自提';
                    break;
                case ORDER_SUCCESS:
                    $str = '已完成';
                    break;
                default:
                    break;
            }
        } else if($type==2) {
            switch($status) {
                case ORDER_SUBMIT:	//待下单
                    $str = 0;
                    break;
                case ORDER_TOPAY:	//待付款
                    $str = 1;
                    break;
                case ORDER_TOSEND:	//-待发货
                    $str = 2;
                    break;
                case ORDER_SUBMITTED:	//待收货
                case ORDER_FORPICKUP:
                case ORDER_SEND:
                    $str = 3;
                    break;
                case ORDER_SUCCESS:	//已完成
                    $str = 5;
                    break;
                case ORDER_AUTO_CANCELED:	//系统自动取消
                    $str = 6;
                    break;
                case ORDER_BUYERS_CANCELED:	//买家已取消
                    $str = 7;
                    break;
                case ORDER_MERCHANT_CANCEL:	//商家关闭订单
                    $str = 8;
                    break;
                case ORDER_REFUND_CANCEL:	//已关闭 ,所有维权申请处理完毕
                    $str = 9;
                    break;
                default:
                    $str = 6;
                    break;
            }
        }
        return $str;
    }

}
