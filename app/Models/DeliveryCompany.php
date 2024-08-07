<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class DeliveryCompany extends Model {

    protected $table = 'delivery_company';
    protected $guarded = ['id'];
    public $timestamps = false;
	
	 /**
     * 获取一条数据
     * @return array
     */
    static function get_data_by_id($id)
    {
        if (!$id || !is_numeric($id)) return;
        $key = CacheKey::get_delivery_by_id($id);
        $data = Cache::get($key);
        if (!$data) {
            $data = self::query()->where('id', '=', $id)->first();
            if ($data) {
                Cache::put($key, $data, 60);
            }
        }
        return $data;

    }
	
}