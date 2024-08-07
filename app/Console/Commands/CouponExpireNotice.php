<?php

/**
 * 手动升级的会员卡到期后运行此自动升级程序
 *
 * @package default
 * @author guoqikai
 **/

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Coupon;
use App\Models\CouponCode;
use Carbon\Carbon;
use App\Services\WeixinMsgService;

class CouponExpireNotice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:couponExpireNotice';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '优惠劵即将过期消息提醒';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(WeixinMsgService $weixinMsgService)
    {
        parent::__construct();
        $this->weixinMsgService = $weixinMsgService;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $start_time = Carbon::now()->toDateString();
        $end_time   = Carbon::now()->addDay(3)->toDateString();
        CouponCode::where('is_delete', 1)->where('status', 0)->where('start_time', '<', Carbon::now())->where('end_time', '>', $start_time)->where('end_time', '<', $end_time)->chunk(100, function($list){
            foreach($list as $row){
                $coupon = Coupon::get_data_by_id($row->coupon_id, $row->merchant_id);

                $content_type_str = '';
                if($coupon->content_type == 1){
                    if($coupon->is_condition == 1){
                        $content_type_str = '满'.$coupon->condition_val.'元减'.$coupon->coupon_val;
                    }else{
                        $content_type_str = '立减'.$coupon->coupon_val.'元';
                    }
                }

                if($coupon->content_type == 2){
                    if($coupon->is_condition == 1){
                        $content_type_str = '满'.$coupon->condition_val.'元打'.floatval($coupon->coupon_val).'折';
                    }else{
                        $content_type_str = floatval($coupon->coupon_val).'折';
                    }
                }

                $msgData = [
                    'merchant_id' => $row->merchant_id,
                    'member_id'   => $row->member_id,
                    'appid'       => '',
                    'id'          => $row->id,
                    'type'        => $content_type_str,
                    'time'        => date('Y-m-d', strtotime($row->end_time)),
                    'remark'      => '优惠劵将在3天内到期，请及时使用。'
                ];

                $result = $this->weixinMsgService->couponRemark($msgData);

                \Log::info('发送消息模板（优惠劵过期提醒）member_id->'.$row->member_id.':require->'.json_encode($msgData,JSON_UNESCAPED_UNICODE).',result->'.json_encode($result,JSON_UNESCAPED_UNICODE));
            }
        });
    }
}
