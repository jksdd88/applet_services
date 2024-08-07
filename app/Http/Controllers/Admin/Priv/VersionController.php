<?php

namespace App\Http\Controllers\Admin\Priv;

use Hash;
use App\Http\Controllers\Controller;
use App\Models\Version;
use App\Models\VersionPriv;
use App\Models\AdminLog;
use App\Models\Merchant;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\Request;
use function Predis\select;

use App\Services\UserPrivService;

class VersionController extends Controller
{

    /**
     * 版本:新增
     * songyongshang@dodoca.com
     * */
    public function postVersion(Request $request)
    {
        //版本名称
        if(empty($request['name'])){
            $rt['errcode']=100002;
            $rt['errmsg']='版本名称 不能为空';
            return Response::json($rt);
        }else{
            $data_Version['name'] = $request['name'];
        }
        $data_Version['is_delete'] = 1;

        //--------------保存数据,记录日志并返回 start-----------------
        DB::beginTransaction();
        try{
            //版本:保存数据
            $rs_Version_id = Version::insert_data($data_Version);
            if(empty($rs_Version_id)){
                $rt['errcode']=100026;
                $rt['errmsg']='版本:保存数据 失败';
                return Response::json($rt);
            }
            //后台操作日志
            $data_UserLog['merchant_id']=Auth::user()->merchant_id;
            $data_UserLog['user_id']=Auth::user()->id;
            //$data_UserLog['type']=34;
            $data_UserLog['action']='post/Version.json';
            $data_UserLog['url']='post/Version.json';
            $data_UserLog['requests']=json_encode($request);
            $data_UserLog['returns']='';
            $data_UserLog['ip']=get_client_ip();
            AdminLog::insert_data($data_UserLog);

            DB::commit();
        }catch (\Exception $e) {
            $rt['errcode']=100027;
            $rt['errmsg']='版本:新增 失败';
            //dd($e);
            return Response::json($rt);
        }
        $rt['errcode']=0;
        $rt['errmsg']='版本:新增 成功';
        return Response::json($rt);
        //--------------保存数据,记录日志并返回 end-----------------
    }

    /**
     * 版本:显示列表
     * Author: songyongshang@dodoca.com
     */
    public function getVersions()
    {
        //$Versions = Version::where(['is_delete' => 1])->get();
        $arr_version = config('version');
        $version=array();
        if(!empty($arr_version)){
            foreach ($arr_version as $key=>$val){
                $version[] = array(
                    'id'=>$key,
                    'name'=>$val['alias'],
                    'grade_point'=>$val['grade_point'],
                );
            }
        }
        $rt['errcode']=0;
        $rt['errmsg']='角色:显示列表 成功';
        $rt['data']=$version;
        return Response::json($rt);
    }

    /**
     * 版本:修改
     * songyongshang@dodoca.com
     * */
    public function putVersion(Request $request,$id)
    {
        //模块id
        if(empty($id)){
            $rt['errcode']=100002;
            $rt['errmsg']='模块id 不能为空';
            return Response::json($rt);
        }
        //版本名称
        if(empty($request['name'])){
            $rt['errcode']=100002;
            $rt['errmsg']='版本名称 不能为空.';
            return Response::json($rt);
        }else{
            $data_Version['name'] = $request['name'];
        }

        //--------------保存数据,记录日志并返回 start-----------------
        DB::beginTransaction();
        try{
            //版本:保存数据
            $rs_Version = Version::update_data($id,$data_Version);
            if(empty($rs_Version)){
                $rt['errcode']=100026;
                $rt['errmsg']='版本:保存数据 失败';
                return Response::json($rt);
            }
            //后台操作日志
            $data_UserLog['merchant_id']=Auth::user()->merchant_id;
            $data_UserLog['user_id']=Auth::user()->id;
            //$data_UserLog['type']=35;
            $data_UserLog['action']='put/Version.json';
            $data_UserLog['url']='put/Version.json';
            $data_UserLog['requests']=json_encode($request);
            $data_UserLog['returns']='';
            $data_UserLog['ip']=get_client_ip();
            AdminLog::insert_data($data_UserLog);

            DB::commit();
        }catch (\Exception $e) {
            $rt['errcode']=100027;
            $rt['errmsg']='版本:修改 失败';
            dd($e);
            return Response::json($rt);
        }
        $rt['errcode']=0;
        $rt['errmsg']='版本:修改 成功';
        return Response::json($rt);
        //--------------保存数据,记录日志并返回 end-----------------
    }
    
