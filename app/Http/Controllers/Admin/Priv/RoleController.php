<?php

namespace App\Http\Controllers\Admin\Priv;

use Hash;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\AdminLog;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Http\Request;
use App\Utils\CacheKey;
use Cache;
use function Predis\select;

class RoleController extends Controller
{
    /**
     * 角色:新增
     * songyongshang@dodoca.com
     * */
    public function postRole(Request $request)
    {
        //角色名称
        if(empty($request['name'])){
            $rt['errcode']=100002;
            $rt['errmsg']='角色名称 不能为空';
            return Response::json($rt);
        }else{
            $data_Role['name'] = $request['name'];
        }
        $data_Role['merchant_id'] = Auth::user()->merchant_id;
        $data_Role['is_delete'] = 1;
        
        //--------------保存数据,记录日志并返回 start-----------------
        DB::beginTransaction();
        try{
            //角色:保存数据
            $rs_Role_id = Role::insert_data($data_Role);
            if(empty($rs_Role_id)){
                $rt['errcode']=100026;
                $rt['errmsg']='角色:保存数据 失败';
                return Response::json($rt);
            }
            //后台操作日志
            $data_UserLog['merchant_id']=Auth::user()->merchant_id;
            $data_UserLog['user_id']=Auth::user()->id;
            //$data_UserLog['type']=30;
            $data_UserLog['action']='post/Role.json';
            $data_UserLog['url']='post/Role.json';
            $data_UserLog['requests']=json_encode($request);
            $data_UserLog['returns']='';
            $data_UserLog['ip']=get_client_ip();
            AdminLog::insert_data($data_UserLog);
        
            DB::commit();
        }catch (\Exception $e) {
            $rt['errcode']=100027;
            $rt['errmsg']='角色:新增 失败';
            return Response::json($rt);
        }
        $rt['errcode']=0;
        $rt['errmsg']='角色:新增 成功';
        return Response::json($rt);
        //--------------保存数据,记录日志并返回 end-----------------
    }
    
    /**
     * 角色:显示列表
     * Author: songyongshang@dodoca.com
     */
    public function getRoles()
    {
        $Roles = Role::where(['merchant_id'=>Auth::user()->merchant_id,'is_delete' => 1])->get();
        
        $rt['errcode']=0;
        $rt['errmsg']='角色:显示列表 成功';
        $rt['data']=!empty($Roles)?$Roles:'';
        return Response::json($rt);
    }
    
    /**
     * 角色:修改
     * songyongshang@dodoca.com
     * */
    public function putRole(Request $request,$id)
    {
        //角色id
        if(empty($id)){
            $rt['errcode']=100002;
            $rt['errmsg']='角色id 不能为空';
            return Response::json($rt);
        }
        //角色名称
        if(empty($request['name'])){
            $rt['errcode']=100002;
            $rt['errmsg']='角色名称 不能为空';
            return Response::json($rt);
        }else{
            $data_Role['name'] = $request['name'];
        }
        $data_Role['merchant_id'] = Auth::user()->merchant_id;

        //--------------保存数据,记录日志并返回 start-----------------
        DB::beginTransaction();
        try{
            //角色:保存数据
            $rs_Role = Role::update_data($id,$data_Role);
            if(empty($rs_Role)){
                $rt['errcode']=100026;
                $rt['errmsg']='角色:保存数据 失败';
                return Response::json($rt);
            }
            //后台操作日志
            $data_UserLog['merchant_id']=Auth::user()->merchant_id;
            $data_UserLog['user_id']=Auth::user()->id;
            //$data_UserLog['type']=31;
            $data_UserLog['action']='put/Role.json';
            $data_UserLog['url']='put/Role.json';
            $data_UserLog['requests']=json_encode($request);
            $data_UserLog['returns']='';
            $data_UserLog['ip']=get_client_ip();
            AdminLog::insert_data($data_UserLog);
    
            DB::commit();
            
            //systybj start
            //角色权限
            $RolePrivKey = CacheKey::get_RolePriv_by_RoleId($id);
            Cache::forget($RolePrivKey);
            //end
        }catch (\Exception $e) {
            $rt['errcode']=100027;
            $rt['errmsg']='角色:修改 失败';
            return Response::json($rt);
        }
        $rt['errcode']=0;
        $rt['errmsg']='角色:修改 成功';
        return Response::json($rt);
        //--------------保存数据,记录日志并返回 end-----------------
    }

    /**
     * 设置角色权限
     */
    public function setRolePrivs(Request $request){
        $role_id = $request->role_id;
        $priv_id = $request->input('priv_id');
        if(empty($role_id)){
            return Response::json(['errcode'=>100028,'errmsg'=>'参数错误']);
        }
        $role = Role::where(['merchant_id'=>Auth::user()->merchant_id,'id'=>$role_id,'is_delete'=>1])->first();
        if(!$role){
            return Response::json(['errcode'=>100029,'errmsg'=>'角色不存在或已删除']);
        }
        $priv_id = 0 == strlen($priv_id) ? [] : explode(',',$priv_id);
        $role->privs()->sync($priv_id);
        //systybj start
        //角色权限
        $RolePrivKey = CacheKey::get_RolePriv_by_RoleId($role_id);
        Cache::forget($RolePrivKey);
        //end
        return Response::json(['errcode'=>0,'errmsg'=>'设置成功']);
    }
    
    /**
     * 角色:删除
     * songyongshang@dodoca.com
     * */
    public function deleteRole(Request $request,$id)
    {
        //角色id
        if(empty($id)){
            $rt['errcode']=100002;
            $rt['errmsg']='角色id 不能为空';
            return Response::json($rt);
        }
        $data_Role['is_delete'] = '-1';
    
        //--------------保存数据,记录日志并返回 start-----------------
        DB::beginTransaction();
        try{
            //角色:保存数据
            $rs_Role = Role::update_data($id,$data_Role);
            if(empty($rs_Role)){
                $rt['errcode']=100026;
                $rt['errmsg']='角色:删除数据 失败';
                return Response::json($rt);
            }
            //后台操作日志
            $data_UserLog['merchant_id']=Auth::user()->merchant_id;
            $data_UserLog['user_id']=Auth::user()->id;
            //$data_UserLog['type']=32;
            $data_UserLog['action']='put/Role.json';
            $data_UserLog['url']='put/Role.json';
            $data_UserLog['requests']=json_encode($request);
            $data_UserLog['returns']='';
            $data_UserLog['ip']=get_client_ip();
            AdminLog::insert_data($data_UserLog);
    
            DB::commit();
        }catch (\Exception $e) {
            $rt['errcode']=100027;
            $rt['errmsg']='角色:删除 失败';
            return Response::json($rt);
        }
        $rt['errcode']=0;
        $rt['errmsg']='角色:删除 成功';
        return Response::json($rt);
        //--------------保存数据,记录日志并返回 end-----------------
    }
}
