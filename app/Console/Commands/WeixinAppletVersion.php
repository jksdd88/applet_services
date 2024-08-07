<?php

/**
 * 版本自动升级
 *
 * @package default
 * @author wangshiliang@dodoca.com
 **/

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WeixinService;

class WeixinAppletVersion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:WeixinAppletVersion';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '版本自动升级';

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
    public function handle(WeixinService $WeixinService)
    {
        $WeixinService->versionUpdate();
    }
}
