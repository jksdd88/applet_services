<?php
/**
 * Created by PhpStorm.
 * User: tang
 * Date: 2017-09-20
 * Time: 10:45
 */
namespace App\Http\Controllers\Com\Restore;

use App\Http\Controllers\Controller;

use App\Utils\CacheKey;
use Cache;
use Illuminate\Http\Request;

class CacheController extends Controller
{
    /**
     * 清指定key缓存
     * @param Request $request method:获取Cache键值的函数名称，param：函数参数，逗号分隔
     * @return string
     * @author: tangkang@dodoca.com
     */
    public function flush(Request $request)
    {
        $method = $request->get('method', null);
        $param = $request->get('param', null);
        $param = explode(',', $param);
        if (empty($method)) return '获取缓存key的方法名不能为空';
        if (empty($param)) return '获取缓存key方法的参数不能为空';
//        dd($method);
        $key = call_user_func_array([CacheKey::class, $method], $param);
//        dd($key);
        //根据key清空缓存
//        $key=$request->get('key',null);
//        if(empty($key)) return 'key不能为空';
        if (empty($key)) return '生成key失败';
        if (!Cache::has($key)) return 'key不存在';
        $res = Cache::forget($key);
        if ($res === false) return 'Failed';
        if ($res === true) return 'Success';
    }

    /**
     * 清空购物车
     * @param Request $request
     * @author: tangkang@dodoca.com
     */
    public function flushCart(Request $request)
    {
        $member_id = $request->get('member_id', 0);
        $merchant_id = $request->get('merchant_id', 0);
        $key = CacheKey::get_cart_by_member_key($member_id, $merchant_id);
        Cache::flush($key);
        echo '清空:' . Cache::get($key);
    }

    /**
     * 获取购物车列表
     * @param Request $request
     * @author: tangkang@dodoca.com
     */
    public function getCart(Request $request)
    {
        $member_id = $request->get('member_id', 0);
        $merchant_id = $request->get('merchant_id', 0);
        $key = CacheKey::get_cart_by_member_key($member_id, $merchant_id);
        $res=Cache::get($key);
        print_r($res);
        die;
    }
    
    /**
     * 查缓存key值
     * @param Request $request method:获取Cache键值的函数名称，param：函数参数，逗号分隔
     * @return string
     * @author: tangkang@dodoca.com
     */
    public function findkey(Request $request)
    {
        $method = $request->get('method', null);
        $param = $request->get('param', null);
        $param = explode(',', $param);
        if (empty($method)) return '获取缓存key的方法名不能为空';
        if (empty($param)) return '获取缓存key方法的参数不能为空';
        //        dd($method);
        $key = call_user_func_array([CacheKey::class, $method], $param);
        //        dd($key);
        //根据key清空缓存
        //        $key=$request->get('key',null);
        //        if(empty($key)) return 'key不能为空';
        if (empty($key)) return '生成key失败';
        if (!Cache::has($key)) return 'key不存在';
        return $key;
    }
}
