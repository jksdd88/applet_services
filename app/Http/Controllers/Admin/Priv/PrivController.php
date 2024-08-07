<?php

namespace App\Http\Controllers\Admin\Priv;

use Hash;
use App\Http\Controllers\Controller;
use App\Models\Priv;
use App\Models\VersionPriv;
use App\Models\UserPriv;
use App\Models\AdminLog;
use App\Models\Merchant;
use App\Models\UserRole;
use App\Models\RolePriv;
use App\Models\Role;
use App\Models\User;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Http\Request;
use function Predis\select;
use function Qiniu\json_decode;
use function GuzzleHttp\json_encode;

class PrivController extends Controller
{
    /**
     * 权限模块:新增
     * songyongshang@dodoca.com
     * */
    public function postPriv(Request $request)
    {
        //父级id
        $data_Priv['parent_id'] = isset($request['parent_id'])?$request['parent_id']:'0';
        //权限模块名称
        if(empty($request['name'])){
            $rt['errcode']=100002;
            $rt['errmsg']='权限模块名称 不能为空';
            return Response::json($rt);
        }else{
            $data_Priv['name'] = $request['name'];
        }
        //权限模块代码
        if(empty($request['code'])){
            $rt['errcode']=100002;
            $rt['errmsg']='权限模块代码 不能为空';
            return Response::json($rt);
        }else{
            $data_Priv['code'] = $request['code'];
        }
        $data_Priv['is_delete'] = 1;

        //--------------保存数据,记录日志并返回 start-----------------
        DB::beginTransaction();
        try{
            //权限模块:保存数据
            $rs_Priv_id = Priv::insert_data($data_Priv);
            if(empty($rs_Priv_id)){
                $rt['errcode']=100026;
                $rt['errmsg']='权限模块:保存数据 失败';
                return Response::json($rt);
            }
            //后台操作日志
            $data_UserLog['merchant_id']=Auth::user()->merchant_id;
            $data_UserLog['user_id']=Auth::user()->id;
            //$data_UserLog['type']=28;
            $data_UserLog['action']='post/priv.json';
            $data_UserLog['url']='post/priv.json';
            $data_UserLog['requests']=json_encode($request);
            $data_UserLog['returns']='';
            $data_UserLog['ip']=get_client_ip();
            AdminLog::insert_data($data_UserLog);

            DB::commit();
        }catch (\Exception $e) {
            $rt['errcode']=100027;
            $rt['errmsg']='权限模块:新增 失败';
            //dd($e);
            return Response::json($rt);
        }
        $rt['errcode']=0;
        $rt['errmsg']='权限模块:新增 成功';
        return Response::json($rt);
        //--------------保存数据,记录日志并返回 end-----------------
    }

    /**
     * 权限模块:显示 所有 模块/子模块/功能 列表
     * Author: songyongshang@dodoca.com
     */
    public function getAllPrivs()
    {
        $privs = $this->_get_all_nodes_by_level();
        //$privs = $this->filterExcludeCode($privs);
        if(!empty($privs) && is_array($privs)){
            $rt['errcode']=0;
            $rt['errmsg']='获取权限列表 成功';
            $rt['data']=$privs;
        }else {
            $rt['errcode']=100003;
            $rt['errmsg']='获取权限列表 失败';
            $rt['data']='';
        }
        
        return Response::json($rt);
    }
    public function _get_all_nodes_by_level($parent_id=0, $lv=1, &$result=array())
    {
        $nodes = Priv::where(['is_delete'=>1])->select(['id','parent_id','name','code'])->get();
        $arr_rt=array();
        $arr_rt=$this->arrange($nodes);
        return $arr_rt;
    }

