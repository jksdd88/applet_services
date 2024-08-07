<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/9/5
 * Time: 16:02
 * Author: DuMing
 */
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use PhpSpec\Exception\Exception;

class GoodsTag extends Model{

    protected $table = 'goods_tag';

    protected $guarded = ['id'];

    const CREATED_AT = 'created_time';

    const UPDATED_AT = 'updated_time';

    /**
     * 添加数据
     * @return id
     */
    static function insert_data($data)
    {

        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);

    }

    /**
     * @return array
     */

    static function get_data_by_id($wheres, $fields = '*')
    {
        $query = self::query();
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        $data = $query->select(\DB::raw($fields))->first();
        return $data;

    }


    /**
     * @param $id
     * @param $data
     * @return bool|int
     * @Author  DuMing
     */
    static function update_data($id ,$data)
    {
        if(!$id || !is_numeric($id))return;
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where('id','=',$id)->update($data);

    }

    /**
     * @return int
     */

    static function delete_data($id)
    {
        if(!$id || !is_numeric($id))return;

        $data['is_delete'] = -1;
        $data['updated_time'] = date('Y-m-d H:i:s');

        return self::query()->where('id','=',$id)->update($data);
    }

    /**
     * @return int|count
     */
    static function get_data_count($wheres=array())
    {
        $query = self::query();
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        return $query->count();
    }

    /***
     * @param array $wheres   查询条件
     * @param string $fields  查询字段
     * @param int $offset     从第几条开始
     * @param int $limit      每页多少条
     * @return mixed
     * @Author  DuMing
     */
    static function get_data_list($wheres=array(), $fields = '*',$order=array('column'=>'create_time','direct'=>'desc'),$offset = 0, $limit = 10)
    {
        try {
            $query = self::query();
            foreach ($wheres as $where) {
                $query->where($where['column'], $where['operator'], $where['value']);
            }
            $query = $query->orderBy($order['column'], $order['direct']);
            $data['data'] = $query->select(\DB::raw($fields))->skip($offset)->take($limit)->get();
            $data['errcode'] = 0;
            $data['errmsg'] = '';
        }catch (Exception $e){
            $data['errcode'] = 500;
            $data['errmsg'] = '服务器端出现错误';
            $data['data'] = [];
        }
        return $data;
    }

    /***
     * @param $wheres
     * @Author  DuMing
     */
    static function getDataByWhere($wheres,$fields="*"){
        $query = self::query();
        foreach($wheres as $where) {
            if($where['operator'] == 'in'){
                $query->whereIn($where['column'], $where['value']);
            }else{
                $query->where($where['column'], $where['operator'], $where['value']);
            }
        }
        $data = $query->select($fields)->get();
        return $data;

    }

    static function updateByWhere($wheres, $update_data)
    {
        $query = self::query();
        foreach ($wheres as $where) {
            if($where['operator'] == 'in'){
                $query->whereIn($where['column'], $where['value']);
            }else{
                $query->where($where['column'], $where['operator'], $where['value']);
            }
        }
        $res = $query->update($update_data);
        return $res;

    }

}