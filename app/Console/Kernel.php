<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\Inspire::class,
        \App\Console\Commands\VipCardUpgrade::class,
        \App\Console\Commands\CalendarGet::class,
        \App\Console\Commands\TradeStatsDay::class,
        \App\Console\Commands\MemberTodayStatics::class,
        \App\Console\Commands\OrderOvertimeClose::class,
        \App\Console\Commands\OrderTodayStatics::class,
        \App\Console\Commands\GoodsOnSale::class,
        \App\Console\Commands\FightgroupRefund::class,
        \App\Console\Commands\FightgroupStart::class,
        \App\Console\Commands\OrderRefundQuery::class,
        \App\Console\Commands\OrderFinish::class,
        \App\Console\Commands\OrderSureDelivery::class,

        \App\Console\Commands\OrderSumTodayStatics::class,
        \App\Console\Commands\TradeStatsSumDay::class,
        \App\Console\Commands\MerchantTodayStatics::class,
        \App\Console\Commands\IndustryMerchant::class,
        \App\Console\Commands\MerchantTotalDay::class,
        \App\Console\Commands\XcxStatics::class,

        \App\Console\Commands\DiscountActivityEnd::class,
        \App\Console\Commands\DiscountActivityStart::class,
        \App\Console\Commands\OrderWaitPay::class,
        \App\Console\Commands\FormStatistics::class,
        //微信版本检测
        \App\Console\Commands\WeixinAppletVerify::class,
        //微信版本升级
        \App\Console\Commands\WeixinAppletVersion::class,
        //清理过期form id
        \App\Console\Commands\WeixinFormidDel::class,
		//微信打款
        \App\Console\Commands\WeixinTransfers::class,
        //微信打款检查
        \App\Console\Commands\WeixinTransfersCheck::class,
		//临时更新老数据
        \App\Console\Commands\TmpUpdateAppid::class,
		//临时修复批量发货未更新发货时间
        \App\Console\Commands\TmpUpdateDelivery::class,
        //更新装修老数据
        \App\Console\Commands\DesignOldData::class,

        //初始录入默认超级表单分类
        \App\Console\Commands\FormCate::class,

        //会员已领取优惠劵快过期提醒
        \App\Console\Commands\CouponExpireNotice::class,


        //小程序每日统计
        \App\Console\Commands\XcxSurveyDailyStatistics::class,
        \App\Console\Commands\XcxVisitDailyStatistics::class,
        \App\Console\Commands\XcxVisitDistributionDailyStatistics::class,
        \App\Console\Commands\XcxVisitPageDailyStatistics::class,
        \App\Console\Commands\XcxVisitRetainDailyStatistics::class,
        \App\Console\Commands\XcxUserPortraitDailyStatistics::class,
        //预约服务提醒
        \App\Console\Commands\ApptNotice::class,
        //订单每小时数据统计
        \App\Console\Commands\OrderHourDailyStatistics::class,
        //更新老数据的链接标识
        \App\Console\Commands\DesignLinktab::class,
        
        //虚拟商品订单，失效自动完成
        \App\Console\Commands\VirtualComplete::class,
        //推客数据每日统计
        \App\Console\Commands\DistribStatistics::class,
        
        //改变订单支付方式数据
        \App\Console\Commands\OrderPayTypeData::class,
            
        //关闭砍价活动
        \App\Console\Commands\BargainOvertimeClose::class,

        //直播开关
        \App\Console\Commands\LiveSwitch::class,
        //直播统计数据
        \App\Console\Commands\LiveStats::class,
        //直播录像删除
        \App\Console\Commands\LiveRecordDel::class,
        //直播最大并发数
        \App\Console\Commands\LiveChannelMax::class,
        //录播费用关闭
        \App\Console\Commands\LiveClosePublish::class,

        //录播人次月底清零
        \App\Console\Commands\LiveRecordReset::class,
        
        //收费版商户送一个直播包
        \App\Console\Commands\LiveGiveBag::class,
        //活动提醒
        \App\Console\Commands\ActivityAlert::class,

        \App\Console\Commands\DataHandle::class,
        
        //群发优惠券
        \App\Console\Commands\CouponSendAll::class,

        //REDIS实现消息队列
        \App\Console\Commands\QueueNotify::class,

        //优惠劵每日领取量、使用量、领取人数、使用人数统计
        \App\Console\Commands\CouponDataCount::class,

        //删除导出任务
        \App\Console\Commands\DelDataExportTask::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('inspire')
                 ->hourly();
        //会员卡自动升级 -- 每天凌晨1点运行
        $schedule->command('command:vipCardUpgrade')
                 ->everyFiveMinutes();
        //商户交易统计表信息 -- 每天凌晨1点运行
        $schedule->command('command:TradeStatsDay')
            ->dailyAt('01:00');

        //交易统计表信息(累计) -- 每天凌晨2点运行
        $schedule->command('command:TradeStatsSumDay')
            ->dailyAt('02:00');

        //拉取本月与之后两个月的日历信息 -- 每天凌晨1点执行
        $schedule->command('calendar:get')
                ->dailyAt('01:00');
        
        //当日会员数据统计 -- 每天凌晨1点运行
        $schedule->command('command:MemberTodayStatics')
                 ->dailyAt('01:00');

        //当日商户数据统计 -- 每天凌晨2点运行
        $schedule->command('command:MerchantTodayStatics')
                 ->dailyAt('02:00');

        //总的平台商户数据统计 -- 每天凌晨2点运行
        $schedule->command('command:MerchantTotalDay')
            ->dailyAt('02:00');

        //订单每小时数据统计
        $schedule->command('command:OrderHourDailyStatistics')
            ->dailyAt('03:00');


        //小程序版本数据统计 -- 每天凌晨2点运行
        $schedule->command('command:XcxStatics')
            ->dailyAt('02:00');

        //小程序概况趋势数据统计 -- 每天凌晨1点运行
        $schedule->command('command:XcxSurveyDailyStatistics')
            ->dailyAt('06:00');

        //小程序访问趋势数据统计    AND  商户当日订单数据统计
        $schedule->command('command:XcxVisitDailyStatistics')
            ->dailyAt('06:10');

        //小程序访问分布数据统计 -- 每天凌晨2点运行
        $schedule->command('command:XcxVisitDistributionDailyStatistics')
            ->dailyAt('06:20');

        //小程序访问留存数据统计 -- 每天凌晨3点运行
        $schedule->command('command:XcxVisitPageDailyStatistics')
            ->dailyAt('06:40');

        //小程序访问页面数据统计 -- 每天凌晨2点30运行
        $schedule->command('command:XcxVisitRetainDailyStatistics')
            ->dailyAt('06:30');

        //小程序用户画像数据统计 -- 每天凌晨1点30运行
        $schedule->command('command:XcxUserPortraitDailyStatistics')
            ->dailyAt('06:50');

        //行业商户数据统计 -- 每天凌晨2点运行
        $schedule->command('command:IndustryMerchant')
            ->dailyAt('02:00');

        
        //每分钟执行过期订单关闭
        $schedule->command('command:OrderOvertimeClose')
            ->everyMinute(); 
        
        //商户当日订单数据统计 -- 每天凌晨1点运行
        // $schedule->command('command:OrderTodayStatics')  ->dailyAt('06:25');

        //当日订单数据统计(累计) -- 每天凌晨2点运行
        $schedule->command('command:OrderSumTodayStatics')
                 ->dailyAt('07:25');

        //优惠劵即将过期提醒 -- 每天早上8点运行
        $schedule->command('command:couponExpireNotice')
                 ->dailyAt('08:00');       

        //预约服务提醒 -- 每天早上7点运行
        $schedule->command('command:ApptNotice')
                 ->dailyAt('07:00');

        //商品定时上架
        $schedule->command('command:goodsOnSale')->everyMinute();

        //每分钟执行：拼团到时间未成团退款
        $schedule->command('command:FightgroupRefund')
            ->everyMinute();

        //每分钟执行：自动更新未开始的拼团状态为开始
        $schedule->command('command:FightgroupStart')
            ->everyMinute();
		
		//每分钟执行：订单状态查询
		$schedule->command('command:OrderRefundQuery')
                 ->everyFiveMinutes();
		
		//每分钟执行：订单自动完成（完成时间后7天）
		$schedule->command('command:OrderFinish')
                 ->everyMinute();
		
		//每小时执行：订单自动确认收货（7+n日后）
		$schedule->command('command:OrderSureDelivery')
                 ->hourly();

        //每分钟执行：自动更新未开始的满减状态为结束
        $schedule->command('command:DiscountActivityEnd')
            ->everyMinute();
        //每分钟执行：自动更新未开始的满减状态为开始
        $schedule->command('command:DiscountActivityStart')
            ->everyMinute();
        //每30分钟执行：版本审核检查
        $schedule->command('command:WeixinAppletVerify')
            ->everyThirtyMinutes();
        //每小时执行：版本更新
        $schedule->command('command:WeixinAppletVersion')
            ->hourly();
        //每小时执行：模板消息formid清除
        $schedule->command('command:WeixinFormidDel')
            ->hourly();
        //每30分钟执行：微信打款
        $schedule->command('command:WeixinTransfers')
            ->everyThirtyMinutes();
        //每5分钟执行：微信打款检查
        $schedule->command('command:WeixinTransfersCheck')
            ->everyFiveMinutes();
		//每分钟执行待付款消息模板通知
        $schedule->command('command:OrderWaitPay')
            ->everyMinute(); 

        //当日超级表单数据统计 -- 每天凌晨2点运行
        $schedule->command('command:FormStatistics')
                 ->dailyAt('02:00');
        
        //虚拟商品订单，失效自动完成 -- 每天凌晨3点运行
        $schedule->command('command:VirtualComplete')
                 ->dailyAt('03:00');
        //tuike数据统计 -- 每天凌晨2点运行
        $schedule->command('command:DistribStatistics')
                 ->dailyAt('02:00');

        //每分钟执行到期砍价活动
        $schedule->command('command:BargainOvertimeClose')
            ->everyMinute();

        //每5分钟执行：直播开关
        $schedule->command('command:LiveSwitch')
            ->everyFiveMinutes();

        //每天早上4点运行 直播统计数据
        $schedule->command('command:LiveStats')
            ->dailyAt('02:00');

        //每天早上4点运行 直播录像删除
        $schedule->command('command:LiveRecordDel')
            ->dailyAt('02:00');

        //每30分钟执行：录播费用到期
        $schedule->command('command:LiveClosePublish')
            ->everyThirtyMinutes();
       //每10分钟执行：直播峰值
        $schedule->command('command:LiveChannelMax')
            ->everyTenMinutes();
        
        //录播人次月底清零
        $schedule->command('command:LiveRecordReset')
                ->monthly();

        //活动提醒
        $schedule->command('command:ActivityAlert') ->hourly();
        
        
        //群发优惠券 -- 每半小时执行
        $schedule->command('command:CouponSendAll')
                 ->everyThirtyMinutes();

        //统计昨天的优惠劵领取数据 --每天凌晨1点执行
        $schedule->command('command:couponDataCount')
                 ->dailyAt('01:00');
        
        //删除一月前的导出任务 --每天午夜执行一次
        $schedule->command('command:delDataExportTask')
                 ->daily();
    }
}
