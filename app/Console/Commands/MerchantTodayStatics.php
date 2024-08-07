<?php
/**
 * Created by PhpStorm.
 * User: lizhenheng
 * Date: 2017-11-27
 * Time: 上午 11:17
 */
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Utils\SendMessage;
use App\Models\MerchantStaticsDaily;

class MerchantTodayStatics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:MerchantTodayStatics';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '统计当日商户数';

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
                $seven_daily_end=date('Y-m-d', strtotime ("-7 day", strtotime($daily_start)))." 00:00:00";
                $month_daily=date('Y-m-d', strtotime ("-1 month", strtotime($daily_start)))." 00:00:00";
                $starttime=date("Y-m-d",strtotime("-".$i." day"))." 00:00:00";
                $result=MerchantStaticsDaily::get_data_by_id($daily_start);
                if(!$result){
                    //按天统计
                    //商家操作统计
                    //有过授权操作的小程序数
                    $today_empower=\DB::select("SELECT count(DISTINCT appid) as num FROM weixin_info WHERE `type` = 1 and appid != '' and auth_time<:createtime and auth_time>=:starttime",[':createtime'=>$daily_end,':starttime'=>$starttime]);
                    //七天内登录的商户数
                    $seven_login_total=\DB::select("select count(DISTINCT merchant_id) as num from user_log where `type` in (3,16,41) and created_time>=:starttime and created_time<:createtime",[':createtime'=>$daily_end,':starttime'=>$seven_daily_end]);//7天内登录的商户数
                    //一个月未登录的商户数
                    $month_login_total=\DB::select("select count(1) as num from merchant where id not in(select DISTINCT merchant_id from user_log where type in (3,16,41) and created_time>=:starttime and created_time<:createtime) and created_time<:endtime",[':createtime'=>$daily_end,':starttime'=>$month_daily,':endtime'=>$daily_end]);
                    //发布失败的小程序数量
                    $today_weapp_release_fail=\DB::select("SELECT count(DISTINCT appid) as num FROM weixin_info WHERE type = 1 and  appid != '' and auth_time <:createtime and auth_time>=:starttime and merchant_id not in (select merchant_id from weixin_template where `release` = 1)",[':createtime'=>$daily_end,':starttime'=>$starttime]);
                    //授权成功且留在平台的小程序数
                    $today_empower_success=\DB::select("SELECT count(DISTINCT appid) as num FROM weixin_info WHERE type = 1 and status = 1 and  appid != '' and auth_time<:createtime and auth_time>=:starttime",[':createtime'=>$daily_end,':starttime'=>$starttime]);

                    //发布成功的小程序数
                    $today_weapp_release_success=\DB::select("SELECT count(DISTINCT appid) as num FROM weixin_template where `release`=1 and release_date<:createtime and release_date>=:starttime",[':createtime'=>strtotime($daily_end),':starttime'=>strtotime($starttime)]);

                    //净增发布成功数
                    $today_weapp_release_net_success = \DB::select("SELECT count(*) as num FROM (SELECT  appid,release_date  FROM weixin_template WHERE `release`=1  AND `status` = 1 GROUP BY `appid`) t WHERE t.release_date >=:starttime and t.release_date <=:endtime",[':endtime'=>strtotime($daily_end),':starttime'=>strtotime($starttime)]);
                    //注册
                    //昨天pc注册统计
                    $today_register_pc=0;
                    $today_register_mobile=0;
                    $today_register_xcx=0;
                    $today_register_whb=0;

                    $today_register_alls =\DB::select("select count(1) as num,source from merchant where created_time<:createtime and created_time>=:starttime group by source",[':createtime'=>$daily_end,':starttime'=>$starttime]);
                    if($today_register_alls){
                        foreach ($today_register_alls as $key=>$val){
                            if($val->source==1){
                                //PC端注册数
                                $today_register_pc=$val->num;
                            }elseif($val->source==2){
                                //移动端注册数
                                $today_register_mobile=$val->num;
                            }elseif($val->source==3){ //新加
                                //小程序注册数
                                $today_register_xcx=$val->num;
                            }elseif($val->source==10){ //新加
                                //微伙伴注册数
                                $today_register_whb=$val->num;
                            }
                        }
                    }

                    //小程序注册用户登录数
                    $today_xcx_register_login=\DB::select("select count(DISTINCT merchant_id) as num from user_log where `type` in (3,16,41) and merchant_id in (select id from merchant where source = 3) and created_time >= :starttime and created_time < :createtime",[':createtime'=>$daily_end,':starttime'=>$starttime]);
                    //移动端注册用户登录数
                    $today_mobile_register_login=\DB::select("select count(DISTINCT merchant_id) as num from user_log where type in (3,16,41) and merchant_id in (select id from merchant where source = 2) and created_time >= :starttime and created_time < :createtime",[':createtime'=>$daily_end,':starttime'=>$starttime]);
                    //PC端注册用户登录数
                    $today_pc_register_login=\DB::select("select count(DISTINCT merchant_id) as num from user_log where type in (3,16,41) and merchant_id in (select id from merchant where source = 1) and created_time >= :starttime and created_time < :createtime",[':createtime'=>$daily_end,':starttime'=>$starttime]);

                    //微伙伴注册用户登录数
                    $today_whb_register_login=\DB::select("select count(DISTINCT merchant_id) as num from user_log where type in (3,16,41) and merchant_id in (select id from merchant where source = 10) and created_time >= :starttime and created_time < :createtime",[':createtime'=>$daily_end,':starttime'=>$starttime]);


                    //授权成功后又流失的小程序数
                    $today_weapp_emp_lost=\DB::select("SELECT count(DISTINCT appid) as num FROM `weixin_template` WHERE `exit_date` > 0  AND `status` =  -1 and updated_time >= :starttime and updated_time < :createtime",[':createtime'=>$daily_end,':starttime'=>$starttime]);
                    //发布成功后又解绑的小程序数
                    $today_weapp_release_lost=\DB::select("select count(DISTINCT appid) as num from `weixin_template` where `release`=1 and `status` = -1 and updated_time >= :starttime and updated_time < :createtime",[':createtime'=>$daily_end,':starttime'=>$starttime]);



                    //发布留存
                    $release_done = \DB::select("SELECT count(DISTINCT appid)AS num FROM `weixin_template` WHERE `release` = 1 AND `status` = 1 AND updated_time>= :starttime and updated_time < :createtime",[':createtime'=>$daily_end,':starttime'=>$starttime]);

                    //发布留存-免费
                    $release_done_free = \DB::select("SELECT count(DISTINCT appid)AS num FROM `weixin_template` WHERE `release` = 1 AND `status` = 1 AND updated_time>= :starttime and updated_time < :createtime and merchant_id in (select id from merchant where status=1 and version_id in(1,5))",[':createtime'=>$daily_end,':starttime'=>$starttime]);


                    //发布留存-收费
                    $release_done_charge = \DB::select("SELECT count(DISTINCT appid)AS num FROM `weixin_template` WHERE `release` = 1 AND `status` = 1 AND updated_time>= :starttime and updated_time < :createtime and merchant_id in (select id from merchant where status=1 and version_id in(2,3,4,6))",[':createtime'=>$daily_end,':starttime'=>$starttime]);


                    //授权留存-免费
                    $empower_success_free=\DB::select("SELECT count(DISTINCT appid) as num FROM weixin_info WHERE type = 1 and status = 1 and  appid != '' and auth_time>= :starttime and auth_time < :createtime and merchant_id in (select id from merchant where status=1 and version_id in(1,5))",[':createtime'=>$daily_end,':starttime'=>$starttime]);


                    //授权留存-收费
                    $empower_success_charge=\DB::select("SELECT count(DISTINCT appid) as num FROM weixin_info WHERE type = 1 and status = 1 and  appid != '' and auth_time>= :starttime and auth_time < :createtime and merchant_id in (select id from merchant where status=1 and version_id in(2,3,4,6))",[':createtime'=>$daily_end,':starttime'=>$starttime]);



                    //注册登录总数查
                    //PC端注册用户登录总数
                    $total_pc_register_login=\DB::select("select count(DISTINCT merchant_id) as num from user_log where type in (3,16,41) and merchant_id in (select id from merchant where source = 1) AND created_time < :createtime", [':createtime'=>$daily_end]);
                    //移动端注册用户登录总数
                    $total_mobile_register_login=\DB::select("select count(DISTINCT merchant_id) as num from user_log where type in (3,16,41) and merchant_id in (select id from merchant where source = 2) AND created_time < :createtime", [':createtime'=>$daily_end]);
                    //小程序注册用户登录总数
                    $total_xcx_register_login=\DB::select("select count(DISTINCT merchant_id) as num from user_log where `type` in (3,16,41) and merchant_id in (select id from merchant where source = 3) AND created_time < :createtime", [':createtime'=>$daily_end]);
                    //微伙伴注册用户登录总数
                    $total_whb_register_login=\DB::select("select count(DISTINCT merchant_id) as num from user_log where type in (3,16,41) and merchant_id in (select id from merchant where source = 10) AND created_time < :createtime", [':createtime'=>$daily_end]);



                    $data=[];
                    $data['today_empower']=$today_empower[0]->num;
                    $data['seven_login_total']=$seven_login_total[0]->num;
                    $data['month_login_total']=$month_login_total[0]->num;
                    $data['today_weapp_release_fail']=$today_weapp_release_fail[0]->num;
                    $data['today_empower_success']=$today_empower_success[0]->num;
                    $data['today_register_pc']=$today_register_pc;
                    $data['today_register_mobile']=$today_register_mobile;
                    $data['today_register_xcx']=$today_register_xcx;
                    $data['today_register_whb']=$today_register_whb;
                    $data['today_xcx_register_login']=$today_xcx_register_login[0]->num;
                    $data['today_mobile_register_login']=$today_mobile_register_login[0]->num;
                    $data['today_pc_register_login']=$today_pc_register_login[0]->num;
                    $data['today_whb_register_login']=$today_whb_register_login[0]->num;
                    $data['today_weapp_release_success']=$today_weapp_release_success[0]->num;

                    $data['today_weapp_emp_lost']=$today_weapp_emp_lost[0]->num;
                    $data['today_weapp_release_lost']=$today_weapp_release_lost[0]->num;

                    $data['today_weapp_release_net'] = $today_weapp_release_net_success[0]->num;



                    $data['today_release_success']=$release_done[0]->num;
                    $data['today_release_success_free']=$release_done_free[0]->num;
                    $data['today_release_success_charge']=$release_done_charge[0]->num;
                    $data['today_empower_success_free']=$empower_success_free[0]->num;
                    $data['today_empower_success_charge']=$empower_success_charge[0]->num;



                    $data['total_pc_register_login'] = $total_pc_register_login[0]->num;
                    $data['total_mobile_register_login'] = $total_mobile_register_login[0]->num;
                    $data['total_xcx_register_login'] = $total_xcx_register_login[0]->num;
                    $data['total_whb_register_login'] = $total_whb_register_login[0]->num;
                    
                    $data['total_register_login'] = $data['total_pc_register_login']+$data['total_mobile_register_login']+$data['total_xcx_register_login']+$data['total_whb_register_login'];


                    $data['day_time']=$daily_start;
                    $data['created_time']=date("Y-m-d H:i:s");
                    MerchantStaticsDaily::insert($data);
                }
            }
        }catch (Exception $e) {
            SendMessage::send_sms('18300689297', '统计当日商户数脚本异常，请及时查看！', '6', 50);
        }
    }
}