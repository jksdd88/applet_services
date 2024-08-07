<?php
/**
 * 测试队列
 * @author zhangchangchun@dodoca.com
 */
namespace App\Jobs;

use App\Jobs\Job;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Utils\CommonApi;

class TestJob extends Job implements SelfHandling, ShouldQueue
{
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($str)
    {
        $this->str = $str;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
		//记录异常
		$except = [
			'activity_id'	=>	1,
			'data_type'		=>	'test_job',
			'content'		=>	'测试队列',
		];
		$result = CommonApi::errlog($except);
		\Log::info('test-job:time->'.date("Y-m-d H:i:s").',result->'.$this->str.$result);
    }
	
}
