<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\FormFeedback;//表单反馈表
use App\Models\FormDailyView;//表单查阅表
use App\Models\FormSum;//表单反馈及查阅统计表
use Illuminate\Support\Facades\DB;



class FormStatistics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:FormStatistics';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '超级表单浏览量,反馈数统计';

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
        DB::beginTransAction();
        try{
            //每天时间区间为00:00:00-23:59:59
            $today_str = date('Y-m-d 00:00:00'); //今日凌晨时间格式
            $yesterday_short_str = date('Y-m-d',strtotime('-1 day')); //昨日时间短格式
            $yesterday_str = $yesterday_short_str.' 00:00:00'; //昨日凌晨时间格式
            $anteayer_short_str = date('Y-m-d',strtotime('-2 day')); //前天凌晨时间短格式
            //浏览表小程序分组查询+统计数量+插入form_sum表单
            FormDailyView::select(DB::raw('count(*) as view_sum_day,form_id,wxinfo_id,merchant_id'))
                ->whereDate('created_time','=',$yesterday_short_str)//时间等于昨天
                ->groupBy('wxinfo_id')
                ->groupBy('form_id')//以form+wxinfo_id 为基本单位
                ->groupBy('merchant_id')
                ->chunk(50,function($view_data)use($yesterday_short_str){
                    foreach($view_data as &$v){//拼接数据
                        $v = $v->toArray();
                        //查询该表当日浏览数及累计浏览数
                        $wheres = [
                            'merchant_id'=>$v['merchant_id'],
                            'form_id'=>$v['form_id'],
                            'wxinfo_id'=>$v['wxinfo_id']
                        ];
                        $v['view_sum'] = FormDailyView::where($wheres)->whereDate('created_time','<=',$yesterday_short_str)->count(); //累计
                        $v['day_time'] = $yesterday_short_str;
                        FormSum::where($wheres)->whereDate('day_time','=',$yesterday_short_str)->delete();//如果存在则删除
                        FormSum::insert_data($v);
                    }
                });
            //反馈表依照小程序+反馈分组进行统计
            $res = FormFeedback::select(DB::raw('count(*) as feedback_sum_day,form_id,wxinfo_id,merchant_id'))
                ->whereDate('created_time','=',$yesterday_short_str)//时间等于昨天
                ->groupBy('wxinfo_id')
                ->groupBy('form_id')//以form+wxinfo_id 为基本单位
                ->groupBy('merchant_id')
                ->chunk(50,function($view_data)use($yesterday_short_str){
                    foreach($view_data as &$v){//拼接数据
                        $v = $v->toArray();
                        $wheres = [
                            'merchant_id'=>$v['merchant_id'],
                            'form_id'=>$v['form_id'],
                            'wxinfo_id'=>$v['wxinfo_id']
                        ];
                         $v['feedback_sum'] = FormFeedback::where($wheres)
                             ->where('is_delete',1)
                             ->whereDate('created_time','<=',$yesterday_short_str)
                             ->count(); //累计反馈数
                        //若记录存在则修改 不存在则添加
                        $is_exist = FormSum::where($wheres)->whereDate('day_time','=',$yesterday_short_str)->first();
                        if($is_exist){
                            FormSum::where('id',$is_exist->id)->update($v);
                        }else{
                            $v['day_time'] = $yesterday_short_str;
                            $v['view_sum_day'] = 0;
                            $v['view_sum'] = FormDailyView::where($wheres)->whereDate('created_time','<=',$yesterday_short_str)->count(); //累计
                            FormSum::insert_data($v);
                        }
                    }

                });
            DB::commit();
        }catch(\Exception $e){
            DB::rollback();
            echo '错误';
        }

    }
}
