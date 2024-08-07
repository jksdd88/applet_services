<?php namespace App\Models;

use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Cache;

class UserPriv extends Model {

    protected $table = 'user_priv';
    protected $fillable = ['user_id','priv_id'];
    
    protected $guarded = ['id'];

    
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

    /**
     * 查询一条记录
     * @return array
     */
    
    public static function get_data_by_id($user_id,$merchant_id)
    {
        if(!$user_id || !is_numeric($user_id))return;
    
        $rs_merchant = Merchant::get_data_by_id($merchant_id);
        //dd($rs_merchant);
        //商户已过期
        if($rs_merchant['expire_time']<date('Y-m-d H:i:s')){
            $redis_key = CacheKey::get_UserPriv_by_UserId($user_id,1);
        }
        //商户未过期
        else{
            $redis_key = CacheKey::get_UserPriv_by_UserId($user_id,$rs_merchant['version_id']);
        }
        $data_cache = Cache::get($redis_key);
        Cache::forget($redis_key);
        if(!$data_cache)
        {
            $data = self::query()->where(['user_id'=>$user_id])->select('priv_id')->get();
            if(!empty($data)){
                foreach ($data as $key=>$val){
                    $data_cache[] = $val['priv_id'];
                }
            }
    
            if($data_cache)
            {
                Cache::put($redis_key, $data_cache, 180);
            }
        }
        return $data_cache;
    }
    
    /**
     * 删除用户权限
     * @return int| 删除条数
     */
    static function delete_data($user_id)
    {
        if(!$user_id || !is_numeric($user_id))return;
    
        $rs_merchant = Merchant::get_data_by_id(Auth::user()->merchant_id);
        //商户已过期
        $key = CacheKey::get_UserPriv_by_UserId(Auth::user()->id,1);
        //商户未过期
        $key = CacheKey::get_UserPriv_by_UserId(Auth::user()->id,$rs_merchant['version_id']);
        $data_cache = Cache::get($key);
    
        return self::query()->where('user_id','=',$user_id)->delete();
    }
}