    /**
     * 权限模块:给商户的角色或者管理员设置权限时显示的权限列表
     * Author: songyongshang@dodoca.com
     */
    public function getVersionPrivs()
    {
        //dd('a');
        $privs = $this->_get_version_nodes_by_level();
        //$privs = $this->filterExcludeCode($privs);
        $rt['errcode']=0;
        $rt['errmsg']='获取权限列表 成功';
        $rt['data']=$privs;
        return $rt;
    }
    public function _get_version_nodes_by_level($parent_id=0, $lv=1, &$result=array())
    {
        //dd(Auth::user()->merchant_id);
        $rs_merchant = Merchant::get_data_by_id(Auth::user()->merchant_id);
        $version_id = $rs_merchant['version_id'];
        //dd($version_id);
        $nodes = VersionPriv::where(['version_priv.version_id'=>$version_id,'priv.is_delete'=>1])
                ->leftjoin('priv','priv.id','=','version_priv.priv_id')
                ->select(['priv.id','priv.parent_id','priv.name','priv.code'])->get();
        $arr_rt=array();
        $arr_rt=$this->arrange($nodes);
        //dd($arr_rt);
        return $arr_rt;
    }

    /**
     * 权限模块:管理员的权限列表
     * Author: songyongshang@dodoca.com
     */
    public function getUserPrivs()
    {
        $privs = $this->_get_user_nodes_by_level();
        //$privs = $this->filterExcludeCode($privs);
        return $privs;
    }
    public function _get_user_nodes_by_level($parent_id=0, $lv=1, &$result=array())
    {
        //dd(Auth::user()->id);
        $nodes = UserPriv::where(['user_priv.user_id'=>Auth::user()->id,'priv.is_delete'=>1])
            ->leftjoin('priv','priv.id','=','user_priv.priv_id')
            ->select(['priv.id','priv.parent_id','priv.name','priv.code']);

        $nodes = UserRole::where(['user_role.user_id'=>Auth::user()->id,'priv.is_delete'=>1])
            ->leftjoin('role_priv','role_priv.role_id','=','user_role.role_id')
            ->leftjoin('priv','priv.id','=','role_priv.priv_id')
            ->select(['priv.id','priv.parent_id','priv.name','priv.code'])
            ->union($nodes)
            ->get();
        //dd(UserRole);
        $arr_rt=array();
        $arr_rt=$this->arrange($nodes);
        return $arr_rt;
    }
    public function arrange($nodes,$parent_id=0,$lv=1,&$result=array()){

        if(!empty($nodes)){
            foreach ($nodes as $key=>$val){
                if($val['parent_id']==$parent_id){
                    $val['lv']=$lv;
                    $result[] = $val;
                    unset($nodes[$key]);
                    $this->arrange($nodes,$val['id'],$lv+1,$result);
                }
            }
        }
        //dd($result);
        return $result;
    }
    public function filterExcludeCode($privs)
    {
        $exludeCodes = $this->getExcludePrivs();
        $rs = [];
        foreach($privs as $_priv) {
            if(!in_array($_priv['code'],$exludeCodes)) {
                $rs[] = $_priv;
            }
        }
        return $rs;
    }

