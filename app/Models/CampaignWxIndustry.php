<?php
/**
 * 微信广告投放推广-行业表
 * @author ailiya@dodoca.com
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class CampaignWxIndustry extends Model
{
    protected $table = 'campaign_wx_industry';
    public $timestamps = false;

    /**
     * 插入一条记录
     * @return id
     */
    static function insert_data($data)
    {
        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }


    /**
     * 根据id获取单条数据
     */
    static function get_data_by_id($wx_industry_id, $merchant_id) {
        if (!$wx_industry_id || !is_numeric($wx_industry_id))return;
        $key = CacheKey::get_campaign_wx_industry_by_wx_id_key($wx_industry_id);
        $data = Cache::get($key);
        if (!$data) {
            $data = self::where(['wx_industry_id' => $wx_industry_id])->first();
            if ($data) {
                Cache::put($key, $data, 60);
            }
        }

        return $data;
    }


    /**
     * 修改数据
     */
    static function update_data($wx_industry_id, $data) {
        if (!$wx_industry_id || !is_numeric($wx_industry_id))return;
        $data['updated_time'] = date('Y-m-d H:i:s');
        $key = CacheKey::get_campaign_wx_industry_by_wx_id_key($wx_industry_id);
        Cache::forget($key);

        return self::where(['wx_industry_id' => $wx_industry_id])->update($data);
    }
}
