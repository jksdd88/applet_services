<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017-12-15
 * Time: 下午 05:18
 */
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Utils\SendMessage;
use App\Models\MerchantTotalDaily;

class MerchantTotalDay extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:MerchantTotalDay';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '统计当日平台总商户数';

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
        try{
            for($i=100;$i>=1;$i--)
            {
                $daily_start=date("Ymd",strtotime("-".$i." day"));
                $daily_end=date("Y-m-d",strtotime("-".($i-1)." day"))." 00:00:00";
                $result=MerchantTotalDaily::get_data_by_id($daily_start);
                if(!$result){
                    //商家操作统计总数据
                    //添加过商品的商家数量
                    $editgoods=\DB::select("select count(DISTINCT merchant_id) as num from goods where created_time<:createtime",[':createtime'=>$daily_end]);
                    //装修过的商家数量
                    $redecorated=\DB::select("select count(DISTINCT merchant_id) as num from shop_design where  created_time<:createtime",[':createtime'=>$daily_end]);
                    //发布过的商家数量
                    $release=\DB::select("select count(DISTINCT merchant_id) as num from weixin_template where `check` > 0 and created_time<:createtime",[':createtime'=>$daily_end]);
                    //发布成功的商家数量
                    $release_success=\DB::select("select count(DISTINCT merchant_id) as num from weixin_template where `release`=1 and created_time<:createtime",[':createtime'=>$daily_end]);
                    //授权公众号的商户数
                    $empower_wechat=\DB::select("SELECT count(DISTINCT merchant_id) as num FROM weixin_info WHERE `type`=2 and created_time<:createtime",[':createtime'=>$daily_end]);
                    //授权过的小程序数
                    $empower=\DB::select("SELECT count(DISTINCT appid) as num FROM weixin_info WHERE `type` = 1 and appid != '' and auth_time<:createtime ",[':createtime'=>$daily_end]);
                    //发布失败的小程序数量
                    $weapp_release_fail=\DB::select("SELECT count(DISTINCT appid) as num FROM weixin_info WHERE type = 1 and  appid != '' and auth_time <:createtime  and merchant_id not in (select merchant_id from weixin_template where `release` = 1)",[':createtime'=>$daily_end]);
                    //授权成功过的小程序数(留存)
                    $empower_success=\DB::select("SELECT count(DISTINCT appid) as num FROM weixin_info WHERE type = 1 and status = 1 and  appid != '' and auth_time<:createtime ",[':createtime'=>$daily_end]);


                    //发布成功的小程序数量
                    $weapp_release_success=\DB::select("select count(DISTINCT appid) as num from weixin_template where `release`=1 and release_date<:createtime",[':createtime'=>strtotime($daily_end)]);

                    //补全信息数--start
                    $replenish_pc_total=0;
                    $replenish_mobile_total=0;
                    $replenish_xcx_total=0;
                    $replenish_whb_total=0;
                    $replenish_alls=\DB::select("select count(1) as num,source  from merchant where (version_id = 1 or version_id = 5) and company != '' and created_time < :createtime GROUP BY source",[':createtime'=>$daily_end]);
                    if($replenish_alls){
                        foreach ($replenish_alls as $key=>$val){
                            if($val->source==1){
                                //PC端补全信息数
                                $replenish_pc_total=$val->num;
                            }elseif($val->source==2){
                                //移动端补全信息数
                                $replenish_mobile_total=$val->num;
                            }elseif($val->source==3){ //新加
                                //小程序补全信息数
                                $replenish_xcx_total=$val->num;
                            }elseif($val->source==10){ //新加
                                //微伙伴补全信息数
                                $replenish_whb_total=$val->num;
                            }
                        }
                    }

                    //授权成功后又流失的小程序数
                    $weapp_emp_lost=\DB::select("SELECT count(DISTINCT appid) as num FROM `weixin_template` WHERE `exit_date` > 0  AND `status` =  -1 and updated_time<:createtime",[':createtime'=>$daily_end]);

                    //发布成功后又解绑的小程序数
                    $weapp_release_lost=\DB::select("select count(DISTINCT appid) as num from `weixin_template` where `release`=1 and `status` = -1 and updated_time<:createtime",[':createtime'=>$daily_end]);

                    //累计提交过审核的小程序数
                    $weapp_check=\DB::select("select count(DISTINCT appid) as num from weixin_template where `check` > 0 and  check_date<:createtime",[':createtime'=>strtotime($daily_end)]);


                    //发布留存
                    $release_done = \DB::select("SELECT count(DISTINCT appid)AS num FROM `weixin_template` WHERE `release` = 1 AND `status` = 1 AND updated_time< :createtime ",[':createtime'=>$daily_end]);

                    //发布留存-免费
                    $release_done_free = \DB::select("SELECT count(DISTINCT appid)AS num FROM `weixin_template` WHERE `release` = 1 AND `status` = 1 AND updated_time< :createtime and merchant_id in (select id from merchant where status=1 and version_id in(1,5))",[':createtime'=>$daily_end]);


                    //发布留存-收费
                    $release_done_charge = \DB::select("SELECT count(DISTINCT appid)AS num FROM `weixin_template` WHERE `release` = 1 AND `status` = 1 AND updated_time< :createtime and merchant_id in (select id from merchant where status=1 and version_id in(2,3,4,6))",[':createtime'=>$daily_end]);


                    //授权留存-免费
                    $empower_success_free=\DB::select("SELECT count(DISTINCT appid) as num FROM weixin_info WHERE type = 1 and status = 1 and  appid != '' and auth_time<:createtime and merchant_id in (select id from merchant where status=1 and version_id in(1,5))",[':createtime'=>$daily_end]);


                    //授权留存-收费
                    $empower_success_charge=\DB::select("SELECT count(DISTINCT appid) as num FROM weixin_info WHERE type = 1 and status = 1 and  appid != '' and auth_time<:createtime and merchant_id in (select id from merchant where status=1 and version_id in(2,3,4,6))",[':createtime'=>$daily_end]);







                    $data=[];

                    $data['editgoods']=$editgoods[0]->num;
                    $data['redecorated']=$redecorated[0]->num;
                    $data['release_success']=$release_success[0]->num;
                    $data['release']=$release[0]->num;
                    $data['empower_wechat']=$empower_wechat[0]->num;
                    $data['empower']=$empower[0]->num;
                    $data['weapp_release_fail']=$weapp_release_fail[0]->num;
                    $data['empower_success']=$empower_success[0]->num;
                    $data['weapp_release_success']=$weapp_release_success[0]->num;

                    $data['replenish_pc_total']=$replenish_pc_total;
                    $data['replenish_mobile_total']=$replenish_mobile_total;
                    $data['replenish_xcx_total']=$replenish_xcx_total;
                    $data['replenish_whb_total']=$replenish_whb_total;

                    $data['weapp_emp_lost']=$weapp_emp_lost[0]->num;
                    $data['weapp_release_lost']=$weapp_release_lost[0]->num;
                    $data['weapp_check'] = $weapp_check[0]->num;

                    $data['release_done']=$release_done[0]->num;
                    $data['release_done_free']=$release_done_free[0]->num;
                    $data['release_done_charge'] = $release_done_charge[0]->num;
                    $data['empower_success_free']=$empower_success_free[0]->num;
                    $data['empower_success_charge']=$empower_success_charge[0]->num;


                    $data['day_time']=$daily_start;
                    $data['created_time']=date("Y-m-d H:i:s");
                    MerchantTotalDaily::insert($data);
                }
            }
        }catch (Exception $e) {
            SendMessage::send_sms('18300689297', '统计当日平台总商户数脚本异常，请及时查看！', '6', 50);
        }
    }
}