<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveRecordGoods extends Model
{
    /**
     * 与模型关联的数据表。
     *
     * @var string
     */
    protected $table = 'live_record_goods';
    protected $guarded = ['id'];

    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

	static function insert_data($data)
    {
        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }
	
	//查询录播关联商品
	public static function get_data_by_live_record_id($live_record_id,$merchant_id) {
		if (!$live_record_id || !is_numeric($live_record_id)) return;
        if (!$merchant_id || !is_numeric($merchant_id)) return;

		$data = LiveRecordGoods::where('merchant_id', $merchant_id)
                                ->where('live_record_id', $live_record_id)
                                ->where('is_delete', 1)
                                ->get()->toArray();
		
		return $data;
	}


    /**
     * demo 删除一条记录
     * @return int|修改成功条数
     */
    static function delete_data($live_record_id, $goods_id, $merchant_id)
    {
        if(!$live_record_id || !is_numeric($live_record_id))return;
		if(!$goods_id || !is_numeric($goods_id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;
        

		$data = ['is_delete'=>-1];

        return self::query()->where(['live_record_id' => $live_record_id, 'goods_id' => $goods_id, 'merchant_id' => $merchant_id])->update($data);
    }
}
