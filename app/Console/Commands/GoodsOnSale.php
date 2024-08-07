<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/9/13
 * Time: 17:59
 * Author: DuMing
 * 商品定时上架
 */
namespace App\Console\Commands;

use App\Models\Goods;
use Illuminate\Console\Command;
use App\Models\Member;
use App\Utils\SendMessage;
class GoodsOnSale extends Command{

    protected $signature = 'command:goodsOnSale';

    protected $description = '商品定时上架';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        try {
            $wheres = array(
                array('column'=>'onsale_type','value'=>1,'operator'=>'='),
                array('column'=>'is_delete','value'=>1,'operator'=>'='),
                array('column'=>'onsale','value'=>0,'operator'=>'='),
                array('column'=>'onsale_time','value'=>date('Y-m-d H:i:s',time()),'operator'=>'<='),
            );
            $data = Goods::getDataByWhere($wheres,array('id','onsale_time'));
            $data = $data->toArray();
            if(!empty($data)){
                foreach($data as $key => $item){
                    $time = time();
                    $onsale_time = strtotime($item['onsale_time']);
                    if($onsale_time<=$time){
                        $update_data['onsale'] = 1;
                        $update_data['updated_time'] = date('Y-m-d',time());
                        $update_data['shelve_at'] = date('Y-m-d',time());
                        $update_wheres = array(
                            array('column'=>'id','value'=>$item['id'],'operator'=>'='),
                            array('column'=>'is_delete','value'=>1,'operator'=>'='),
                        );
                        $res = Goods::updateByWhere($update_wheres,$update_data);
                    }
                }
            }
        } catch (Exception $e) {
//            SendMessage::send_sms('13052268638', '日历脚本异常，请及时查看！', '6', 50);
        }
    }
}