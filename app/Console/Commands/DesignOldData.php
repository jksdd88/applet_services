<?php
/**
 * Created by PhpStorm.
 * Date: 2017/11/27
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
class DesignOldData extends Command{

    protected $signature = 'command:DesignOldData';


    public function __construct(WeixinService $WeixinService)
    {
        parent::__construct();
        $this->WeixinService = $WeixinService;
    }

    public function handle()
    {
        //查询所有数据
        $data = ShopDesign::select('*')->where('wxinfo_id','')->where('title','')->where('bg_color','')->where('bg_img','')->get();
        if($data){
            $data = $data->toArray();
            foreach ($data as $k => $v) {
                DB::beginTransaction();
                try {
                    //根据商户id查找小程序id
                    $wxinfo_id = WeixinInfo::select('id')->where('merchant_id',$v['merchant_id'])->where('type',1)->where('status',1)->first();
                    if(!$wxinfo_id){
                        $wxinfo = $this->WeixinService->createApp($v['merchant_id']);
                        if(isset($wxinfo['id']) && !empty($wxinfo['id']) ){
                            $wxinfo_id = $wxinfo;
                        }else{
                            throw new \Exception('小程序id生成有误');
                        }
                    }
                    //组织数据并更新原有数据
                    $design_data['wxinfo_id'] =  $wxinfo_id['id'];
                    $design_data['title'] =  "首页";
                    ShopDesign::update_data($v['id'],$v['merchant_id'],$design_data);
                    $comdata = array('shop_design_id' => $v['id'],
                                     'type' => 'groupGoods',
                                     'content' => '{"style":1,"sort":1,"show_ele":{"name":true,"price":true,"sales":true,"cart":true},"tag":[{"id":0,"name":"\u6240\u6709\u5546\u54c1"}],"goods_num":10,"font_bgcolor":"#000"}',
                                     'listorder' => 2
                                );
                    ShopDesignComponent::insert_data($comdata);
                    WeixinInfo::update_data('id', $wxinfo_id['id'], ['index_id' => $v['id']]);
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    echo '失败';
                }
            }
        }
    }
}