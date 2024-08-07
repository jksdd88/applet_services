<?php
/**
 * 广告投放创意
 * @author ailiya@dodoca.com
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class CampaignAdcreative extends Model
{
    protected $table = 'campaign_adcreative';
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
     * 根据商品id获取单条数据
     * @author renruiqi@dodoca.com
     */
    static function get_data_by_campaign_id($campaign_id, $merchant_id) {
        if (!$campaign_id || !is_numeric($campaign_id))return;
        if (!$merchant_id || !is_numeric($merchant_id))return;
        $key = CacheKey::get_campaign_adcreative_by_campaign_id_key($campaign_id, $merchant_id);
        $data = Cache::get($key);
        if (!$data) {
            $data = self::where(['campaign_id' => $campaign_id, 'merchant_id' => $merchant_id, 'is_delete'=>1])->first();
            if ($data) {
                Cache::put($key, $data, 60);
            }
        }

        return $data;
    }


    /**
     * 修改数据
     * @author renruiqi@dodoca.com
     */
    static function update_data($campaign_id, $merchant_id, $data) {
        if (!$campaign_id || !is_numeric($campaign_id))return;
        if (!$merchant_id || !is_numeric($merchant_id)) return;
        $data['updated_time'] = date('Y-m-d H:i:s');
        $key = CacheKey::get_campaign_adcreative_by_campaign_id_key($campaign_id, $merchant_id);
        Cache::forget($key);

        return self::where(['campaign_id' => $campaign_id, 'merchant_id' => $merchant_id])->update($data);
    }
}
