<?php

/**
 * 日历表 Model
 * @author 王禹
 * @package App\Models
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class Calendar extends Model
{

    protected $table = 'calendar';
    protected $guarded = ['id'];
    public $timestamps = false;

    //插入一条记录
    static function insert_data($data)
    {

        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);

    }

    //查询一条记录
    static function get_data_by_date($date, $fields = 'data')
    {
        if(!strtotime($date))return;

        $key = CacheKey::get_search_calendar_key($date);
        $data = Cache::get($key);

        if(!$data)
        {
            $data = self::query()->select(\DB::raw($fields))->where('date','=',$date)->first();

            if($data)
            {
                $data = json_decode($data['data'],true);
                Cache::put($key,$data , 1800);//第三个参数为缓存生命周期 单位：分钟
            }

        }
        return $data;
    }

}
