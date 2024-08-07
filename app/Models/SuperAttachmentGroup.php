<?php
/**
 * Created by PhpStorm.
 * User: lizhenheng
 * Date: 2017-12-26
 * Time: 下午 03:16
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuperAttachmentGroup extends Model {

    protected $table = 'super_attachment_group';
    protected $guarded = ['id'];
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';
    /*
    type类型: 
        0：普通分组 
        1：素材专用分组(二级分组)

    */



    /**
     * demo 查询一条记录
     * @return array
     */

    static function get_data_by_id($id, $fields = '*')
    {
        if(!$id || !is_numeric($id))return;

        $data = self::query()->select(\DB::raw($fields))->where('id','=',$id)->first();

        return $data;

    }
}