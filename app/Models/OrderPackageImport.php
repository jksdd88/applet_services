<?php
/**
 * Created by PhpStorm.
 * User: qinyuan
 * Date: 2017/10/10
 * Time: 14:56
 * 批量发货日志表
 */

namespace App\Models;
use Illuminate\Database\Eloquent\Model;


class OrderPackageImport extends Model
{
    protected $table='order_package_import';

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
     * @author qinyuan@dodoca.com
     */
    static function get_data_by_id($id, $fields = '*')
    {
        if(!$id || !is_numeric($id))return;
        $data = self::query()->select(\DB::raw($fields))->where(['id'=>$id])->first();
        return $data;
    }

    /**
     * 查询多条记录
     * @author qinyuan@dodoca.com
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
}