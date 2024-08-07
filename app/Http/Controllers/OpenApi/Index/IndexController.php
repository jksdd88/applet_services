<?php

namespace App\Http\Controllers\OpenApi\Index;

use App\Http\Requests;
use App\Models\Merchant;
use App\Models\ApiLimit;
use App\Models\User;
use App\Utils\CacheKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Response;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class IndexController extends Controller
{
    private $request ;
    private $cacheTime = 120;//分钟
    private $limit = [
        'index_login' => 110000,
    ];

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function index(){
        $user = $this->request->user();
        $data = $this->request->all();
        return Response::json(['errcode'=>0, 'errmsg'=>'ok', 'data'=>$data,'user'=>$user ]);
    }

    public function login()
    {
        $validator = Validator::make($this->request->input(),[
            'app_id' => ['required','alpha_dash','string'],
            'app_secret'=>['required','alpha_dash','string','size:32'],
        ],[
            'app_id.required'=>'请传入开发者id参数',
            'app_id.alpha_dash'=>'开发者id参数格式有误',
            'app_id.string'=>'开发者id参数格式有误',
            'app_secret.required'=>'请传入开发者秘钥参数',
            'app_secret.alpha_dash'=>'开发者秘钥格式有误',
            'app_secret.string'=>'开发者秘钥格式有误'
        ]);

        if($validator -> fails()){
            return Response::json(['errcode'=>20001, 'errmsg'=>$validator->messages()->first()]);
        }
        $ddcId = $this->request->input('app_id');
        $ddcSecret = $this->request->input('app_secret');
        $merchantInfo = Merchant::getDataOne($ddcId);//'id','ddcid','ddcsecret'
        if(!$merchantInfo || !isset($merchantInfo->id)){
            return Response::json(['errcode'=>20002, 'errmsg'=>'请传入开发者id']);
        }
        if($merchantInfo->ddcsecret != $ddcSecret){
            return Response::json(['errcode'=>20002, 'errmsg'=>'请传入开发者秘钥']);
        }
        $merchantInfo = $merchantInfo->toArray();
        //请求次数限制
        $result = ApiLimit::apiLimit($merchantInfo['id'],'index_login', $this->limit['index_login']);
        if(!$result){
            return Response::json(['errcode'=>20003, 'errmsg'=>'今日请求次数已达上限']);
        }
        //清理旧token
        $oldTokenkey = Cache::get(CacheKey::get_open_api($merchantInfo['id']));
        Cache::forget($oldTokenkey);
        //新token
        $token = sha1(Crypt::encrypt($ddcSecret.date('YmdH')));
        $tokenkey =  CacheKey::get_open_api($token);
        Cache::put($tokenkey,$merchantInfo,$this->cacheTime);
        Cache::put(CacheKey::get_open_api($merchantInfo['id']),$tokenkey,$this->cacheTime);
        return Response::json(['errcode'=>0, 'errmsg'=>'成功', 'access_token'=>$token]);
    }

    public function refresh(){
        $token = $this->request->input('access_token','');
        $merchantInfo = Cache::get( CacheKey::get_open_api($token));
        if(!$merchantInfo || !isset($merchantInfo['id'])){
            return Response::json(['errcode'=>20004, 'errmsg'=>'access_token 已过期']);
        }
        //请求次数限制
        $result = ApiLimit::apiLimit($merchantInfo['id'],'index_login', $this->limit['index_login']);
        if(!$result){
            return Response::json(['errcode'=>20003, 'errmsg'=>'今日请求次数已达上限']);
        }
        //新token
        $newtoken = sha1(Crypt::encrypt($merchantInfo['ddcsecret'].date('YmdH')));
        $tokenkey =  CacheKey::get_open_api($newtoken);
        Cache::put($tokenkey,$merchantInfo,$this->cacheTime);
        Cache::put(CacheKey::get_open_api($merchantInfo['id']),$tokenkey,$this->cacheTime);
        //清理旧token
        Cache::forget(CacheKey::get_open_api($token));
        return Response::json(['errcode'=>0, 'errmsg'=>'ok', 'access_token'=>$newtoken]);
    }



}
