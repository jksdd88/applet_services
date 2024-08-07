<?php
/**
 * 订单主表
 * @author lujingjing@dodoca.com
 */
 
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderRefund extends Model
{

    protected $table='order_refund';

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
     * @author wangshiliang@dodoca.com
     */
    static function insert_data($data)
    {
        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }

    /**
     * 修改数据
     * @author wangshiliang@dodoca.com
     */
    static function update_data($id, $merchant_id, $data)
    {
        if(!$id || !is_numeric($id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where(['id'=>$id,'merchant_id'=>$merchant_id])->update($data);
    }

    /**
     * 修改数据
     * @author 王禹
     */
    static function update_data_status_distrib($id, $merchant_id, $data)
    {
        if(!$id || !is_numeric($id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where(['id'=>$id,'merchant_id'=>$merchant_id,'status_distrib'=> 0])->update($data);
    }

    /**
     * 获取单条数据
     * @author wangshiliang@dodoca.com
     */
    static function get_data_by_id($id, $merchant_id)
    {
        if(!$id || !is_numeric($id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;

        return self::query()->where(['id'=>$id,'merchant_id'=>$merchant_id])->first();
    }
    /**
     * 获取单条数据
     * @author wangshiliang@dodoca.com
     */
    static function select_one($key, $val){
        $data = self::query()->where([$key => $val ])->first();
        return $data  ? $data->toArray() : [];
    }

    /**
     * 获取订单已退积分
     * @author wangshiliang@dodoca.com
     */

    static function select_integral($order_id,$goods_id,$spec_id){
        $data =  self::query()->where(['order_id' => $order_id, 'goods_id' => $goods_id,'spec_id'=>$spec_id  ])->whereNotIn('status',[REFUND_REFUSE,REFUND_CANCEL]) ->lists('integral')->toArray();
        return is_array($data) && !empty($data) ?array_sum($data):0;
    }


    /**
     * 获取订单已退总金额（不要动）
     * @author 王禹
     */
    static function get_amount_by_orderid($order_id, $merchant_id){

        if(!$order_id || !is_numeric($order_id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;

        return self::query()->where('order_id','=',$order_id)
            ->where('merchant_id','=',$merchant_id)
            ->where('status','=',REFUND_FINISHED)
            ->sum('amount');
    }

    /**
     * count记录条数
     * @return int|count
     */
    static function get_data_count($wheres=array(),$whereNotIns=array())
    {
        $query = self::query();
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }

        foreach($whereNotIns as $whereNotIn) {
            $query->whereNotIn($whereNotIn['column'], $whereNotIn['value']);
        }
        return $query->count();
    }
}