    /**
     * 版本-权限:新增
     * songyongshang@dodoca.com
     * */
    public function postVersionPriv(Request $request)
    {
        //模块id
        if(empty($request['version_id'])){
            $rt['errcode']=100002;
            $rt['errmsg']='版本id 不能为空';
            return Response::json($rt);
        }else{
            $data_VersionPriv['version_id'] = $request['version_id'];
        }
        //版本名称
        if(empty($request['priv_id'])){
            $rt['errcode']=100002;
            $rt['errmsg']='权限id 不能为空.';
            return Response::json($rt);
        }else{
            $data_VersionPriv['priv_id'] = $request['priv_id'];
        }
    
        //--------------保存数据,记录日志并返回 start-----------------
        DB::beginTransaction();
        try{
            //版本:保存数据
            $rs_Version = VersionPriv::insert_data($data_VersionPriv);
            if(empty($rs_Version)){
                $rt['errcode']=100026;
                $rt['errmsg']='版本-权限:保存数据 失败';
                return Response::json($rt);
            }
            //后台操作日志
            $data_UserLog['merchant_id']=Auth::user()->merchant_id;
            $data_UserLog['user_id']=Auth::user()->id;
            //$data_UserLog['type']=36;
            $data_UserLog['action']='put/Version.json';
            $data_UserLog['url']='put/Version.json';
            $data_UserLog['requests']=json_encode($request);
            $data_UserLog['returns']='';
            $data_UserLog['ip']=get_client_ip();
            AdminLog::insert_data($data_UserLog);
    
            DB::commit();
        }catch (\Exception $e) {
            $rt['errcode']=100027;
            $rt['errmsg']='版本:新增 失败';
            dd($e);
            return Response::json($rt);
        }
        $rt['errcode']=0;
        $rt['errmsg']='版本-权限:新增 成功';
        return Response::json($rt);
        //--------------保存数据,记录日志并返回 end-----------------
    }

    /**
     * 版本:删除
     * songyongshang@dodoca.com
     * */
    public function deleteVersion(Request $request,$id)
    {
        //模块id
        if(empty($id)){
            $rt['errcode']=100002;
            $rt['errmsg']='模块id 不能为空';
            return Response::json($rt);
        }
        $data_Version['is_delete'] = '-1';

        //--------------保存数据,记录日志并返回 start-----------------
        DB::beginTransaction();
        try{
            //版本:保存数据
            $rs_Version = Version::update_data($id,$data_Version);
            if(empty($rs_Version)){
                $rt['errcode']=100026;
                $rt['errmsg']='版本:删除数据 失败';
                return Response::json($rt);
            }
            //后台操作日志
            $data_UserLog['merchant_id']=Auth::user()->merchant_id;
            $data_UserLog['user_id']=Auth::user()->id;
            //$data_UserLog['type']=37;
            $data_UserLog['action']='put/Version.json';
            $data_UserLog['url']='put/Version.json';
            $data_UserLog['requests']=json_encode($request);
            $data_UserLog['returns']='';
            $data_UserLog['ip']=get_client_ip();
            AdminLog::insert_data($data_UserLog);

            DB::commit();
        }catch (\Exception $e) {
            $rt['errcode']=100027;
            $rt['errmsg']='版本:删除 失败';
            return Response::json($rt);
        }
        $rt['errcode']=0;
        $rt['errmsg']='版本:删除 成功';
        return Response::json($rt);
        //--------------保存数据,记录日志并返回 end-----------------
    }
    
