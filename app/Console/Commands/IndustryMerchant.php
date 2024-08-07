<?php

/**
 * 统计行业商户数据
 *
 * @package default
 * @author zhangyu
 **/

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Utils\SendMessage;
use App\Models\Merchant;
use App\Models\IndustryDaily;

class IndustryMerchant extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:IndustryMerchant';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '统计行业商家数据';


    /**
     * The model
     */
    protected $industrydaily;

    protected $merchant;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct( Merchant $merchant, IndustryDaily $industrydaily)
    {
        parent::__construct();
        $this->merchant = $merchant;
        $this->industrydaily = $industrydaily;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(){

        try{
            for($i=1;$i<=65;$i++){

                $yestoday = date("Ymd", strtotime("-" . $i . " day"));

                $industry = config('industrysign');

                $merchant_info = new Merchant();

                $industry_daily = new IndustryDaily();

                foreach ($industry as $key => $value) {  //遍历行业写入统计库

                    $wheres=array();
                    $industry_id = $key;
                    $wheres[] = array('column' => 'industry_sign', 'value' => $industry_id, 'operator' => '=');
                    $wheres[] = array('column' => 'created_time', 'value' => date("Y-m-d", strtotime("-".($i-1)."day")), 'operator' => '<=');
                    $total = $merchant_info->get_data_count($wheres); //行业商户总数

                    $wheres = array();
                    $wheres[] = array('column' => 'industry_sign', 'value' => $industry_id, 'operator' => '=');
                    $wheres[] = array('column' => 'created_time', 'value' => date("Y-m-d", strtotime("-".($i-1)."day")), 'operator' => '<');
                    $wheres[] = array('column' => 'created_time', 'value' => date("Y-m-d", strtotime("-".$i."day")), 'operator' => '>=');
                    $total_day = $merchant_info->get_data_count($wheres); //每日行业商户总数


                    $data = $industry_daily->where(array('industry_id' => $industry_id, 'day_time' => $yestoday))->first();

                    $putdata = array(
                        'industry_id' => $industry_id,
                        'merchant_total' => $total,
                        'merchant_total_today' => $total_day,
                        'day_time'=>$yestoday

                    );
                    if (!$data) {

                        $result = $industry_daily->insert_data($putdata);
                    }
                }

            }
        }
        catch (\Exception $e)
        {
            SendMessage::send_sms('15651778032', '统计行业商户数据脚本异常，请及时查看！', '6', 50); //短信通知 -》zhangyu
        }

    }
}
