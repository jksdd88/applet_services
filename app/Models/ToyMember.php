<?php

namespace App\Models;

use App\Services\ToyService;
use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class ToyMember extends Model
{
    protected $connection = 'applet_cust';

    protected $table = 'toy_member';
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

    /**
     * 新增参与会员
     * @param $data
     * @return mixed
     * @author: tangkang@dodoca.com
     */
    static public function insert_data($data)
    {
        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        $data['is_delete'] = 1;
        return self::insertGetId($data);
    }

    /**
     * 会员有用娃娃数量和被助力次数
     * @param $member_id
     * @param $merchant_id
     * @return Model|null|void|static
     * @author: tangkang@dodoca.com
     */
    static public function get_data_by_id($member_id, $merchant_id)
    {
        if (!$member_id || !is_numeric($member_id)) return;
        if (!$merchant_id || !is_numeric($merchant_id)) return;
        $data = self::query()->where('merchant_id', '=', $merchant_id)
            ->where('member_id', '=', $member_id)
            ->where('is_delete', '=', 1)
            ->first();
        return $data;
    }

    /**
     * 更新玩家信息
     * @param $id
     * @param $merchant_id
     * @param $data 更新的数据
     * @param $result 抓中娃娃更新数据
     * @param $exchange 兑换娃娃更新数据--减抓中的娃娃数量
     * @return int|void
     * @author: tangkang@dodoca.com
     */
    static public function update_data($id, $merchant_id, $data, $result = false, $exchange = false)
    {
        if (!$id || !is_numeric($id)) return;
        if (!$merchant_id || !is_numeric($merchant_id)) return;

        $data['updated_time'] = date('Y-m-d H:i:s');
        if ($result) {//抓中更新金额，10块钱了
            $res = self::find($id);//单用户单条记录不存在并发问题
            if ($res['toy_qty_total'] % 3 == 0) {//抓中更新金额，10块钱了
                $tag_key = CacheKey::get_toy_tag_by_merchant_id_id_key($merchant_id);
                $key = CacheKey::get_toy_money_by_member_id_key($merchant_id);
                Cache::tags($tag_key)->forget($key);
            }
        }
        $query = self::query()->where('id', '=', $id);
        if ($exchange && isset($data['toy_qty']) && is_object($data['toy_qty']) && $data['toy_qty'] instanceof \Illuminate\Database\Query\Expression) {
            $query->where($data['toy_qty'], '>=', 0);
        }
        return $query->update($data);
    }

    /**
     * 获取已抓去到的价值金额（不满5000有时返回0）
     * @param $merchant_id
     * @return bool|float|int|mixed|void
     * @author: tangkang@dodoca.com
     */
    static public function get_money($merchant_id)
    {
        if (!$merchant_id || !is_numeric($merchant_id)) return;
        $tag_key = CacheKey::get_toy_tag_by_merchant_id_id_key($merchant_id);
        $key = CacheKey::get_toy_money_by_member_id_key($merchant_id);
        $money = Cache::tags($tag_key)->get($key);
        if (!$money) {
            $total_toy = ToyMember::where('merchant_id', $merchant_id)
                ->where('toy_qty_total', '>=', ToyService::$exchange_rules['ten'])
                ->where('is_delete', 1)
                ->limit(500)->get(['toy_qty_total']);
            if ($total_toy->isEmpty()) return 0;
//            if ($total_toy <= 166) return 0;//166*30(9个娃娃)=4980，167个人中6个价值20或者167、168各中3个
            $money = 0;//大于等于5000，概率为0
            foreach ($total_toy as $value) {
                $remainder = floor($value['toy_qty_total'] / ToyService::$exchange_rules['ten']);
                $money += ($remainder * 10);
            }
            Cache::tags($tag_key)->put($key, $money, 10080);
        }
        return $money;
    }
}
