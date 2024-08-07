<?php
/**
 * 订单主表
 * @author lujingjing@dodoca.com
 */
 
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderRefundLog extends Model
{

    protected $table='order_refund_log';

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

    static function insert_data($data)
    {
        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }
}
