<?php

/**
 * 优惠劵每日领取量、使用量、领取人数、使用人数统计
 *
 * @package default
 * @author guoqikai
 **/

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DataExportTask;
use Carbon\Carbon;

class DelDataExportTask extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:delDataExportTask';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '删除一月前的导出任务';

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
        $time = Carbon::now()->subMonths(1)->toDateString();
        DataExportTask::where('created_time', '<', $time)->chunk(100, function($list){
            foreach($list as $row){
                DataExportTask::where('id', $row->id)->delete();
            }
        });              
    }
}
