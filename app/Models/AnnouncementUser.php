<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017-11-29
 * Time: 下午 04:49
 */
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AnnouncementUser extends Model
{
    /**
     * 与模型关联的数据表。
     *
     * @var string
     */
    protected $table = 'announcement_user';//会员每天统计

    /**
     * 指定是否模型应该被戳记时间。
     *
     * @var bool
     */
    public $timestamps = false;

//    protected $connection = 'applet';


    /**
     * 查询一条记录
     * @return array
     */

    static function get_one_data($wheres)
    {
        $query = self::query();
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        $data=$query->first();
        return $data;

    }

    /**
     * 根据条件更新一条数据
     * @return int|修改成功条数
     */

    static function update_data_by_where( $wheres = [], $data)
    {
        $query = self::query();
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        return $query->update($data);
    }

}