    /**
     * 版本:修改版本权限
     * songyongshang@dodoca.com
     * */
    public function putVersionPriv(Request $request)
    {
        //版本id
        if(empty($request['version_id'])){
            $rt['errcode']=100002;
            $rt['errmsg']='版本id 不能为空';
            return Response::json($rt);
        }
        //权限id
        if(empty($request['priv_id'])){
            $rt['errcode']=100002;
            $rt['errmsg']='权限id 不能为空';
            return Response::json($rt);
        }
        //操作
        if(empty($request['op'])){
            $rt['errcode']=100002;
            $rt['errmsg']='操作 不能为空';
            return Response::json($rt);
        }

        $arr_diff = array();
        if($request['op'] == 'add'){
            $arr_version_priv = array();
            $rs_version_priv = VersionPriv::where(['version_id'=>$request['version_id']])->whereIn('priv_id',$request['priv_id'])->select('priv_id')->get();
            if(!empty($rs_version_priv)){
                foreach ($rs_version_priv as $key=>$val){
                    $arr_version_priv[]=$val['priv_id'];
                }
            }
            $arr_diff = array_diff($request['priv_id'], $arr_version_priv);
        }else if($request['op'] =='strike'){
            $rs_version_priv = VersionPriv::where(['version_id'=>$request['version_id']])->whereIn('priv_id',$request['priv_id'])->select('priv_id')->get();
            if(!empty($rs_version_priv)){
                foreach ($rs_version_priv as $key=>$val){
                    $arr_diff[]=$val['priv_id'];
                }
            }
        }

        //--------------保存数据,记录日志并返回 start-----------------
        DB::beginTransaction();
        try{
            //版本:保存数据
            $rs_success = 0;
            if(! empty($arr_diff) ){
                foreach ( $arr_diff as $key=>$val ){
                    if( VersionPriv::update_data($request['version_id'],$val,$request['op']) ){
                        $rs_success++;
                    }
                }
                
                if( $rs_success!=count($arr_diff) ){
                    $rt['errcode']=100026;
                    $rt['errmsg']='版本-权限:保存数据 失败,请刷新页面重试';
                    return Response::json($rt);
                }
            }
            
            //后台操作日志
            //$data_UserLog['merchant_id']=Auth::user()->merchant_id;     //super后台没有
            $data_UserLog['user_id']=Session::get('super_user.id');
            //$data_UserLog['type']=38;
            $data_UserLog['action']='put/Version.json';
            $data_UserLog['url']='put/Version.json';
            $data_UserLog['requests']=json_encode($request);
            $data_UserLog['returns']='';
            $data_UserLog['ip']=get_client_ip();
            AdminLog::insert_data($data_UserLog);
    
            DB::commit();
        }catch (\Exception $e) {
            DB::rollBack();
            
            $rt['errcode']=100027;
            $rt['errmsg']='版本-权限:修改 失败';
            return Response::json($rt);
        }
        
        $rt['errcode']=0;
        $rt['errmsg']='版本-权限:修改 成功';
        return Response::json($rt);
        //--------------保存数据,记录日志并返回 end-----------------
    }
    
    /**
     * 版本:查看版本权限
     * songyongshang@dodoca.com
     * */
    public function getVersionPriv($version_id)
    {
        //版本id
        if(empty($version_id)){
            $rt['errcode']=100002;
            $rt['errmsg']='版本id 不能为空';
            return Response::json($rt);
        }
        $merchant_info = Merchant::get_data_by_id(Auth::user()->merchant_id);
        if(empty($merchant_info['version_id'])){
            $rt['errcode'] = 'no_priv';
            $rt['errmsg'] = '此商户的版本有问题';
            $rt['data'] = '';
            return $rt;
        }
        //版本:保存数据
        if($merchant_info['expire_time'] < date('Y-m-d H:i:s')){
            $rs_Version = VersionPriv::get_data_by_id(1);
        }else{
            $rs_Version = VersionPriv::get_data_by_id($version_id);
        }
        
        
        $rt['errcode']=0;
        $rt['errmsg']='版本-权限:显示列表 成功';
        $rt['data'] = $rs_Version;
        return Response::json($rt);

    }

    /**
     * 版本  查看版本权限(Super后台)
    */
    public function getSuperVersionPriv($version_id)
    {
        //版本id
        if(empty($version_id)){
            $rt['errcode']=100002;
            $rt['errmsg']='版本id 不能为空';
            return Response::json($rt);
        }
        //版本:保存数据
        $rs_Version = VersionPriv::get_data_by_id($version_id);
        $rt['errcode']=0;
        $rt['errmsg']='版本-权限:显示列表 成功';
        $rt['data'] = $rs_Version;
        return Response::json($rt);

    }


    public function getUserPriv(){
        $user = UserPrivService::getUserPrivs();
        dd($user);
    }
}
