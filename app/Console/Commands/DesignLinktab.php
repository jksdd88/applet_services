<?php
/**
 * Created by PhpStorm.
 * Date: 2017/1/25
 * Author: gongruimin
 * 装修老数据修改
 */
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Utils\SendMessage;
use DB;
use App\Models\ShopDesign;
use App\Models\ShopDesignComponent;
use App\Models\WeixinInfo;
use App\Services\WeixinService;
class DesignLinktab extends Command{

    protected $signature = 'command:DesignLinktab';


    public function __construct(WeixinService $WeixinService)
    {
        parent::__construct();
        $this->WeixinService = $WeixinService;
    }

    public function handle()
    {
         //查询所有数据
        $data = ShopDesign::select('*')->where('link_tab','')->where('is_delete',1)->get();
        if($data){
            $data = $data->toArray();
            foreach ($data as $k => $v) {
                $link_tab = "L".date('YmdHis',strtotime($v['created_time'])).str_pad(mt_rand(1,99999),5,'0',STR_PAD_LEFT);
                //查询这个是否存在
                $link = ShopDesign::select('link_tab')->where(array('link_tab' => $link_tab))->first();
                if($link){
                    return;
                }
                //组织数据并更新原有数据
                $design_data['link_tab'] =  $link_tab;
                ShopDesign::update_data($v['id'],$v['merchant_id'],$design_data);
            }
        }
    }
}