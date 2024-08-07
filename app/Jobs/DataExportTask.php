<?php

namespace App\Jobs;

use App\Jobs\Job;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Contracts\Queue\ShouldQueue;

use App\Models\DataExportTask as DataExportTaskModel;
use App\Models\Member;
use App\Models\DistribOrder;
use App\Models\DistribPartner;
use App\Models\WeixinInfo;
use App\Models\DistribBuyerRelation;
use App\Services\QiNiuService;

class DataExportTask extends Job implements SelfHandling, ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    protected $task_id;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($task_id)
    {
        $this->task_id = $task_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $task = DataExportTaskModel::where('id', $this->task_id)->where('status', 0)->first();
        if($task){
            switch ($task->type) {
                case 1:
                    $this->distribOrder($task);
                    break;
                case 2:
                    $this->distribInfo($task);
                    break;
                case 3:
                    $this->distribInfoCheck($task);
                    break;
            }
        }
    }

    private function distribOrder($task)
    {
        $task_id     = $task->id;
        $merchant_id = $task->merchant_id;
        $condition   = $task->condition;
        $status      = $task->status;

        if($status === 0){
            DataExportTaskModel::where('id', $task_id)->update(['status' => 1]);
            $condition = json_decode($condition, true);
            extract($condition);
            $query = DistribOrder::query();

            $query->where('merchant_id', $merchant_id);

            if($appid){
                $query->where('appid', $appid);
            }

            if($settled_time){
                $tempFinishedTime = trim($settled_time);
                $tempTime = explode('/', trim($settled_time));

                $query->where('settled_time', '>', $tempTime[0])->where('settled_time', '<', $tempTime[1]);
            }

            if($status){
                $query->where('status', $status-1);
            }

            if($order_sn){
                $query->where('order_sn', $order_sn);
            }

            if($mobile){
                $member_ids = DistribPartner::where('mobile', $mobile)->lists('member_id');
            }

            if($name){
                $member_ids = DistribPartner::where('name', $name)->lists('member_id');
            }

            if($mobile || $name){
                $query->whereIn('member_id', $member_ids);
            }

            if($member_id){
                $query->where('member_id', $member_id - MEMBER_CONST);
            }

            $query->orderBy('created_time', 'desc');

            $filepath = storage_path('app').DIRECTORY_SEPARATOR.'distrib_order_export_'.$merchant_id.'_'.time().'.csv';
            $file     = fopen($filepath, "w");

            //表格列表
            $head = ['创建时间', '买家', '订单信息', '实付金额', '佣金', '来源推客', '推客手机号', '推客会员账号', '结算时间', '来源小程序', '订单状态'];
            foreach($head as $key => $val){
                $head[$key] = @iconv('UTF-8', 'GBK//IGNORE', $val);
            }
            fputcsv($file, $head);

            $initiate_status = [
                DISTRIB_AWAIT         => '待处理',
                DISTRIB_NOT           => '不参与分佣',
                DISTRIB_AWAIT_SETTLED => '已处理，待结算',
                DISTRIB_FINISH        => '已结算',
                DISTRIB_REFUND        => '已退单'
            ];

            $query->chunk(500, function($list) use ($file, $merchant_id, $initiate_status) {
                foreach($list as $value){
                    $member  = Member::get_data_by_id($value['order_member_id'], $merchant_id);
                    $partner = DistribPartner::get_data_by_memberid($value['member_id'], $merchant_id);
                    $wxinfo  = WeixinInfo::get_one_appid($merchant_id, $value['appid']);

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
                        'weapp'           => $wxinfo['nick_name'], //来源小程序
                        'order_status'    => $initiate_status[$value['status']] //订单状态
                    ];

                    foreach($row as $k => $v){
                        $row[$k] = @iconv('UTF-8', 'GBK//IGNORE', $v);
                    }
                    fputcsv($file, $row);
                }
            });

            fclose($file);
            $result = (new QiNiuService())->uploadFile($filepath);
            if($result && $result['errcode'] == 0){
                unlink($filepath);
                $filename = $result['data'];
            }
            DataExportTaskModel::where('id', $task_id)->update(['status' => 2, 'filepath' => $filename]);
        }
    }

    private function distribInfo($task)
    {
        $task_id     = $task->id;
        $merchant_id = $task->merchant_id;
        $condition   = $task->condition;
        $status      = $task->status;

        if($status === 0){
            DataExportTaskModel::where('id', $task_id)->update(['status' => 1]);
            $condition = json_decode($condition, true);
            extract($condition);
            $query = DistribPartner::query();

            $query->where('merchant_id', $merchant_id);

            if($wxinfo_id){
                $query->where('wxinfo_id', $wxinfo_id);
            }

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
            if($status > 0){
                $query->where('status', $status);
            }
            //推荐人姓名
            if($referrer_name){
                //从会员表中查询
                $parent_member_id = Member::where('merchant_id', $merchant_id)->where('name', 'like', '%'.$referrer_name.'%')->lists('id');
                if($parent_member_id){
                    $query->whereIn('parent_member_id', $parent_member_id);
                }
            }
            //开始时间
            if($start_time && $end_time){
                $query->where('check_time', '>=', $start_time)->where('check_time', '<=', $end_time.' 23:59:59');
            }

            $query->whereIn('status', [1, 2]); //推客列表 正常+禁用

            $query->orderBy('created_time', 'desc');

            $filepath = storage_path('app').DIRECTORY_SEPARATOR.'distrib_info_'.$merchant_id.'_'.time().'.csv';
            $file     = fopen($filepath, "w");

            $head = ['推客信息', '会员账号', '未结算佣金', '已结算佣金', '推广订单总额', '推荐人(会员号)', '推客下级人数', '佣金下级人数', '姓名', '手机号', '加入时间', '来源小程序', '状态'];
            foreach($head as $key => $val){
                $head[$key] = @iconv('UTF-8', 'GBK//IGNORE', $val);
            }
            fputcsv($file, $head);

            $status_arr = ['待处理','正常','禁用','已拒绝'];

            $query->chunk(500, function($list) use ($file, $merchant_id, $status_arr) {
                foreach($list as $value){
                    //推客昵称
                    $member = Member::get_data_by_id($value['member_id'], $merchant_id);
                    //上级推客信息
                    $parent_info = '';
                    if($value['parent_member_id']){
                        $higher_member = Member::get_data_by_id($value['parent_member_id'], $merchant_id);
                        $account       = $value['parent_member_id'] + MEMBER_CONST;
                        $parent_info   = !empty($higher_member['name']) ? filterEmoji($higher_member['name']) : '';
                        $parent_info .= '(' .$account .')';
                    }
                    //小程序信息
                    $wxinfo = WeixinInfo::check_one_id($value['wxinfo_id']);
                    //推广订单额
                    $order_amount = DistribOrder::where('member_id', $value['member_id'])->sum('order_amount');
                    //下级人数
                    $team_size = DistribPartner::where('merchant_id', $merchant_id)->where('parent_member_id', $value['member_id'])->whereIn('status', [1, 2])->count();
                    //佣金下级人数
                    $commission_num = DistribBuyerRelation::where('merchant_id', $merchant_id)->where('distrib_member_id', $value['member_id'])->count();

                    $row = [
                        'nick_name'        => filterEmoji($member['name']), //推客呢称
                        'account'          => $value['member_id'] + MEMBER_CONST, //推客会员帐号
                        'expect_comission' => $value['expect_comission'], //未结算佣金
                        'total_comission'  => $value['total_comission'], //已结算佣金
                        'order_amount'     => $order_amount, //推广订单总额
                        'parent_info'      => $parent_info, //推荐人(会员号)
                        'team_size'        => $team_size ? $team_size : 0, //下级人数
                        'commission_num'   => $commission_num ? $commission_num : 0, //佣金下级人数
                        'name'             => $value['name'], //姓名
                        'mobile'           => $value['mobile'], //手机号
                        'check_time'       => $value['check_time'], //加入时间
                        'weapp'            => $wxinfo['nick_name'], //来源小程序
                        'status'           => $status_arr[$value['status']] //推客状态
                    ];

                    foreach($row as $k => $v){
                        $row[$k] = @iconv('UTF-8', 'GBK//IGNORE', $v);
                    }

                    fputcsv($file, $row);
                }
            });

            fclose($file);
            $result = (new QiNiuService())->uploadFile($filepath);
            if($result && $result['errcode'] == 0){
                unlink($filepath);
                $filename = $result['data'];
            }
            DataExportTaskModel::where('id', $task_id)->update(['status' => 2, 'filepath' => $filename]);
        }
    }

    private function distribInfoCheck($task)
    {
        $task_id     = $task->id;
        $merchant_id = $task->merchant_id;
        $condition   = $task->condition;
        $status      = $task->status;

        if($status === 0){
            DataExportTaskModel::where('id', $task_id)->update(['status' => 1]);
            $condition = json_decode($condition, true);
            extract($condition);
            $query = DistribPartner::query();

            $query->where('merchant_id', $merchant_id);

            if($wxinfo_id){
                $query->where('wxinfo_id', $wxinfo_id);
            }

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
            if($status > 0){
                $query->where('status', $status);
            }
            //推荐人帐号
            if($referrer_id){
                $query->where('parent_member_id', $referrer_id - MEMBER_CONST);
            }
            //推荐人姓名
            if($referrer_name){
                //从会员表中查询
                $parent_member_id = Member::where('merchant_id', $merchant_id)->where('name', 'like', '%'.$referrer_name.'%')->lists('id');
                if($parent_member_id){
                    $query->whereIn('parent_member_id', $parent_member_id);
                }
            }
            //申请时间
            if($start_time && $end_time){
                $query->where('created_time', '>=', $start_time)->where('created_time', '<=', $end_time.' 23:59:59');
            }

            $query->orderBy('created_time', 'desc');

            $filepath = storage_path('app').DIRECTORY_SEPARATOR.'distrib_info_check_'.$merchant_id.'_'.time().'.csv';
            $file     = fopen($filepath, "w");

            $head = ['申请推客', '会员账号', '姓名', '手机号', '推荐人(会员号)', '来源小程序', '申请时间', '处理时间', '审核状态'];
            foreach($head as $key => $val){
                $head[$key] = @iconv('UTF-8', 'GBK//IGNORE', $val);
            }
            fputcsv($file, $head);

            $status_arr = ['待处理','已通过','已通过','已拒绝'];

            $query->chunk(500, function($list) use ($file, $merchant_id, $status_arr) {
                foreach($list as $value){
                    //推客昵称
                    $member = Member::get_data_by_id($value['member_id'], $merchant_id);
                    //上级推客信息
                    $parent_info = '';
                    if($value['parent_member_id']){
                        $higher_member = Member::get_data_by_id($value['parent_member_id'], $merchant_id);
                        $account       = $value['parent_member_id'] + MEMBER_CONST;
                        $parent_info   = !empty($higher_member['name']) ? filterEmoji($higher_member['name']) : '';
                        $parent_info .= '(' .$account .')';
                    }
                    //小程序信息
                    $wxinfo = WeixinInfo::check_one_id($value['wxinfo_id']);

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

                    foreach($row as $k => $v){
                        $row[$k] = @iconv('UTF-8', 'GBK//IGNORE', $v);
                    }

                    fputcsv($file, $row);
                }
            });

            fclose($file);
            $result = (new QiNiuService())->uploadFile($filepath);
            if($result && $result['errcode'] == 0){
                unlink($filepath);
                $filename = $result['data'];
            }
            DataExportTaskModel::where('id', $task_id)->update(['status' => 2, 'filepath' => $filename]);
        }
    }
}
