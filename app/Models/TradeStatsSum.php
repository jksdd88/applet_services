<?php
/**
 * Created by PhpStorm.
 * User: jiangnan
 * Date: 2017/9/6
 * Time: 18:54
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Cache;

class TradeStatsSum extends Model
{
    protected $connection = 'applet_stats';

    protected $table = 'trade_sum_daily';

    protected $guarded = ['id'];


    public $timestamps = false;

    public function getList($wheres=array(), $offset=null, $limit=null, $sorts=array(), $select=null,$index=array())
    {
        $query = self::query();
        if($index && isset($index['key']) && $index['type']){
            $query->index($index['key'],$index['type']);
        }
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        if(!is_null($select)) {
            $query->addSelect($select);
        }
        if(!is_null($offset)) {
            $query->skip($offset);
        }
        if(!is_null($limit)) {
            $query->take($limit);
        }
        if($sorts){
            foreach($sorts as $sort) {
                $query->orderBy($sort['column'], $sort['direction']);
            }
        }
        return $query->get();
    }

    /**
     * 插入一条记录
     * @return id
     */
    static function insert_data($data)
    {
        $data['created_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }



    /**
     * 查询多条记录
     * @return array
     */
    static function get_data_list($wheres=array(), $fields = '*', $offset = 0, $limit = 10)
    {
        $query = self::query();
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        $data = $query->select(\DB::raw($fields))->skip($offset)->take($limit)->orderBy('day_time', 'desc')->get();
        return json_decode($data,true);
    }
}

