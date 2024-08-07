<?php

/**
 * 数据处理
 *
 * @package default
 * @author guoqikai
 **/

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DistribPartner;
use App\Models\Member;
use App\Models\WeixinInfo;
use App\Models\DistribOrder;
use App\Models\DistribBuyerRelation;

class DataHandle extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:dataHandle {merchant_id} {page} {pagesize}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '数据处理';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $merchant_id = $this->argument('merchant_id');
        $page = $this->argument('page');
        $pagesize = $this->argument('pagesize');

        $page = $page ? intval($page) : 1;
        $pagesize = $pagesize ? intval($pagesize) : 500;
        $offset = ($page -1) * $pagesize;

        $filename = './storage/distrib_export_'.$merchant_id.'_'.$page.'.csv';
        $file = fopen($filename, "w");

        $head = ['推客信息', '会员账号', '未结算佣金', '已结算佣金', '推广订单总额', '推荐人(会员号)', '推客下级人数', '佣金下级人数', '姓名', '手机号', '加入时间', '来源小程序', '状态'];
        foreach($head as $key => $val){
            $head[$key] = @iconv('UTF-8', 'GBK//IGNORE', $val);
        }
        fputcsv($file, $head);

        $list = DistribPartner::where('merchant_id', $merchant_id)->whereIn('status', [1, 2])->skip($offset)->take($pagesize)->orderBy('id', 'asc')->get();


        $status_arr = ['待处理','正常','禁用','已拒绝'];
        foreach($list as $value){
            //推客昵称
            $member = Member::get_data_by_id($value['member_id'], $merchant_id);
            //上级推客信息
            $parent_info = '';
            if($value['parent_member_id']){
                $higher_member = Member::get_data_by_id($value['parent_member_id'], $merchant_id);
                $account       = $value['parent_member_id'] + MEMBER_CONST;
                $parent_info   = filterEmoji($higher_member['name'] . '(' .$account .')');
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

            $column = [];
            foreach($row as $k => $v){
                $column[$k] = @iconv('UTF-8', 'GBK//IGNORE', $v);
            }

            fputcsv($file, $column);
        }
        fclose($file);
        $this->info('All records have been imported.');
    }
}
