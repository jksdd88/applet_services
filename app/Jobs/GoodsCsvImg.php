<?php

/*导入商品的主图上传到七牛云*/

namespace App\Jobs;

use App\Jobs\Job;
use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\GoodsCsv;


use App\Services\AttachmentService;


class GoodsCsvImg extends Job implements SelfHandling, ShouldQueue
{
    public function __construct($id=0,$merchant_id=0)
    {
        $this->id = $id;
        $this->merchant_id = $merchant_id;
    }


    public function handle(AttachmentService $AttachmentService)
    {

        //查询
        $img = GoodsCsv::select('id','img','is_delete')->where('id',$this->id)->get()->toArray();
        if(!empty($img[0]['img'])) {
                $imgs = json_decode($img[0]['img'],true);
                $imgs_qiniu = [];
                if(!empty($imgs) && is_array($imgs)){
                    foreach($imgs as $k=>$v) {
                        if($attach = $AttachmentService->takesOnlinePictureNew(['url'=>$v],$this->merchant_id)) {
                            //七牛返回参数日志
                            \Log::info('goods-csv-qiniu:merchant_id->'.$this->merchant_id.',goods_csv_id->'.$this->id.',taobao_img_url->'.$v.',attach_qiniu->'.json_encode($attach,JSON_UNESCAPED_UNICODE));

                            if(!isset($attach['error']) && isset($attach['url'])) {
                                $imgs_qiniu[] = $attach['url'];
                            }
                        }
                    }

                    $imgs_json = isset($imgs_qiniu) ? json_encode($imgs_qiniu) : '';

                    GoodsCsv::updateByWhere(
                        array(
                            array('column' => 'id', 'value' => $this->id, 'operator' => '='),
                            array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '=')
                        ),
                        array('state' => 1, 'img' => $imgs_json)
                    );
                }

        }
    }

}
