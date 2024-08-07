<?php

/**
 * 优惠劵每日领取量、使用量、领取人数、使用人数统计
 *
 * @package default
 * @author guoqikai
 **/

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CouponCode;
use App\Models\CouponDaily;
use Carbon\Carbon;

class CouponDataCount extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:couponDataCount';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '优惠劵每日领取量、使用量、领取人数、使用人数统计';

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
        $current_date = Carbon::now()->toDateString();
        if(strtotime($current_date) > strtotime('2018-09-06')){
            $day_time = Carbon::now()->subDays(1)->toDateString();
            $list = CouponCode::select('merchant_id', 'coupon_id')->where('created_time', '>', $day_time)->where('created_time', '<=', $day_time.' 23:59:59')->groupBy('coupon_id')->get();
            foreach($list as $row){
                $get_count = CouponCode::where('merchant_id', $row->merchant_id)->where('coupon_id', $row->coupon_id)->where('created_time', '>', $day_time)->where('created_time', '<=', $day_time.' 23:59:59')->count();

                $use_count = CouponCode::where('merchant_id', $row->merchant_id)->where('coupon_id', $row->coupon_id)->where('created_time', '>', $day_time)->where('created_time', '<=', $day_time.' 23:59:59')->where('status', 1)->count();

                $get_count_user = CouponCode::where('merchant_id', $row->merchant_id)->where('coupon_id', $row->coupon_id)->where('created_time', '>', $day_time)->where('created_time', '<=', $day_time.' 23:59:59')->distinct()->count('member_id');

                $use_count_user = CouponCode::where('merchant_id', $row->merchant_id)->where('coupon_id', $row->coupon_id)->where('created_time', '>', $day_time)->where('created_time', '<=', $day_time.' 23:59:59')->where('status', 1)->distinct()->count('member_id');
                
                $data = [
                    'merchant_id'    => $row->merchant_id,
                    'coupon_id'      => $row->coupon_id,
                    'day_time'       => $day_time,
                    'get_count'      => $get_count,
                    'use_count'      => $use_count,
                    'get_count_user' => $get_count_user,
                    'use_count_user' => $use_count_user
                ];

                CouponDaily::insert_data($data);
            }
        }else{
            //初始化一个月内的数据
            for($i=30;$i>0;$i--){
                $day_time = Carbon::now()->subDays($i)->toDateString();
                $list = CouponCode::select('merchant_id', 'coupon_id')->where('created_time', '>', $day_time)->where('created_time', '<=', $day_time.' 23:59:59')->groupBy('coupon_id')->get();
                foreach($list as $row){
                    $get_count = CouponCode::where('merchant_id', $row->merchant_id)->where('coupon_id', $row->coupon_id)->where('created_time', '>', $day_time)->where('created_time', '<=', $day_time.' 23:59:59')->count();

                    $use_count = CouponCode::where('merchant_id', $row->merchant_id)->where('coupon_id', $row->coupon_id)->where('created_time', '>', $day_time)->where('created_time', '<=', $day_time.' 23:59:59')->where('status', 1)->count();

                    $get_count_user = CouponCode::where('merchant_id', $row->merchant_id)->where('coupon_id', $row->coupon_id)->where('created_time', '>', $day_time)->where('created_time', '<=', $day_time.' 23:59:59')->distinct()->count('member_id');

                    $use_count_user = CouponCode::where('merchant_id', $row->merchant_id)->where('coupon_id', $row->coupon_id)->where('created_time', '>', $day_time)->where('created_time', '<=', $day_time.' 23:59:59')->where('status', 1)->distinct()->count('member_id');
                    
                    $data = [
                        'merchant_id'    => $row->merchant_id,
                        'coupon_id'      => $row->coupon_id,
                        'day_time'       => $day_time,
                        'get_count'      => $get_count,
                        'use_count'      => $use_count,
                        'get_count_user' => $get_count_user,
                        'use_count_user' => $use_count_user
                    ];

                    CouponDaily::insert_data($data);
                }
            }
        }
                
    }
}
