<?php
/**
 * 包裹字表
 * @author zhangchangchun@dodoca.com
 */
 
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class OrderPackageItem extends Model
{

    protected $table='order_package_item';

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
	 * 获取单条数据
	 * @author zhangchangchun@dodoca.com
	 */
    static function get_data_by_id($id, $fields = '*')
    {
        if(!$id || !is_numeric($id))return;
		$data = self::query()->select(\DB::raw($fields))->where(['id'=>$id])->first();
        return $data;
    }
	
	/**
     * 查询多条记录
     * @return array
     */
    static function get_data_list($wheres=array(), $fields = '*', $offset = 0, $limit = 100)
    {
        $query = self::query();
        if(isset($wheres[0]['column'])) {
			foreach($wheres as $where) {
				$query->where($where['column'], $where['operator'], $where['value']);
			}
		} else {
			$query->where($wheres);
		}
        $data = $query->select(\DB::raw($fields))->skip($offset)->take($limit)->orderBy('id', 'desc')->get();
        return json_decode($data,true);
    }
	
	/**
	 * 获取包裹商品
	 * @author zhangchangchun@dodoca.com
	 */
    static function get_data_by_package_id($package_id, $order_id)
    {
        if(!$package_id || !is_numeric($package_id))return;
        if(!$order_id || !is_numeric($order_id))return;
		$key = CacheKey::get_goods_by_package_id($package_id, $order_id);
        $data = Cache::get($key);
		if(!$data) {
			$data = self::query()->where(['package_id'=>$package_id,'order_id'=>$order_id])->get();
			if($data) {
				Cache::put($key, $data, 60);
			}
		}
        return $data;
    }
	
}
