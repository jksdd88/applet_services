<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DesignTemplatePage extends Model
{

    protected $table = 'design_template_page';

    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

    /**
     * 插入数据
     */
    static function insert_data($data)
    {
        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }


    /**
     * @param $group_id
     * @param string $fields
     */
    public static function get_template_id($template_id,$fields = '*')
    {
        if (!$template_id) return;
        $data = self::query()->select(\DB::raw($fields))->where(['template_id' => $template_id])->get();
        return $data;
    }

    /**
     * @param $group_id
     * @param string $fields
     */
    public static function get_templatepage_id($page_id,$fields = '*')
    {
        if (!$page_id) return;
        $data = self::query()->select(\DB::raw($fields))->where(['id' => $page_id])->first();
        return $data;
    }


    public static function get_template_by_page($offset,$limit,$fields = '*')
    {
        $query = self::query()->select(\DB::raw($fields));
        $count = $query->count();
        $list = $query->skip($offset)->take($limit)->orderBy('sort', 'asc')->get();
        if($list){
            $list = $list->toArray();
        }
        return [
            '_count' => $count,
            'data' => $list,
        ];
        
    }


    /**
     * 查询多条记录
     * @return array
     */
    static function get_data_list($wheres = array(), $fields = '*')
    {
        $query = self::query();
        if (isset($wheres[0]['column'])) {
            foreach ($wheres as $where) {
                $query->where($where['column'], $where['operator'], $where['value']);
            }
        } else {
            $query->where($wheres);
        }
        $data = $query->select(\DB::raw($fields))->orderBy('listorder', 'asc')->get();
        return json_decode($data, true);
    }

    static function update_data($id,  $data)
    {
        if(!$id || !is_numeric($id))return;

        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where('id' , $id)->update($data);
    }
}