    /**
     * 权限模块:修改
     * songyongshang@dodoca.com
     * */
    public function putPriv(Request $request,$id)
    {
        //模块id
        if(empty($id)){
            $rt['errcode']=100002;
            $rt['errmsg']='模块id 不能为空';
            return Response::json($rt);
        }
        //父级id
        $data_Priv['parent_id'] = isset($request['parent_id'])?$request['parent_id']:'0';
        //权限模块名称
        if(empty($request['name'])){
            $rt['errcode']=100002;
            $rt['errmsg']='权限模块名称 不能为空.';
            return Response::json($rt);
        }else{
            $data_Priv['name'] = $request['name'];
        }
        //权限模块代码
        // if(empty($request['code'])){
        //     $rt['errcode']=100002;
        //     $rt['errmsg']='权限模块代码 不能为空';
        //     return Response::json($rt);
        // }else{
        //     $data_Priv['code'] = $request['code'];
        // }

        //--------------保存数据,记录日志并返回 start-----------------
        DB::beginTransaction();
        try{
            //权限模块:保存数据
            $rs_Priv = Priv::update_data($id,$data_Priv);
            if(empty($rs_Priv)){
                $rt['errcode']=100026;
                $rt['errmsg']='权限模块:保存数据 失败';
                return Response::json($rt);
            }
            //后台操作日志
            $data_UserLog['merchant_id']=Auth::user()->merchant_id;
            $data_UserLog['user_id']=Auth::user()->id;
            //$data_UserLog['type']=29;
            $data_UserLog['action']='put/priv.json';
            $data_UserLog['url']='put/priv.json';
            $data_UserLog['requests']=json_encode($request);
            $data_UserLog['returns']='';
            $data_UserLog['ip']=get_client_ip();
            AdminLog::insert_data($data_UserLog);

            DB::commit();
        }catch (\Exception $e) {
            $rt['errcode']=100027;
            $rt['errmsg']='权限模块:修改 失败';
            dd($e);
            return Response::json($rt);
        }
        $rt['errcode']=0;
        $rt['errmsg']='权限模块:修改 成功';
        return Response::json($rt);
        //--------------保存数据,记录日志并返回 end-----------------
    }

    /**
     * 权限模块:删除
     * songyongshang@dodoca.com
     * */
    public function deletePriv(Request $request,$id)
    {
        //模块id
        if(empty($id)){
            $rt['errcode']=100002;
            $rt['errmsg']='模块id 不能为空';
            return Response::json($rt);
        }
        $data_Priv['is_delete'] = '-1';

        //--------------保存数据,记录日志并返回 start-----------------
        DB::beginTransaction();
        try{
            //权限模块:保存数据
            $rs_Priv = Priv::update_data($id,$data_Priv);
            if(empty($rs_Priv)){
                $rt['errcode']=100026;
                $rt['errmsg']='权限模块:删除数据 失败';
                return Response::json($rt);
            }
            //后台操作日志
            $data_UserLog['merchant_id']=Auth::user()->merchant_id;
            $data_UserLog['user_id']=Auth::user()->id;
            //$data_UserLog['type']=30;
            $data_UserLog['action']='put/priv.json';
            $data_UserLog['url']='put/priv.json';
            $data_UserLog['requests']=json_encode($request);
            $data_UserLog['returns']='';
            $data_UserLog['ip']=get_client_ip();
            AdminLog::insert_data($data_UserLog);

            DB::commit();
        }catch (\Exception $e) {
            $rt['errcode']=100027;
            $rt['errmsg']='权限模块:删除 失败';
            return Response::json($rt);
        }
        $rt['errcode']=0;
        $rt['errmsg']='权限模块:删除 成功';
        return Response::json($rt);
        //--------------保存数据,记录日志并返回 end-----------------
    }

