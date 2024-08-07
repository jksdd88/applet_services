<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class UserLog extends Model 
{

    protected $table = 'user_log';
    protected $guarded = ['id'];
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';
    
    /**
     * 修改一条记录
     * @return int|修改成功条数
     */
    public static function update_data($id, $data)
    {
        if(!$id || !is_numeric($id))return;
    
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where('id', $id)->update($data);
    }
    
    /**
     * 新注册的50个 1.上海地区 2.免费版商家 开通客服
     * @return array
     */
    static function get_data_of_custserviceWithFree($fields = '*')
    {
        $key = CacheKey::get_cache_data_of_custserviceWithFree();
        //Cache::forget($key);
        $data = Cache::get($key);
        if(!$data){
            $data = array();
            
            $data['count'] = 0;
            $rs_userlog = self::query()->select(\DB::raw($fields))->where('type','=',50)->groupBy('merchant_id')->get();
            $arr = array();
            if(!empty($rs_userlog)){
                foreach ($rs_userlog as $key=>$val){
                    $data['count'] ++;
                    $arr[$val['merchant_id']] = 1;
                }
            }
            $data['merchant_id'] = $arr;
            
            if($data){
                Cache::forever($key, $data);//第三个参数为缓存生命周期 单位：分钟
            }
        }
    
        return $data;
    
    }
    
    /**
     * 上海地区免费商家开通客服,写入记录
     * @return array
     */
    static function insert_data($data)
    {
        $key = CacheKey::get_cache_data_of_custserviceWithFree();
        Cache::forget($key);
        
        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    
    }
}
