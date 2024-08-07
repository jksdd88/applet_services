<?php
/**
 * 订单主表
 * @author lujingjing@dodoca.com
 */
 
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderAddr extends Model
{

    protected $table='order_addr';

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
    static function insert_data($data)
    {
        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }
	
	/**
	 * 获取单条数据
	 * @author zhangchangchun@dodoca.com
	 */
    static function get_data_by_id($order_id, $fields = '*')
    {
        if(!$order_id || !is_numeric($order_id))return;
		$data = self::query()->select(\DB::raw($fields))->where(['order_id'=>$order_id])->first();
        return $data;
    }
	
}
