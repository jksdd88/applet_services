<?php
/**
 * 虚拟商品订单，失效自动完成
 * @author wangshen@dodoca.com
 * @cdate 2018-3-15
 * 
 */
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\GoodsVirtual;
use App\Models\OrderGoods;
use App\Models\OrderInfo;

class VirtualComplete extends Command {
	
	
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:VirtualComplete';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '虚拟商品订单，失效自动完成';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
		OrderInfo::select(['id','merchant_id','order_goods_type'])->where('order_goods_type','=',ORDER_GOODS_VIRTUAL)->where(['status'=>ORDER_SEND])->orderBy('id','asc')->chunk(300, function ($list) {
			if($list) {
				$dump = [];
				foreach($list as $key => $oinfo) {
				    //虚拟商品订单，商品数据
				    $ordergoods = OrderGoods::get_data_by_order($oinfo['id'], $oinfo['merchant_id'], 'id,quantity,goods_id');
				    $goods_virtual_info = GoodsVirtual::get_data_by_id($ordergoods['goods_id'], $oinfo['merchant_id']);
				    
				    //指定有效期，且当前时间过了有效期
				    if($goods_virtual_info['time_type'] == 1 && $goods_virtual_info['end_time'] < date('Y-m-d H:i:s')){
				        
				        $data = [
							'status'		=>	ORDER_SUCCESS,
							'finished_time'	=>	date("Y-m-d H:i:s"),
						];
						$result = OrderInfo::update_data($oinfo['id'],$oinfo['merchant_id'],$data);
						if($result) {
							$dump[] = $oinfo['id'];
						}
				    }
				    
				}
				print_r($dump);
			}
		});
    }

}
