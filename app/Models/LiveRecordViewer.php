<?php
/**
 * 录播观众表
 */
 
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Cache;

class LiveRecordViewer extends Model
{
    /**
     * 与模型关联的数据表。
     *
     * @var string
     */
    protected $table = 'live_record_viewer';

    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

	static function insert_data($data)
    {
        return self::insertGetId($data);
    }

    /**
     * 查询一条记录
     * @return array
     */
    static function get_data_by_id($member_id, $record_id)
    {
        if(!$member_id || !is_numeric($member_id))return;
        if(!$record_id || !is_numeric($record_id))return;

        $key = CacheKey::get_live_record_viewer_by_id($member_id, $record_id);
        $data = Cache::get($key);
        if(!$data)
        {
            $data = self::query()->where(['member_id' => $member_id, 'record_id' => $record_id])->first();
            if($data)
            {
                Cache::put($key, $data, 60);
            }
        }
        return $data;
    }
	
	 /**
     * demo 修改一条记录
     * @return int|修改成功条数
     */

    static function update_data($member_id, $record_id, $data)
    {
        if(!$member_id || !is_numeric($member_id))return;
        if(!$record_id || !is_numeric($record_id))return;

        $key = CacheKey::get_live_record_viewer_by_id($member_id, $record_id);
        Cache::forget($key);
        return self::query()->where(['member_id' => $member_id, 'record_id' => $record_id])->update($data);
    }
	
}
