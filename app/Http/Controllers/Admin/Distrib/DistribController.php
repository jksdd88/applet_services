<?php

namespace App\Http\Controllers\Admin\Distrib;
use App\Models\DistribDaily;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\DistribOrder;
use App\Models\DistribOrderGoods;
use App\Models\DistribOrderDetail;
use App\Models\OrderInfo;
use App\Models\OrderGoods;
use App\Models\OrderRefund;
use App\Utils\CommonApi;
use App\Models\Goods;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\WeixinQrcode;
use App\Models\DistribPartner;
use App\Models\Member;
use App\Models\WeixinInfo;
use App\Models\DistribCheckLog;
use App\Models\DistribBuyerRelation;
use App\Models\DistribSetting;
use App\Models\DataExportTask;
use Illuminate\Support\Facades\DB;
use App\Jobs\WeixinMsgJob;
use App\Jobs\DataExportTask as JobDataExportTask;


class DistribController extends Controller
{
    private $merchant_id;

    protected $initiate_status = array(
        DISTRIB_AWAIT   => '待处理',
        DISTRIB_NOT  => '不参与分佣',
        DISTRIB_AWAIT_SETTLED  => '已处理，待结算',
        DISTRIB_FINISH  => '已结算',
        DISTRIB_REFUND  => '已退单'
    );

    function __construct(Request $request)
    {
        $this->params = $request->all();        
        if (app()->isLocal()) {
            $this->merchant_id = 6;
            // $this->merchant_id = 1;
            $this->user_id = 2;

        } else {
            $this->merchant_id = Auth::user()->merchant_id;
            $this->user_id = Auth::user()->id;
        }
    }

    public function getReview()
    {
        $data = [
            'sum_order_amount'      =>0.00,//累计推客销售额
            'sum_total_comission'   =>0.00,//累计佣金
            'order_count'           =>0,//推客总订单数
            'partner_count'         =>0,//总推客数
        ];
        for($i=6;$i>=0;$i--){
            $dis_num = (date('H') <2) ? $i+2 : $i+1; //凌晨两点数据未更新 展示前2-8天数据
            $short_data = date('Y-m-d',strtotime('-'.$dis_num.' day'));
            $empty_array = ['date'=>$short_data,'number'=>0];
            $data['seven_orders'][]=$empty_array;
            $data['seven_parter'][]=$empty_array;
            $data['seven_amount'][]=$empty_array;
            $data['seven_comission'][]=$empty_array;


        }
        //查询当前7天数据
        $review_list = DistribDaily::where('merchant_id',$this->merchant_id)
            ->limit(7)
            ->orderBy('id','desc')
            ->get();
        if(count($review_list)>0){
            foreach($review_list->toArray() as $k=>$v){
                if($k == 0){
                    $data['sum_order_amount'] = $v['order_money_sum'];//累计推客销售额
                    $data['sum_total_comission'] = $v['comission_sum'];//累计佣金
                    $data['order_count'] = $v['order_sum'];//推客总订单数
                    $data['partner_count'] = $v['distrib_sum'];//总推客数
                }
                $data['seven_orders'][6-$k]['number'] = $v['order_day'];//订单数
                $data['seven_parter'][6-$k]['number'] = $v['distrib_day'];//推客数
                $data['seven_amount'][6-$k]['number'] = $v['order_money_day'];//订单额度
                $data['seven_comission'][6-$k]['number'] = $v['comission_day'];//订单佣金
            }

        }
        return ['errcode' => 0, 'errmsg' => '获取数据成功', 'data' => $data];
    }
    /**
    *7天数据处理
    *@author denghongmei@dodoca.com
    *@param array $params 源数据
    *@return array
    */
    public function sevendata($params){
        for($i=7;$i>0;$i--){
            $date[] = ['date' => date('Y-m-d', strtotime("-{$i} days")),'number'=> 0];
        }
        foreach($date as $k=>$v){
            foreach($params as $va){
                if($va['date'] == $v['date']){
                    $date[$k]['number'] = $va['count'];
                }
            }
        }
        return $date;
    }

