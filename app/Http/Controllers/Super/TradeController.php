<?php

/*
 * 超级管理后台
 *
 */

namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\Member;
use App\Models\Trade;

class TradeController extends Controller {
    protected $request;
    protected $params;

    public function __construct(Request $request) {
        $this->request = $request;
        $this->params = $request->all();
    }

    /*
     * 获取交易记录
     */
    public function tradeList() {
        $offset = isset($this->params['offset']) ? $this->params['offset'] : 0;
        $limit = isset($this->params['limit']) ? $this->params['limit'] : 20;
        $wheres = [];
        if (!empty($this->params['merchant_id'])) {  //商户号
            $wheres[] = array('column' => 'merchant_id', 'value' => $this->params['merchant_id'], 'operator' => '=');
        }
        if (!empty($this->params['member_id'])) {  //会员号
            $wheres[] = array('column' => 'member_id', 'value' => $this->params['member_id'], 'operator' => '=');
        }
        if (!empty($this->params['order_id'])) { //订单号
            $wheres[] = array('column' => 'order_id', 'value' => $this->params['order_id'], 'operator' => '=');
        }
        if (!empty($this->params['order_sn'])) { //订单编号
            $wheres[] = array('column' => 'order_sn', 'value' => $this->params['order_sn'], 'operator' => '=');
        }
        if(isset($this->params['pay_status'])){ //付款状态
            $wheres[] = array('column' => 'pay_status', 'value' => $this->params['pay_status'], 'operator' => '=');
        }
        if(isset($this->params['trade_type'])){ //收入、退款
            $wheres[] = array('column' => 'trade_type', 'value' => $this->params['trade_type'], 'operator' => '=');
        }
        if(!empty($this->params['trade_sn'])){ //第三方交易号
            $wheres[] = array('column' => 'trade_sn', 'value' => $this->params['trade_sn'], 'operator' => '=');
        }
        if(!empty($this->params['startTime'])){ //开始时间
            $wheres[] = array('column' => 'created_time', 'value' => $this->params['startTime'], 'operator' => '>=');
        }
        if(!empty($this->params['endTime'])){ //结束时间
            $wheres[] = array('column' => 'created_time', 'value' => $this->params['endTime'], 'operator' => '<=');
        }
        $column = isset($this->params['column']) ? trim($this->params['column']) : 'created_time';
        if (!in_array($column, array('id', 'order_id', 'merchant_id', 'created_time'))) {
            $column = 'created_time';
        }
        $sort = isset($this->params['sort']) ? trim($this->params['sort']) : '';
        if (!in_array($sort, array('desc', 'asc'))) {
            $sort = 'desc';
        }

        $fields='id,merchant_id,member_id,order_id,order_sn,pay_status,pay_time,pay_type,order_type,payment_sn,amount,trade_type,trade_sn,created_time';
        $result=Trade::get_data_list($wheres,$fields,$offset,$limit);
        $count=Trade::get_data_count($wheres);

        $data['errcode'] = 0;
        $data['_count'] = $count;
        $data['data'] = $result;
        return Response :: json($data);
    }
}
