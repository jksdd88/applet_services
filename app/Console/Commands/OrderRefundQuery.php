<?php
/**
 * @ 订单退款查询（重新发起退款、退款状态查询）
 * @ author zhangchangchun@dodoca.com
 * @ time 2017-09-04
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Utils\SendMessage;
use App\Models\OrderRefundApply;
use App\Services\OrderJobService;

class OrderRefundQuery extends Command {
	
	use DispatchesJobs;
	
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:OrderRefundQuery';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '订单退款查询';

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
    public function handle(OrderJobService $OrderJobService) {
		OrderRefundApply::select(['id'])->where('status', '<>', 1)->chunk(50, function($data) use ($OrderJobService) {
            foreach ($data as $refund) {
                $OrderJobService->OrderRefundCommand($refund);
            }
        });
    }

}
