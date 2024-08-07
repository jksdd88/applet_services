<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WeixinPayService;

class WeixinTransfersCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:WeixinTransfersCheck';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Weixin Transfers Check';

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
    public function handle(WeixinPayService $WeixinPayService)
    {
        //
        $WeixinPayService->transfersCheck();
    }
}
