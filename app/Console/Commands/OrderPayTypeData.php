<?php
/**
 * 改变订单支付方式数据
 * @author wangshen@dodoca.com
 * @cdate 2018-4-16
 * 
 */
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\OrderInfo;

class OrderPayTypeData extends Command {
	
	
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:OrderPayTypeData';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '改变订单支付方式数据';

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
        
        $data = [
            'pay_type' => 1
        ];
        
        $rs = OrderInfo::query()->where('pay_type','=',0)
                                ->whereIn('status', [ORDER_REFUND_CANCEL,ORDER_TOSEND,ORDER_FORPICKUP,ORDER_SEND,ORDER_SUCCESS])
                                ->update($data);
        if($rs){
            print_r('success:'.$rs);
        }else{
            print_r('error:'.$rs);
        }
        
    }

}

