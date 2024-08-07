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
use App\Models\User;
use App\Models\CreditDetail;
use App\Models\Merchant;

class CreditController extends Controller {
    protected $request;
    protected $params;

    public function __construct(CreditDetail $CreditDetail,Request $request) {
        $this->request = $request;
        $this->params = $request->all();
        $this->model = $CreditDetail;
    }

    /*
     * 获取积分记录
     */
    public function creditList() {
        $param=$this->params;
        $offset = isset($param['offset']) && $param['offset'] ? $param['offset'] : 0;
        $limit = isset($param['limit']) && $param['limit'] ? $param['limit'] : 20;
        $query=$this->model->select('credit_detail.id','credit_detail.merchant_id','credit_detail.member_id','credit_detail.nickname','credit_detail.pre_credit','credit_detail.credit','credit_detail.final_credit','credit_detail.type','credit_detail.memo','credit_detail.created_time');
        $nickname = isset($this->params['nickname']) ? trim($this->params['nickname']) : '';

        //会员昵称
        if (!empty($nickname)) {
            $query->where('credit_detail.nickname','like', '%' . $nickname . '%');
        }
        //商户名称
        if(isset($param['company']) && !empty($param['company'])){
            $query->leftJoin("merchant","merchant.id","=","credit_detail.merchant_id");
            $query->where( 'merchant.company', "like", '%' . $param['company'] . '%');
        }
        //商户编号
        if(isset($param['merchantid']) && $param['merchantid']){
            $query->where('credit_detail.merchant_id','=',$param['merchantid']);
        }
        //会员编号
        if(isset($param['member_id']) && $param['member_id']){
            $query->where('credit_detail.member_id','=',$param['member_id']);
        }
        //会员账号
        if(isset($param['user_id']) && $param['user_id']){
            $member_id = $param['user_id'] - MEMBER_CONST;
            $query->where('credit_detail.member_id','=',$member_id);
        }
        //手机号
        if(isset($param['phone_num']) && $param['phone_num']){
            $mobiletmp = Member::where('mobile',$param['phone_num'])->pluck('id');
            $query->where('credit_detail.member_id','=',$mobiletmp);
        }
        //时间范围(开始时间)
        if(isset($param['startDate']) && $param['startDate']){
            $query->where('credit_detail.created_time','>=',date('Y-m-d 00:00:00',strtotime($param['startDate'])));
        }
        //时间范围(结束时间)
        if(isset($param['endDate']) && $param['endDate']){
            $query->where('credit_detail.created_time','<=',date('Y-m-d 00:00:00',strtotime($param['endDate'])));
        }
        //积分类型
        if(isset($param['creditStyle']) && $param['creditStyle']){
            if($param['creditStyle'] == 1){
                $query->where('credit_detail.credit','>',0);
            }else{
                $query->where('credit_detail.credit','<',0);
            }
        }
        //明细
        if(isset($param['detail']) && $param['detail']) {
            $query->where('credit_detail.memo','like','%'. $param['detail'] .'%');
        }

        $count = $query->count();
        $query->skip($offset);
        $query->take($limit);
        $result = $query->get();
        if(!empty($result)){
            foreach($result as $key=>$item){
                $result[$key]['user_id'] = $item['member_id'] + MEMBER_CONST;
                $result[$key]['mobile'] = Member::where('id',$item['member_id'])->pluck('mobile');
                //商户名称字段
                $merchant=Merchant::get_data_by_id($item["merchant_id"]);
                if($merchant){
                    $result[$key]['company']=$merchant["company"];
                }
            }
        }
        $data['errcode'] = 0;
        $data['_count'] = $count;
        $data['data'] = $result;
        return Response :: json($data);
    }
}
