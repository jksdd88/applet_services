<?php

namespace App\Console\Commands;

use App\Services\WeixinPayService;
use Illuminate\Console\Command;

class WeixinTransfers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:WeixinTransfers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Weixin Transfers';

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
        $WeixinPayService->transfers();
    }
}
