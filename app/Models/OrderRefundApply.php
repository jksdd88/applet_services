<?php
/**
 * 第三方退款申请表
 * @author lujingjing@dodoca.com
 */
 
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderRefundApply extends Model
{

    protected $table='order_refund_apply';

    protected $guarded = ['id'];

    /**
     * The name of the "created at" column.
     *
     * @var string
     */
    const CREATED_AT = 'created_time';

    /**
     * The name of the "updated at" column.
     *
     * @var string
     */
    const UPDATED_AT = 'updated_time';
	
	/**
     * 插入数据
     * @author zhangchangchun@dodoca.com
     */
    static function insert_data($data) {
        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }
	
	/**
     * 获取单条数据
     * @author zhangchangchun@dodoca.com
     */
    static function get_data_by_id($id, $merchant_id, $fields = '*') {
        if (!$id || !is_numeric($id))
            return;
        if (!$merchant_id || !is_numeric($merchant_id))
            return;
        $data = self::query()->select(\DB::raw($fields))->where(['id' => $id, 'merchant_id' => $merchant_id])->first();
        return $data;
    }
	
    /**
     * 修改数据
     * @author zhangchangchun@dodoca.com
     */
    static function update_data($id, $merchant_id, $data) {
        if (!$id || !is_numeric($id))
            return;
        if (!$merchant_id || !is_numeric($merchant_id))
            return;
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where(['id' => $id, 'merchant_id' => $merchant_id])->update($data);
    }
}
