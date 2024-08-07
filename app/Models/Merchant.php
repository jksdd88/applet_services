<?php namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;

use App\Utils\CacheKey;
use Cache;

class Merchant extends Model implements AuthenticatableContract, CanResetPasswordContract {

    use Authenticatable, CanResetPassword;
    
    protected $table = 'merchant';
    protected $fillable = ['username'];
    
    protected $hidden = array('password');
    protected $guarded = ['id'];
    
    public static $rules = [
        'username' => 'required',
        'password'=>'required',
    ];
    
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';
    public $timestamps = true;

    /**
     * 查询一条记录
     * @return array
     */
    public static function get_data_by_id($id) {
        if(!$id || !is_numeric($id))
            return;

        $key = CacheKey::get_merchant_by_id_key($id);
        $data = Cache::get($key);
        //Cache::forget($key);
        if( !$data || !isset($data['is_demo']) || !$data['ddcid'] ) {
            $data = self::query()->where('id', $id)->first();

            if($data) {
                Cache::forget($key);
                Cache::put($key, $data, 60);
            }
        }
        return $data;
    }

    /**
     * 修改一条记录
     * @return int|修改成功条数
     */
    public static function update_data($id, $data)
    {
        if(!$id || !is_numeric($id))return;

        $key = CacheKey::get_merchant_by_id_key($id);
        Cache::forget($key);
        self::clearDataOne($id);

        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where('id', $id)->update($data);
    }

    /**
     * 删除一条记录
     * @return int|删除条数
     */
    static function delete_data($id)
    {
        if(!$id || !is_numeric($id))return;

        $key = CacheKey::get_merchant_by_id_key($id);
        Cache::forget($key);
        self::clearDataOne($id);
        
        $data['updated_time'] = date('Y-m-d H:i:s');

        return self::query()->where('id', $id)->update($data);
    }

    /**
     * count记录条数
     * @return int|count
     */
    static function get_data_count($wheres = array()) {
        $query = self::query();
        if (isset($wheres[0]['column'])) {
            foreach ($wheres as $where) {
                if ($where['operator'] == 'in') {
                    $query->whereIn($where['column'], $where['value']);
                } else {
                    $query->where($where['column'], $where['operator'], $where['value']);
                }
            }
        } else {
            $query->where($wheres);
        }
        return $query->count();
    }

    //递增余额
    static function increment_data($id ,$field ,$val)
    {
        if(!$id || !is_numeric($id))return;
    
        $key = CacheKey::get_merchant_by_id_key($id);
        Cache::forget($key);
    
        //->where(\DB::raw("$field + $val")  ,'=',0)
        return self::query()->where('id','=',$id)
            ->increment($field, $val);
    
    }
    
    //递减余额
    static function decrement_data($id ,$field ,$val)
    {
        if(!$id || !is_numeric($id))return;
    
        $key = CacheKey::get_merchant_by_id_key($id);
        Cache::forget($key);
    
        return self::query()->where('id','=',$id)
            ->where($field, '>=', abs($val))
            ->where(\DB::raw("$field - ".abs($val))  ,'>=',0)
            ->decrement($field, abs($val));
    }

    //ddcid
    static function getDataOne($ddcId){
        $key = CacheKey::get_merchant_by_id_key($ddcId);
        $data = Cache::get($key);
        if(!$data || !isset($data['id'])){
            $data =  self::query()->where('ddcid','=',$ddcId)->first(['id','ddcid','ddcsecret']);
            if($data){
                Cache::put($key, $data, 60);
            }
        }
        return $data;
    }

    static function clearDataOne($id){
        $data = self::query()->where('id', $id)->first(['ddcid']);
        if(isset($data['ddcid'])){
            $key = CacheKey::get_merchant_by_id_key($data['ddcid']);
            Cache::forget($key);
        }
        return true;
    }
}