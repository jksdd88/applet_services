<?php

namespace App\Http\Controllers\admin\Userlog;

use App\Http\Controllers\Controller;
use App\Models\UserLog;

use Illuminate\Support\Facades\Response;
use Illuminate\Http\Request;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class UserlogController extends Controller
{
    /**
     * 修改商家日志
     * songyongshang@dodoca.com
     * */
    public function putUserlog(Request $request)
    {
        if(empty($request['merchant_id'])){
            $rt['errcode']=100002;
            $rt['errmsg']='请填写 商家id';
            return Response::json($rt);
        }
        //此免费商家是否开通客服的商家
        $data_userlog = UserLog::where(['merchant_id'=>$request['merchant_id'],'type'=>50])->first();
        
        if( !empty($data_userlog) ){
            $key = CacheKey::get_cache_data_of_custserviceWithFree();
            Cache::forget($key);
            
            $data2_UserLog['type']=51;
            $data2_UserLog['ip']=get_client_ip();
            UserLog::update_data($data_userlog['id'], $data2_UserLog);
            
            $rt['errcode']=0;
            $rt['errmsg']='手工调整日志记录 成功！';
            return Response::json($rt);
        }else{
            $rt['errcode']=0;
            $rt['errmsg']='无此记录！';
            return Response::json($rt);
        }
            
        
    }

}
