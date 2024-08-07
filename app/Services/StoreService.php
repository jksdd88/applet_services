<?php
/**
 * 门店服务类
 * Date: 2017-10-25
 * Time: 15:20
 */
namespace App\Services;

use App\Models\Store;

class StoreService {

    /**
     * 通过商户id获取门店列表
     * chang
     * 20171025 12:00
     * $merchant_id  商户ID  必传参数
     * $name              门店名称
     * $enabled           门店状态：1->开启
     * $extract_enabled   门店自提点状态： 1->开启
     * $offset       页码，默认第一页
     * $limit   每页数量，默认100条
     */
    public function storeList($merchant_id=0,$name="",$offset=0,$limit=100,$enabled="",$extract_enabled="")
    {
        if((int)$merchant_id < 1){
            return ['errcode' => 0, 'errmsg' => '商户id有误','_count'=>"",'data' => ""];exit;
        }

        $wheres = [
            [
                'column'   => 'merchant_id',
                'operator' => '=',
                'value'    => $merchant_id,
            ],
            [
                'column'   => 'is_delete',
                'operator' => '=',
                'value'    => 1,
            ]
        ];
        if($name != ""){
            $wheres[] = ['column' => 'name','operator' => 'like', 'value' => '%' . trim($name) . '%'];
        }
        if(isset($enabled) && $enabled == 1){
            $wheres[] = ['column' => 'enabled','operator' => '=', 'value' => '1'];
        }
        if(isset($extract_enabled) && $extract_enabled == 1){
            $wheres[] = ['column' => 'extract_enabled','operator' => '=', 'value' => '1'];
        }


        $fields=" id,merchant_id,name,mobile,office_at,country_name,province_name,city_name,district_name,address,contacts,lng,lat,enabled,extract_enabled,open_time,close_time,city_enabled";
        $count = Store::get_data_count($wheres);
        $data = Store::get_data_list($wheres, $fields, $offset, $limit,"id","desc");
        foreach($data as $key=>$val){
            $data[$key]['office_at'] = explode(',',$val['office_at']);
            $data[$key]['open_time_close_time'] = "[".$val['open_time'].",".$val['close_time']."]";
        }

        return ['errcode' => 0, 'errmsg' => '获取门店列表成功','_count'=>$count,'data' => $data];
    }

}