<?php

/**
 * 投票日志表 Model demo
 * @author songyongshang@dodoca.com
 * @package App\Models
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoteLog extends Model
{

    protected $table = 'vote_log';
    protected $guarded = ['id'];
    public $timestamps = false;
    protected $connection = 'applet_cust';

    //插入一条记录
    static function insert_data($data) {
        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }

    /**
     * 修改一条记录
     */
    static function update_data($vote_log_id, $data) {
        if(!$vote_log_id || !is_numeric($vote_log_id))return;
    
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where('id','=',$vote_log_id)->update($data);
    }
    
    /**
     * 查询一条记录
     */
    static function get_data_by_id($id, $fields = '*') {
        if(!$id || !is_numeric($id))return;

        $data = self::query()->select(\DB::raw($fields))->where('id','=',$id)->first();

        return $data;
    }

    /**
     * count记录条数
     * @return int| count
     */
    static function get_data_count($wheres=array()) {
        $query = self::query();
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        return $query->count();
    }

    /**
     * 查询多条记录
     * @return array
     */
    static function get_data_list($wheres=array(), $fields = '*', $offset = 0, $limit = 10) {
        $query = self::query();
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        $data = $query->select(\DB::raw($fields))->skip($offset)->take($limit)->get();
        return json_decode($data,true);
    }
}
