<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ActivityAlertService;

class ActivityAlert extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:ActivityAlert';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Activity Alert';

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
        $activityAlert = new ActivityAlertService();
        $activityAlert->setConfig('seckill')->activity();
        $activityAlert->setlog();
        $activityAlert->setConfig('fightgroup')->activity();
        $activityAlert->setlog();
        $activityAlert->setConfig('bargain')->activity();
        $activityAlert->setlog();
    }
}