    /**
    *推广订单
    *@author denghongmei@dodoca.com
    *@param 
    *@return array
    */
    public function getOrders(Request $request){
        set_time_limit(0); //防止导出数据量大时程序超时
        $params = $request->all();
        $offset = isset($params['offset']) && $params['offset'] ? $params['offset'] : 0;
        $limit = isset($params['limit']) && $params['limit'] ? $params['limit'] : 10;
        $member_id = isset($params['member_id']) && $params['member_id'] ? trim($params['member_id']) : '';
        $status = isset($params['status']) && is_numeric($params['status']) ? intval($params['status']) : 0;
        $order_sn = isset($params['order_sn']) && $params['order_sn'] ? $params['order_sn'] : '';
        $mobile = isset($params['mobile']) && $params['mobile'] ? $params['mobile'] : '';
        $name = isset($params['name']) && $params['name'] ? $params['name'] : '';
        $settled_time = isset($params['settled_time']) && $params['settled_time'] ? $params['settled_time'] : '';
        $appid = isset($params['appid']) && $params['appid'] ? $params['appid'] : '';
        $sort = isset($params['sort']) && $params['sort'] ? $params['sort'] : '';

        //小程序列表
        $arr_weixininfo = array();
        $rs_weixininfo = WeixinInfo::list_data('merchant_id',$this->merchant_id,1,0);
        if(!empty($rs_weixininfo)){
            foreach ($rs_weixininfo as $key=>$val){
                if($val['appid']){
                    $arr_weixininfo[$val['appid']] = $val['nick_name'];
                }
            }
        }

        $query = DistribOrder::select('*')->where('merchant_id','=',$this->merchant_id);

        //根据订单号查询
        if($order_sn){
            $query = $query->where('order_sn','=',$order_sn);
        }

        //根据订单状态查询
        if($status){
            $query = $query->where('status','=',$status-1);
        }
        $ids=array();
        //根据会员手机查询
        if($mobile){
            $member=DistribPartner::select('member_id','name')
                ->where('merchant_id','=',$this->merchant_id)
                ->where('mobile','=',$mobile)
                ->get()->toArray();
            if($member){
                foreach ($member as $key=>$val){
                    $ids[]=$val['member_id'];
                }
            }
        }

        //根据会员名称查询
        if($name){
            $member=DistribPartner::select('member_id','name')
                ->where('merchant_id','=',$this->merchant_id)
                ->where('name','=',$name)
                ->get()->toArray();
            if($member){
                foreach ($member as $key=>$val){
                    $ids[]=$val['member_id'];
                }
            }
        }

        if($mobile || $name){
            $query = $query->whereIn('member_id',$ids);
        }

        //根据会员账号查询
        if($member_id){
            $id = intval($member_id) - MEMBER_CONST;
            $query = $query->where('member_id','=',$id);
        }

        //根据结算时间查询
        if($settled_time){
            $tempFinishedTime = trim($settled_time);
            $where["settled_time"] = explode("/",$tempFinishedTime);

            $query = $query->where("settled_time",">",$where["settled_time"][0]);
            $query = $query->where("settled_time","<",$where["settled_time"][1]);
        }
        //根据来源小程序查询
        if($appid){
            $query = $query->where('appid','=',$appid);
        }

        //导出订单
        $export = isset($params['export']) ? trim($params['export']) : 0;
        if($export == 1){
            if($sort=='asc'){
                $query->orderBy("total_comission", "asc");
            }elseif ($sort=='desc'){
                $query->orderBy("total_comission", "desc");
            }else{
                $query->orderBy(".created_time", "DESC");
            }

            $filename = '订单列表'.date('Ymd',time()).'.csv';
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="'.$filename.'"');
            header('Cache-Control: max-age=0');

            //打开PHP文件句柄，php://output表示直接输出到浏览器
            $fp = fopen('php://output', 'a');
            //表格列表
            $head = ['创建时间', '买家', '订单信息', '实付金额', '佣金', '来源推客', '推客手机号', '推客会员账号', '结算时间', '来源小程序', '订单状态'];
            foreach($head as $key => $val){
                $head[$key] = @iconv('UTF-8', 'GBK//IGNORE', $val);
            }
            fputcsv($fp, $head);

            $query->chunk(100, function($list) use ($fp, $arr_weixininfo) {
                foreach($list as $value){
                    $member  = Member::get_data_by_id($value['order_member_id'], $this->merchant_id);
                    $partner = DistribPartner::get_data_by_memberid($value['member_id'], $this->merchant_id);
                    $row = [
                        'created_time'    => $value['created_time'], //创建时间
                        'nickname'        => isset($member['name']) ? filterEmoji($member['name']) : '', //买家
                        'order_sn'        => $value['order_sn'], //订单信息
                        'order_amount'    => $value['order_amount'], //实付金额
                        'total_comission' => $value['total_comission'], //佣金
                        'name'            => isset($partner['name']) ? filterEmoji($partner['name']) : '', //来源推客
                        'mobile'          => isset($partner['mobile']) ? $partner['mobile'] : '', //推客手机号
                        'account'         => $value['member_id'] + MEMBER_CONST, //推客会员账号
                        'settled_time'    => $value['settled_time'] == '0000-00-00 00:00:00' ? '-' : $value['settled_time'], //结算时间
                        'weapp'           => isset($arr_weixininfo[$value['appid']]) ? $arr_weixininfo[$value['appid']] : '', //来源小程序
                        'order_status'    => $this->initiate_status[$value['status']] //订单状态
                    ];
                    $column = array();
                    foreach($row as $k => $v){
                        $column[$k] = @iconv('UTF-8', 'GBK//IGNORE', $v);
                    }
                    fputcsv($fp, $column);
                }
            });
            exit;
        }

        //获取列表
        $count = $query->count();
        if($sort=='asc'){
            $query->orderBy("total_comission", "asc");
        }elseif ($sort=='desc'){
            $query->orderBy("total_comission", "desc");
        }else{
            $query->orderBy("created_time", "DESC");
        }
        $data = $query->skip($offset)->take($limit)->get()->toArray();
        if (count($data) > 0) {
            foreach ($data as $key => $value) {
                $orderinfo=OrderInfo::select('id','nickname')
                    ->where('id','=',$value['order_id'])
                    ->first();
                $partner=DistribPartner::select('id','mobile','name')
                    ->where('member_id','=',$value['member_id'])
                    ->first();
                $data[$key]['mobile'] = isset($partner['mobile'])?$partner['mobile']:'';
                $data[$key]['name'] = isset($partner['name'])?$partner['name']:'';
                $data[$key]['nickname'] = isset($orderinfo['nickname'])?$orderinfo['nickname']:'';
                $data[$key]['account'] = MEMBER_CONST + $value['member_id']; //会员账号等于会员开好常量+会员id
                $data[$key]['status_name'] = $this->initiate_status[$value['status']];
                $data[$key]['xcx_name'] = isset($arr_weixininfo[$value['appid']])?$arr_weixininfo[$value['appid']]:'';
                if($value['settled_time']=='0000-00-00 00:00:00'){
                    $data[$key]['settled_time'] = '-';
                }
            }
        }


        $res['errcode'] = 0;
        $res['errmsg'] = '查询成功';
        $res['_count'] = $count;
        $res['list'] = $data;
        $res['xcx_list'] = $arr_weixininfo;
        $res['order_status'] = $this->initiate_status;

        return Response::json($res);


    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 查询订单详情
     */
    public function getGoods(Request $request){
        $params = $request->all();
        $order_id = isset($params['order_id']) && is_numeric($params['order_id']) ? intval($params['order_id']) : 0;
        if($order_id){
            $list = DistribOrderGoods::get_list_by_orderid($order_id,$this->merchant_id);
            if($list){
                foreach ($list as $key=>$val){
                    $refund_info_num = 0;
                    if($val["refund_info"]){
                        $refund_info = json_decode($val["refund_info"],true);
                        if(!empty($refund_info)){
                            foreach ($refund_info as $k=>$v){
                                $refund_info_num += array_sum($v);
                            }
                        }
                    }
                    $list[$key]['refund_info_num'] = $refund_info_num;
                }
            }
        }

        $res['errcode'] = 0;

        $res['list'] = $list;

        return Response::json($res);

    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 查看佣金详情
     */
    public function getOrderDetail(Request $request){
        $params = $request->all();
        $order_id = isset($params['order_id']) && is_numeric($params['order_id']) ? intval($params['order_id']) : 0;
        if($order_id){
            $list = DistribOrderDetail::get_list_by_orderid($order_id,$this->merchant_id);
            if($list){
                foreach ($list as $key=>$val){
                    $member = Member::get_data_by_id($val['member_id'],$this->merchant_id);
                    $list[$key]['name'] = $member['name'];
                    $list[$key]['mobile'] = $member['mobile'];
                }
            }
        }

        $res['errcode'] = 0;

        $res['list'] = $list;

        return Response::json($res);
    }


    /**
    *推客列表
    *@author renruiqi@dodoca.com
    *@param $page           int 页码 (可选,默认为1)
    *@param $pagesize       int 显示条数(可选,默认为10)
    *@param $order_colume   str 可选 排序字段
    *@param $order_type     str 可选 排序值 (asc/desc)
    *@param $appid      int 可选 来源小程序
    *@param $member_id      int 可选 会员账号
    *@param $mobile         str 可选 联系方式
    *@param $name           str 可选 推客姓名
    *@param $status         int 可选 推客状态 
    *@param $referrer_name  str 可选 推荐人姓名
    *@param $referrer_id    str 可选 推荐人id
    *@param $start_time     str 可选 注册时间段搜索->开始时间
    *@param $end_time       str 可选 注册时间段搜索->结束时间
    *@param $distrib_list   int 可选 推客列表值为1 (审核列表不传)
    *@param $is_down        int 可选 是否下载列表 (1下载推客列表 2下载审核列表)
    */
    public function getList(Request $request)
    {
        set_time_limit(0); //防止导出数据量大时程序超时
        $page          = $request->input('page', 1);
        $pagesize      = $request->input('pagesize', 10);
        $wxinfo_id     = $request->input('wxinfo_id', 0);
        $member_id     = $request->input('member_id', '');
        $mobile        = $request->input('mobile', '');
        $name          = $request->input('name', '');
        $status        = $request->input('status', 0);
        $referrer_id   = $request->input('referrer_id', '');
        $referrer_name = $request->input('referrer_name', '');
        $nick_name     = $request->input('nick_name', '');
        $start_time    = $request->input('start_time', '');
        $end_time      = $request->input('end_time', '');
        $distrib_list  = $request->input('distrib_list', 0);
        $is_down       = $request->input('is_down', 0);

        $query = DistribPartner::query();
        $query->where('merchant_id', $this->merchant_id);
        //加入时间 来源小程序 会员账号 推客姓名 手机号 推荐人 状态
        //来源小程序
        if($wxinfo_id){
            $query->where('wxinfo_id', $wxinfo_id);
        }
         //会员账号
        if($member_id){
            $query->where('member_id', $member_id - MEMBER_CONST);
        }
         //手机号
        if($mobile){
            $query->where('mobile', 'like', $mobile.'%');
        }
        //推客姓名
        if($name){
            $query->where('name', 'like', '%'.$name.'%');
        }
        //状态
        if($status){
            $query->where('status', $status);
        }
        //推荐人账号
        if($referrer_id){  
            $query->where('parent_member_id', $referrer_id - MEMBER_CONST);
        }
        //推荐人姓名
        if($referrer_name){
            //从会员表中查询
            $parent_member_id = Member::where('merchant_id', $this->merchant_id)->where('name', 'like', '%'.$referrer_name.'%')->lists('id');
            if($parent_member_id){
                $query->whereIn('parent_member_id', $parent_member_id);
            }
        }
        //推客昵称
        if($nick_name){
            //从会员表中查询
            $distrib_member_id = Member::where('merchant_id', $this->merchant_id)->where('name', 'like', '%'.$nick_name.'%')->lists('id');
            if($distrib_member_id){
                $query->whereIn('member_id', $distrib_member_id);
            }
        }
        //开始时间
        if($start_time){
            $query->where('check_time', '>=', $start_time);
        }
        //结束时间
        if($end_time){
            $query->where('check_time', '<=', $end_time.' 23:59:59');
        }
        //去除审核失败/未审核的推客
        if($distrib_list && $distrib_list ==1){
            $query->whereIn('status', [1, 2]);//推客列表 正常+禁用
        }

        $query->orderBy('id', 'desc');

        //是否是下载数据(下载表格无需分页信息)
        if($is_down){
            //下载
            $filename = $is_down == 1 ? '推客列表'.date('Y-m-d').'.csv' : '推客审核列表'.date('Y-m-d').'.csv';
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="'.$filename.'"');
            header('Cache-Control: max-age=0');
            //打开PHP文件句柄，php://output表示直接输出到浏览器
            $fp = fopen('php://output', 'a');
            //表格列表
            if($is_down == 1){
                //$head = ['推客信息', '会员账号', '未结算佣金', '已结算佣金', '推广订单总额', '推荐人(会员号)', '推客下级人数', '佣金下级人数', '姓名', '手机号', '加入时间', '来源小程序', '状态'];
                $head = ['推客信息', '会员账号', '未结算佣金', '已结算佣金', '推荐人(会员号)', '推客下级人数', '佣金下级人数', '姓名', '手机号', '加入时间', '来源小程序', '状态'];
            }else{
                $head = ['申请推客', '会员账号', '姓名', '手机号', '推荐人(会员号)', '来源小程序', '申请时间', '处理时间', '审核状态'];
            }
            
            foreach($head as $key => $val){
                $head[$key] = @iconv('UTF-8', 'GBK//IGNORE', $val);
            }
            fputcsv($fp, $head);

            $query->chunk(500, function($list) use ($fp, $is_down) {
                $status_arr = $is_down == 1 ? ['待处理','正常','禁用','已拒绝'] : ['待处理','已通过','已通过','已拒绝'];
                foreach($list as $value){
                    //推客昵称
                    $member = Member::get_data_by_id($value['member_id'], $this->merchant_id);
                    //上级推客信息
                    $parent_info = '';
                    if($value['parent_member_id']){
                        $higher_member = Member::get_data_by_id($value['parent_member_id'], $this->merchant_id);
                        $account = $value['parent_member_id'] + MEMBER_CONST;
                        $parent_info   = filterEmoji($higher_member['name'] . '(' .$account .')');
                    }
                    //小程序信息
                    $wxinfo = WeixinInfo::check_one_id($value['wxinfo_id']);
                    
                    if($is_down == 1){
                        //推广订单额
                        //$order_amount = DistribOrder::where('member_id', $value['member_id'])->sum('order_amount');
                        //下级人数
                        $team_size = DistribPartner::where('merchant_id', $this->merchant_id)->where('parent_member_id', $value['member_id'])->whereIn('status', [1, 2])->count();
                        //佣金下级人数
                        $commission_num = DistribBuyerRelation::where('merchant_id', $this->merchant_id)->where('distrib_member_id', $value['member_id'])->count();
                        $row = [
                            'nick_name'        => filterEmoji($member['name']), //推客呢称
                            'account'          => $value['member_id'] + MEMBER_CONST, //推客会员帐号
                            'expect_comission' => $value['expect_comission'], //未结算佣金
                            'total_comission'  => $value['total_comission'], //已结算佣金
                            //'order_amount'     => $order_amount, //推广订单总额
                            'parent_info'      => $parent_info, //推荐人(会员号)
                            'team_size'        => $team_size ? $team_size : 0, //下级人数
                            'commission_num'   => $commission_num ? $commission_num : 0, //佣金下级人数
                            'name'             => $value['name'], //姓名
                            'mobile'           => $value['mobile'], //手机号
                            'check_time'       => $value['check_time'], //加入时间
                            'weapp'            => $wxinfo['nick_name'], //来源小程序
                            'status'           => $status_arr[$value['status']] //推客状态
                        ];
                    }else{
                        $row = [
                            'nick_name'    => filterEmoji($member['name']), //推客呢称
                            'account'      => $value['member_id'] + MEMBER_CONST, //推客会员帐号
                            'name'         => $value['name'], //姓名
                            'mobile'       => $value['mobile'], //手机号
                            'parent_info'  => $parent_info, //推荐人(会员号)
                            'weapp'        => $wxinfo['nick_name'], //来源小程序
                            'created_time' => $value['created_time'], //申请时间
                            'check_time'   => $value['check_time'], //处理时间
                            'status'       => $status_arr[$value['status']] //审核状态
                        ];
                    }
                    
                    $column = [];
                    foreach($row as $k => $v){
                        $column[$k] = @iconv('UTF-8', 'GBK//IGNORE', $v);
                    }

                    fputcsv($fp, $column);
                }

                ob_flush();
                flush();
            });

            fclose($fp);
            exit;
        }

        $data['count']  = $query->count();
        $data['data']   = $query->offset(($page-1)*$pagesize)->limit($pagesize)->get();//列表展示数据
        
        if($data['count']>0){
            // $data
            $data['data'] = $data['data']->toArray();
            foreach($data['data'] as &$v){
                //微信昵称
                $self_info      = Member::get_data_by_id($v['member_id'], $this->merchant_id);
                $v['nick_name'] =  count($self_info)>0 ? $self_info['name'] : '--';
                //来源小程序
                $wx_info       = WeixinInfo::check_one_id($v['wxinfo_id']);
                $v['xcx_name'] =  count($wx_info)>0 ? $wx_info['nick_name'] : '--';
                //推荐人姓名
                if($v['parent_member_id'] == 0){
                    $v['parent_name']      =   '--';
                    $v['parent_member_id'] = '--';
                    $v['parent_info']      = '--';
                }else{
                    $referrer_info         = Member::get_data_by_id($v['parent_member_id'], $this->merchant_id);
                    $v['parent_name']      =  count($referrer_info)>0 ? $referrer_info['name'] : '--';
                    $v['parent_member_id'] =  count($referrer_info)>0 ? $referrer_info['id'] + MEMBER_CONST : '--';
                    $v['parent_info']      = $v['parent_name'].'('.$v['parent_member_id'].')';
                }

                //审核时间 
                $v['check_time'] = strtotime($v['check_time']) ? $v['check_time'] : '--';//未审核'--'代替

                //推客手机号
                if(!$v['mobile']) $v['mobile'] = '--';
                
                if($distrib_list && $distrib_list == 1){
                    //推广订单总额
                    $order_info = DistribOrder::where('member_id', $v['member_id'])->sum('order_amount');
                    $v['real_amount'] = $order_info ? $order_info : '0.00';

                    //下级人数
                    $team_size = DistribPartner::where('merchant_id', $this->merchant_id)->where('parent_member_id', $v['member_id'])->whereIn('status', [1, 2])->count();
                    $v['team_size'] = $team_size ? $team_size : 0;

                    //佣金下级人数
                    $commission_num = DistribBuyerRelation::where('merchant_id', $this->merchant_id)->where('distrib_member_id', $v['member_id'])->count();
                    $v['commission_num'] = $commission_num;
                }
                
                //会员账号
                $v['member_id'] += MEMBER_CONST;
            }
        }

        $data['errcode'] = 0;
        $data['errmsg'] = '查询成功';
        return Response::json($data);

    }

    //佣金下级人员列表
    public function getCommissionMemberList(Request $request)
    {
        $page              = $request->input('page', 1);
        $pagesize          = $request->input('pagesize', 10);
        $name              = $request->name;
        $mobile            = $request->mobile;
        $member_id         = $request->member_id;
        $distrib_member_id = $request->distrib_member_id;
        $offset            = ($page - 1) * $pagesize;

        if($distrib_member_id){
            $query = \DB::table('distrib_buyer_relation');

            $query->select('distrib_buyer_relation.merchant_id', 'distrib_buyer_relation.member_id', 'distrib_buyer_relation.distrib_member_id', 'distrib_buyer_relation.updated_time', 'member.name', 'member.mobile', 'member.latest_buy_time');

            $query->leftJoin('member', 'distrib_buyer_relation.member_id', '=', 'member.id');
            $query->where('distrib_buyer_relation.distrib_member_id', $distrib_member_id - MEMBER_CONST);
            if($name){
                $query->where('member.name', 'like', '%'.$name.'%');
            }
            if($member_id){
                $query->where('distrib_buyer_relation.member_id', $member_id - MEMBER_CONST);
            }
            if($mobile){
                $query->where('member.mobile', $mobile);
            }
            $count = $query->count();
            $list  = $query->skip($offset)->take($pagesize)->get();

            foreach ($list as &$val){
                $order_count = DistribOrder::where('order_member_id', $val->member_id)->where('member_id', $val->distrib_member_id)->count();
                $val->order_count = $order_count;
                $val->member_id   = $val->member_id + MEMBER_CONST;
            }

            return ['errcode' => 0, 'errmsg' => '查询成功', 'count' => $count, 'list' => $list];
        }else{
            return ['errcode' => 99001, 'errmsg' => '上级不存在'];
        }   
    }

    /**
    *未成为推客的列表
    *@param $page           int 页码 (可选,默认为1)
    *@param $pagesize       int 显示条数(可选,默认为10)
    *@param $member_id      int 可选 会员账号
    *@param $name           str 可选 会员昵称
    *@author renruiqi@dodoca.com
    */
    public function memberList(Request $request)
    {
        $sele_data = $request->all();
        $page = (empty($sele_data['page']) || (int)$sele_data['page'] <1) ? 1 : (int)$sele_data['page'];
        $pagesize = (empty($sele_data['pagesize']) || (int)$sele_data['pagesize'] <1) ? 100 : (int)$sele_data['pagesize'];
        $name = (empty($sele_data['name'])) ? '':trim($sele_data['name']);
        $member_id = (empty($sele_data['member_id']) || (int)$sele_data['member_id'] <1) ? false : (int)$sele_data['member_id'];
        // $query = Member::select('member.id','member.name','p.status')
        //             ->leftJoin('distrib_partner as p','member.id','=','p.member_id')
        //             ->havingRaw(" (`p`.`status` is null  or `p`.`status` not in (1,2) )")//排除已通过,已禁用的推客
        //             ->where('member.merchant_id',$this->merchant_id); 
        $query = Member::select('member.id','member.name','p.status')
                    ->leftJoin('distrib_partner as p','member.id','=','p.member_id')
                    ->where(function($p_query){
                        $p_query->whereNull('status')
                            ->orWhereNotIn('p.status',[1,2]);
                    })
                    ->where('member.merchant_id',$this->merchant_id);

        if($name){
            $query->where('member.name',$name);
        }
        if($member_id){
            $query->where('member.id',$member_id-MEMBER_CONST);
        }

        // dd($query->toSql());
        $data['count'] = $query->count();
        $data['data'] = [];
        if($data['count']>0){
            $data['data'] = $query->forPage($page,$pagesize)->latest('id')->get();
            foreach($data['data'] as &$v){//会员账号
                $v->id += MEMBER_CONST;
            }
        }
        $data['errcode'] = 0;
        $data['errmsg'] = '查询成功';
        return Response::json($data);
    }

    /**
    *用于转化特殊字符
    */
    public  function replaceStr( $str )
    {
        $str = (string)$str;
        $str= @iconv("utf-8","gbk",$str);
        $str = @iconv("gbk","utf-8",$str);
        return $str;
    }


    /**
    *开启/禁用推客
    *@author renruiqi@dodoca.com
    *@param $member_id  int 必选 会员id
    *@param $status   int 必选 1开启 2禁用
    */
    public function checkStatus(Request $request)
    {
      //参数验证
      $data['member_id'] = intval($request->input('member_id',0));
      $data['status'] = $request->input('status',0);
      if($data['member_id']>0 && in_array($data['status'],[1,2])){
            $data['member_id'] -= MEMBER_CONST;
            DistribPartner::update_data($data['member_id'] ,$this->merchant_id ,$data);
            return Response::json(['errcode'=>0,'errmsg'=>'操作成功']);
      }else{
          return Response::json(['errcode'=>99001,'errmsg'=>'参数非法']);
      }
    }

    /**
    *审核推客
    *@author renruiqi@dodoca.com
    *@param $member_id  int 必选 会员id
    *@param $status   int 必选 1通过 3审核失败
    */
    public function checkDistrib(Request $request)
    {
      //参数验证
      $date_str = Carbon::now();
      $data['member_id'] = intval($request->input('member_id',0));
      $data['status'] = $request->input('status',0);
      $data['check_time'] = $date_str;
      if($data['member_id']>0 && in_array($data['status'],[1,3])){
            $data['member_id'] -= MEMBER_CONST;
              //修改推客表
                $status = $data['status'] == 1 ? 1 : 3 ;
                $info = DistribPartner::get_data_by_memberid($data['member_id'] , $this->merchant_id);
                //查询推客信息
                if(count($info) <1 || $info['status']!=0 ) return Response::json(['errcode'=>'70001','errmsg'=>'参数非法']);

                if($status == 1){
                    //插入推客关系
                    $setting = DistribSetting::get_data_by_merchant_id($this->merchant_id);
                    $relation = DistribBuyerRelation::get_data_by_memberid($data['member_id'] , $this->merchant_id);
                    if(!$relation){
                        $relation_data = [
                            'merchant_id'       => $this->merchant_id,
                            'member_id'         => $data['member_id'],
                            'distrib_member_id' => $data['member_id']   //自已已经是推客了，关系就是与自已绑定
                        ];

                        DistribBuyerRelation::insert_data($relation_data);
                    }elseif($relation && $setting['buyer_period'] == 0) {
                        DistribBuyerRelation::update_data($data['member_id'], $this->merchant_id, ['distrib_member_id' => $data['member_id']]);
                    }
                }

                if($info['parent_member_id'] != 0 && $status == 1){//推客团队人数加1 
                    DistribPartner::increment_data($info['parent_member_id'] ,$this->merchant_id ,'team_size' ,1);
                    //通知上级有新成员加入
                    $this->dispatch(new WeixinMsgJob([
                        'type'        => 'distrib_partner_join', 
                        'member_id'   => $data['member_id'],
                        'merchant_id' => $this->merchant_id
                    ]));
                }
                
                $check_data = [
                    'member_id'       =>$data['member_id'],
                    'merchant_id'     =>$this->merchant_id,
                    'mobile'          =>$info['mobile'],
                    'collect_fields'  =>empty($info['collect_fields']) ? ' ' : $info['collect_fields'],
                    'apply_time'      =>$info['created_time'],
                    'check_time'      =>$date_str,
                    'apply_time'      =>$info['created_time'],
                    'check_uid'       =>$this->user_id,
                    'check_result'    =>$status,
                    'check_type'      =>2, //总部审核
                ];
                //添加验证表
                DistribPartner::update_data($data['member_id'] ,$this->merchant_id ,$data);
                DistribCheckLog::insert_data($check_data);
                return Response::json(['errcode'=>'0','errmsg'=>'操作成功']);
            
      }else{
          return Response::json(['errcode'=>99001,'errmsg'=>'参数非法']);
      }
    }





    /**
    *将会员设置成推客
    *@param  mid_list array  必选 会员id数组
    */
    public function becomeDistrib(Request $request)
    {   
        $date_str = date("Y-m-d H:i:s");
        //数组判定
        $mid_list = $request->input('mid_list',[]);
        if(!is_array($mid_list) || count($mid_list)<1 ){
            return Response::json(['errcode'=>99001,'errmsg'=>'参数非法']);
        }
        $nums =0;//成功条目数
        foreach($mid_list as $member_id){
            $member_id -= MEMBER_CONST;
            // if(!app()->isLocal()) $member_id -= MEMBER_CONST;
            //不是会员 跳出
            $member_info = Member::get_data_by_id($member_id , $this->merchant_id);
            if(!$member_info) continue;
            //加入推客表
            $data = [
                'parent_member_id'  => 0,
                'merchant_id'       => $this->merchant_id,   
                'member_id'         => $member_id,   
                'status'            => 1,   
                'name'              => $member_info['name'],   
                'mobile'            => $member_info['mobile'],   
                'collect_fields'    => ' ',   
                'check_time'        => $date_str,
                'total_comission'   => 0,//累计结算佣金总额
                'expect_comission'  => 0,//未结算佣金
            ];
            //查询推客信息
            $is_distrib = DistribPartner::get_data_by_memberid($member_id , $this->merchant_id);
            if($is_distrib){
                if(in_array($is_distrib['status'],[0,3])){ //若是未审核/被拒绝的推客,则将状态改为1 (审核成功)
                    $res = DistribPartner::update_data($member_id ,$this->merchant_id ,['status'=>1,'check_time'=>$date_str]);
                    if($res) $nums++; 
                }else{
                    continue;
                }
            // dump( $is_distrib);
            }else{//信息不存在则加入推客表
                $res = DistribPartner::insert_data($data);//加入推客表
            }
            if($res){
                $nums++; //成功 则成功数加一
            }else{
                continue; //失败则进行下一个
            }

            //插入推客关系
            $relation = DistribBuyerRelation::get_data_by_memberid($member_id, $this->merchant_id);
            if(!$relation){
                $relation_data = [
                    'merchant_id'       => $this->merchant_id,
                    'member_id'         => $member_id,
                    'distrib_member_id' => $member_id   //自已已经是推客了，关系就是与自已绑定
                ];

                DistribBuyerRelation::insert_data($relation_data);
            }else{
                DistribBuyerRelation::update_data($member_id, $this->merchant_id, ['distrib_member_id' => $member_id]);
            }
            //加入验证表
            $check_data = [
                'member_id'         => $member_id,
                'merchant_id'       => $this->merchant_id,
                'mobile'            => $member_info['mobile'],
                'collect_fields'    => ' ',
                'apply_time'        => $date_str,
                'check_time'        => $date_str,
                'check_uid'         => $this->user_id,//自动审核
                'check_type'        => 2,//总部审核
                'check_result'      => 1,//1通过2拒绝
            ];
            DistribCheckLog::insert_data($check_data);
        }
        if($nums > 0){
            return Response::json(['errcode'=>0,'errmsg'=>'操作成功']);
        }
        return Response::json(['errcode'=>200003,'errmsg'=>'操作失败,请勿重复设置']);
    }

    //过滤微信昵称特殊字符
    public function filterEmoji($emojiStr){
        $emojiStr = preg_replace_callback('/./u',function(array $match){
            return strlen($match[0]) >= 4 ? '' : $match[0];
        },$emojiStr);
        return $emojiStr;
    }

    //设置推客上级
    public function setParent(Request $request)
    {
        $type             = $request->type; //类型 1、设为总店推客 2、指定上级
        $parent_member_id = $request->parent_member_id; //上级推客会员帐号
        $member_id        = $request->member_id; //需要设置的推客ID

        if(!$member_id){
            return ['errcode' => 99001, 'errmsg' => '推客ID不存在'];
        }

        if($type == 1){
            $member_id = $member_id - MEMBER_CONST;
            if(DistribPartner::update_data($member_id, $this->merchant_id, ['parent_member_id' => 0])){
                return ['errcode' => 0, 'errmsg' => '操作成功'];
            }
        }

        if($type == 2){
            if(!$parent_member_id){
                return ['errcode' => 99001, 'errmsg' => '上级会员帐号不正确'];
            }

            $parent_member_id = $parent_member_id - MEMBER_CONST;
            $parent = DistribPartner::get_data_by_memberid($parent_member_id, $this->merchant_id);

            if(!$parent){
                return ['errcode' => 99001, 'errmsg' => '上级推客不存在'];
            }

            $member_id = $member_id - MEMBER_CONST;

            if($parent['parent_member_id'] == $member_id){
                return ['errcode' => 99001, 'errmsg' => '选中的推客为自已的下级'];
            }

            if($parent_member_id == $member_id){
                return ['errcode' => 99001, 'errmsg' => '上级推客不能为自已'];
            }

            if(DistribPartner::update_data($member_id, $this->merchant_id, ['parent_member_id' => $parent_member_id])){
                return ['errcode' => 0, 'errmsg' => '操作成功'];
            }
        }
    }

    //创建数据导出任务
    public function exportTask(Request $request)
    {
        $type      = $request->input('type', 0);
        $condition = $request->input('condition', '');

        if(!$type){
            return ['errcode' => 99001, 'errmsg' => '任务类型必须'];
        }

        if(!$condition){
            return ['errcode' => 99001, 'errmsg' => '任务条件必须'];
        }

        if(DataExportTask::where('type', $type)->where('created_time', '>', Carbon::now()->subMinutes(10))->orderBy('created_time', 'desc')->first()){
            return ['errcode' => 99001, 'errmsg' => '同一类型的任务，10分钟内不可以重复'];
        }

        $task_id = DataExportTask::insert_data(['merchant_id' => $this->merchant_id, 'type' => $type, 'condition' => json_encode($condition)]);
        if($task_id){
            $this->dispatch(new JobDataExportTask($task_id));
            return ['errcode' => 0, 'errmsg' => '任务创建成功，请查看任务列表'];
        }
    }

    //导出任务列表
    public function taskLists(Request $request)
    {
        $pagesize = $request->input('pagesize', 10);
        $page     = $request->input('page', 1);
        $offset   = ($page - 1) * $pagesize;

        $query = DataExportTask::query();
        $query->where('merchant_id', $this->merchant_id);
        $count = $query->count();
        $data = $query->orderBy('id', 'desc')->skip($offset)->take($pagesize)->get();

        return ['errcode' => 0, 'count' => $count, 'data' => $data];
    }
}
