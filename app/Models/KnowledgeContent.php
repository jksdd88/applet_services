<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class KnowledgeContent extends Model
{
    protected $table = 'knowledge_content';
    protected $guarded = ['id'];
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';
    const STATUS_ONSHELVE = 1;//上架
    const STATUS_UNSHELVE = 2;//下架
    const TYPE_ARTICLE = 1;//文章
    const TYPE_AUDIO = 2;//音频
    const TYPE_VIDEO = 3;//视频

    /**
     * 新增
     * @param $data
     * @return int
     * @author tangkang@dodoca.com
     */
    static function insert_data($data)
    {
        $data['is_delete'] = 1;
        return self::insertGetId($data);
    }

    /**
     * 获取一条数据
     * @param $id
     * @param $merchant_id
     * @return int
     * @author tangkang@dodoca.com
     */
    static function get_data_by_id($id, $merchant_id)
    {
        if (!$id || !is_numeric($id)) return 0;
        if (!$merchant_id || !is_numeric($merchant_id)) return 0;
        $key = CacheKey::get_k_content_by_id($id, $merchant_id);
        $data = Cache::get($key);
        if (!$data) {
            $data = self::query()->whereId($id)->whereMerchantId($merchant_id)->first();

            if ($data) {
                Cache::put($key, $data, 60);//第三个参数为缓存生命周期 单位：分钟
            }
        }
        return $data;
    }

    /**
     * 更新
     * @param $id
     * @param $merchant_id
     * @param $data
     * @return int|void
     * @author tangkang@dodoca.com
     */
    static function updata_data($id, $merchant_id, $data)
    {
        if (!$id || !is_numeric($id)) return 0;
        if (!$merchant_id || !is_numeric($merchant_id)) return 0;
        if (empty($data)) return 0;
        $key = CacheKey::get_k_content_by_id($id, $merchant_id);
        Cache::forget($key);
        return self::query()->whereId($id)->whereMerchantId($merchant_id)->update($data);
    }

    /**
     * 自增订阅量
     * @param $id
     * @param $merchant_id
     * @author: tangkang@dodoca.com
     */
    static function incCsale($id, $merchant_id)
    {
        if (!$id || !is_numeric($id)) return;
        if (!$merchant_id || !is_numeric($merchant_id)) return;
        $key = CacheKey::get_k_content_by_id($id, $merchant_id);
        Cache::forget($key);
        return self::query()->whereId($id)->whereMerchantId($merchant_id)->increment('csale');
    }

    /**
     * 删除
     * @param $id
     * @param $merchant_id
     * @return int
     * @author tangkang@dodoca.com
     */
    static function delete_data($id, $merchant_id)
    {
        if (!$id || !is_numeric($id)) return 0;
        if (!$merchant_id || !is_numeric($merchant_id)) return 0;
        $data = [
            'is_delete' => -1,
        ];
        $key = CacheKey::get_k_content_by_id($id, $merchant_id);
        Cache::forget($key);

        return self::query()->whereId($id)->whereMerchantId($merchant_id)->update($data);

    }

    static function get_data_lists($merchant_id)
    {
        if (!$merchant_id || !is_numeric($merchant_id)) return 0;
        $query = self::query()->where();
        $data['count'] = $query->count();
        $data['lists'] = $query->get();

        return $data;
    }

    /*
    $key = CacheKey::get_holiday_marketing_tag_by_id($id);
        $data = Cache::get($key);
        if(!$data) {
            $data = self::query()->select(\DB::raw($fields))->where('id','=',$id)->first();

            if($data) {
                Cache::forever($key, $data);//第三个参数为缓存生命周期 单位：分钟
            }
        }

        $key = CacheKey::get_forminfo_by_id_key($id, $merchant_id);
        Cache::forget($key);
    */
}
