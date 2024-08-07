<?php
/**
 * Created by PhpStorm.
 * User: tang
 * Date: 2017-12-05
 * Time: 15:39
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FormCate extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:FormCate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '初始录入默认超级表单分类';

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
        $cate_name = ['预约', '报名', '投票', '问卷'];
        $data = [];
        foreach ($cate_name as $key => $name) {
            $data[] = [
                'merchant_id' => 0,
                'name' => $name,
                'is_edit' => 1,
                'sort' => $key,
                'is_delete' => 1,
            ];
        }
        $cate_res = \App\Models\FormCate::where('merchant_id', 0)->where('is_delete', 1)->count();
        if (!empty($cate_res)) {
            echo 'Failed: 已有默认数据，请核对数据库中已有默认数据是否正确！';
        } else {
            $res = DB::table('form_cate')->insert($data);
            if (!empty($res)) {
                echo 'Success';
            } else {
                echo 'Failed:' . json_encode($res, JSON_UNESCAPED_UNICODE);
            }
        }
    }

}