    /**
     * 优化权限:权限列表
     * songyongshang@dodoca.com
     * */
    public function get_all_priv(Request $request)
    {
        if( !isset($request['mode']) || !in_array($request['mode'], array('role','version','user','priv','login_user')) ){
            $rt['errcode']=120020;
            $rt['errmsg']='获取权限列表,mode不正确';
            return $rt;
        }
        $arr_op = array();
        $arr_op['mode'] = $request['mode'];
        
        //1 所有权限
        $arr_priv = Priv::get_all_priv_data();
        $arr_priv = json_decode(json_encode($arr_priv),true);
        //2 版本权限
        $version_id = 0;
        if( in_array($request['mode'], array('role','user','login_user')) ){
            $rs_merchant = Merchant::get_data_by_id(Auth::user()->merchant_id);
            $version_id = $rs_merchant['version_id'];
        }else if( in_array($request['mode'], array('version')) ){
            if( !isset($request['version_id']) || empty($request['version_id']) ){
                $rt['errcode']=120020;
                $rt['errmsg']='获取权限列表,版本id不能为空';
                return $rt;
            }
            $version_id = $request['version_id'];
        }
        if( !empty($version_id) ){
            $arr_op['version_priv'] = VersionPriv::get_data_by_id($version_id);
        }
        //dd($arr_op['version_priv']);
        //3 角色权限
        if( in_array($request['mode'], array('role')) ){
            if( !isset($request['role_id']) || empty($request['role_id']) ){
                $rt['errcode']=120020;
                $rt['errmsg']='获取权限列表,角色id不能为空';
                return $rt;
            }
            $rs_role = Role::get_data_by_id($request['role_id']);
            if( empty($rs_role) ){
                $rt['errcode']=120020;
                $rt['errmsg']='查不到此角色数据';
                return $rt;
            }
            $arr_op['role_priv'] = RolePriv::get_data_by_id($request['role_id']);
        }else if( in_array($request['mode'], array('login_user')) ){
            if( Auth::user()->is_admin==1 ){
                //主账号
                $arr_op['user_priv'] = $arr_op['version_priv'];
            }else{
                //子账号
                //角色权限
                $arr_userrole = UserRole::get_data_by_id(Auth::user()->id);
                if( !empty($arr_userrole) ){
                    $arr_user_role_priv = RolePriv::get_data_by_user_role($arr_userrole);
                }
                if( empty($arr_userrole) || empty($arr_user_role_priv) ){
                    $arr_user_role_priv = array();
                }
                //用户权限
                $arr_user_priv = UserPriv::get_data_by_id(Auth::user()->id,Auth::user()->merchant_id);
                if(empty($arr_user_priv)){
                    $arr_user_priv = array();
                }
                $arr_op['user_priv'] = array_merge($arr_user_role_priv,$arr_user_priv);
            }
        }

        //3 已拥有的权限加载到权限列表上
        if( !empty($arr_priv) && !empty($arr_op) ) {
            foreach ( $arr_priv as $key=>$val ) {
                $arr_priv[$key]['title'] = $arr_priv[$key]['name'];
                unset($arr_priv[$key]['name']);
                
                $arr_priv[$key]['versionAuth'] = 0;
                $arr_priv[$key]['userAuth'] = 0;
                $arr_priv[$key]['roleAuth'] = 0;
                
                if( isset($arr_op['version_priv']) && in_array($val['id'], $arr_op['version_priv']) ) {
                    $arr_priv[$key]['versionAuth'] = 1;
                }
                if( isset($arr_op['user_priv']) && in_array($val['id'], $arr_op['user_priv']) ) {
                    $arr_priv[$key]['userAuth'] = 1;
                }
                if( isset($arr_op['role_priv']) && in_array($val['id'], $arr_op['role_priv']) ) {
                    $arr_priv[$key]['roleAuth'] = 1;
                }
                //查看角色权限,版本权限没有的需要排除掉
                if( $arr_op['mode']=='role' && $arr_priv[$key]['versionAuth']!=1 ){
                    unset($arr_priv[$key]);
                }
            }
        }
        
        // 4 格式化权限
        $arr_rt = $this->list_to_tree($arr_priv);
        
        $rt['errcode']=0;
        $rt['errmsg']='获取成功';
        $rt['data']=$arr_rt;
        return $rt;
    }
    
    /**
     * 优化权限:格式化数据
     * songyongshang@dodoca.com 
     * */
    public function list_to_tree($list) {
        //创建Tree
        $tree = array();
         
        //引用传递,创建基于主键的数组引用
        $refer = array();
        foreach ($list as $key => $val) {
        	$refer[$val['id']] = &$list[$key]; 
        }
    
        foreach ($list as $key => $val) {
            //判断是否存在parent
            $parantId = $val['parent_id'];
    
            if (0 == $parantId) {
                //值传递
               $tree[] = &$list[$key];
            } else {
                if (isset($refer[$parantId])) {
                    //引用传递
                    $parent = &$refer[$parantId];
                    $parent['children'][] = &$list[$key];
                }
            }
        }
         
        return $tree;
    }
}
