<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Cache;

class VersionPriv extends Model {

    protected $table = 'version_priv';
    protected $fillable = ['version_id','priv_id'];
    
    protected $guarded = ['id'];

    
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

    /**
     * 插入一条记录
     * @return id
     */
    static function insert_data($data)
    {
    
        
        return self::insertGetId($data);
    }
    
    /**
     * 查询一条记录
     * @return array
     */
    
    static function get_data_by_id($version_id)
    {
        if(!$version_id || !is_numeric($version_id))return;
    
        $redis_key = CacheKey::get_VersionPriv_by_VersionId($version_id);
        $data = Cache::get($redis_key);
        //Cache::forget($redis_key);
        if(!$data)
        {
            $data_cache = self::query()->where(['version_id'=>$version_id])->get();
            if(!empty($data_cache)){
                foreach ($data_cache as $key=>$val){
                    $data[] = $val['priv_id'];
                }
            }
            if(!empty($data))
            {
                Cache::forever($redis_key, $data);
            }
        }
        return $data;
    }
    
    /**
     * 修改版本权限
     * @return array
     */
    
    static function update_data($version_id,$priv_id,$op)
    {
        if(!$version_id || !is_numeric($version_id))return;
        if(!$priv_id || !is_numeric($priv_id))return;
    
        $key = CacheKey::get_VersionPriv_by_VersionId($version_id);
        //dd($key);
        Cache::forget($key);
        if($op=='strike'){
            return self::query()->where(['version_id'=>$version_id,'priv_id'=>$priv_id])->delete();
        }else if($op=='add'){
            $data['version_id'] = $version_id;
            $data['priv_id'] = $priv_id;
            return self::insertGetId($data);
        }
        
    }
}