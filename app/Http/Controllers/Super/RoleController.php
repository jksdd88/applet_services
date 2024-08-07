<?php
namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\SuperPriv;
use App\Models\SuperRole;
use App\Models\SuperRolePriv;
use App\Models\AdminLog;

use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    protected $request;

    protected  $superpriv;

    protected  $superrole;

    protected  $superrolepriv;

    public function __construct(Request $request, SuperPriv $superpriv, SuperRole $superrole, SuperRolePriv $superrolepriv)
    {
        $this->request = $request;

        $this->params = $request->all();

        $this->superpriv = $superpriv;

        $this->superrole = $superrole;

        $this->superrolepriv = $superrolepriv;

    }


    /**
     *  角色列表
     *
     */
    public function getRoleLists(){

        $offset = isset($this->params['offset']) ? $this->params['offset'] : 0;

        $limit = isset($this->params['limit']) ? $this->params['limit'] : 10;

        $role_name = isset($this->params['role_name']) ? trim($this->params['role_name']) : '';

        $query = $this->superrole->select('*');

        $query->where('is_delete','=',1);

        if (!empty($role_name)) {  //角色名称搜索

            $query->where('rolename','like','%' . $role_name . '%');
        }
        $count = $query->count();

        $list = $query->orderBy('created_time', 'DESC')->skip($offset)->take($limit)->get();

        return Response::json(['errcode'=>0,'_count'=>$count,'data'=>$list]);

    }

    /**
     *  可选角色列表
     *
     */
    public function getrolelist(){

        $query = $this->superrole->select('*');

        $query->where('is_delete','=',1);

        if (!empty($role_name)) {  //角色名称搜索

            $query->where('rolename','like','%' . $role_name . '%');
        }
        $count = $query->count();

        $list = $query->orderBy('created_time', 'DESC')->get();

        return Response::json(['errcode'=>0,'_count'=>$count,'data'=>$list]);

    }

    /**
     * 角色详情
     */
    public function getRole(){

        $id = $this->request->id;

        $query = SuperRole::select('*');

        $role = $query->where(['is_delete'=>1,'id'=>$id])->get()->toArray();

        if(empty($role)){

            return Response::json(['errcode'=>10001,'errmsg'=>'记录不存在']);
        }

        $data['errcode'] = 0;

        $data['msg'] = '查询成功';

        $data['data'] = $role;

        return Response::json($data);
    }


    /**
     *  添加角色
     *
     */

    public function postAddRole(){

        if(empty($this->params['name'])){

            return Response::json(['errcode'=>10001,'errmsg'=>'参数错误']);
        }

        $super_role = [
            'rolename' => $this->params['name'],

            'is_delete' => 1,

        ];

        //--------------保存数据,记录日志并返回 start-----------------
        DB::beginTransaction();
        try{
            //角色:保存数据
            $rs_Role_id = SuperRole::insert_data($super_role);
            if(empty($rs_Role_id)){
                $rt['errcode']=100026;
                $rt['errmsg']='角色:保存数据 失败';
                return Response::json($rt);
            }
            //后台操作日志
            //$data_UserLog['merchant_id']=Auth::user()->merchant_id;
           // $data_UserLog['user_id']=Auth::user()->id;
            //$data_UserLog['type']=30;
            $data_UserLog['action']='post/Role.json';
            $data_UserLog['url']='post/Role.json';
            $data_UserLog['requests']=json_encode($this->params);
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
     * 修改编辑角色
     */
    public function putRole(){

        $id = $this->request->id;

        $role = SuperRole::where(['id'=>$id,'is_delete'=>1])->first();

        if(empty($role)){

            return Response::json(['errcode'=>10001,'errmsg'=>'角色不存在']);

        }
        if(!empty($this->params['name'])){

            $roledata['rolename'] = $this->params['name'];

        }else{

            return Response::json(['errcode'=>10001,'errmsg'=>'参数错误']);
        }


        //--------------保存数据,记录日志并返回 start-----------------
        DB::beginTransaction();
        try{
            //角色:保存数据
            $rs_Role = SuperRole::update_data($id,$roledata);
            if(empty($rs_Role)){
                $rt['errcode']=100026;
                $rt['errmsg']='角色:保存数据 失败';
                return Response::json($rt);
            }
            //后台操作日志
            //$data_UserLog['merchant_id']=Auth::user()->merchant_id;
            //$data_UserLog['user_id']=Auth::user()->id;
            //$data_UserLog['type']=31;
            $data_UserLog['action']='put/Role.json';
            $data_UserLog['url']='put/Role.json';
            $data_UserLog['requests']=json_encode($this->params);
            $data_UserLog['returns']='';
            $data_UserLog['ip']=get_client_ip();
            AdminLog::insert_data($data_UserLog);

            DB::commit();

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

    /*
     * 删除角色
     */
    public function deleteRole(){

        $id = $this->request->id;

        $result = SuperRole::where(['id'=>$id,'is_delete'=>1])->delete();

        return $result ? Response::json(['errcode'=>0,'errmsg'=>'删除成功']) : Response::json(['errcode'=>10001,'errmsg'=>'删除失败']);
    }

    /**
     * 获取角色的权限
     *
     */
    public function getSuperRolePriv(){

        $super_role_id = $this->request->id;

        $query = $this->superpriv->select('*');

        $query->where('is_delete','=',1);

        $list = $query->orderBy('id', 'desc')->get();

        if(!empty($super_role_id)){

            $super_role_priv = SuperRolePriv::where('super_role_id','=',$super_role_id)->get()->toArray();

            if($super_role_priv){

                foreach ($list as $key=>$val){

                    $list[$key]['status'] = 0;

                    foreach ($super_role_priv as $k=>$v){

                        if($val->id == $v['priv_id']){

                            $list[$key]['status'] =1;

                        }
                    }
                }

            }else{

                foreach ($list as $key=>$val){

                    $list[$key]['status'] = 0;

                }
            }


            $rt['errcode']=0;
            $rt['errmsg']='角色-权限:显示列表 成功';
            $rt['data'] = $list;
            return Response::json($rt);

        }else{

            Response::json(['errcode'=>10001,'errmsg'=>'缺少参数']);
        }
    }

    /**
     * 修改角色权限
     *
     */

    public function putSuperRolePriv(){

        //角色id
        if(empty($this->params['role_id'])){
            $rt['errcode']=100002;
            $rt['errmsg']='版本id 不能为空';
            return Response::json($rt);
        }
        //权限id
        if(empty($this->params['priv_id'])){
            $rt['errcode']=100002;
            $rt['errmsg']='权限id 不能为空';
            return Response::json($rt);
        }
        //操作
        if(empty($this->params['op'])){
            $rt['errcode']=100002;
            $rt['errmsg']='操作 不能为空';
            return Response::json($rt);
        }

        if($this->params['op'] == 'add'){
            $role_priv = SuperRolePriv::select("*")->where(array('super_role_id'=>$this->params['role_id'],'priv_id'=>$this->params['priv_id']))->count();
            if($role_priv >= 1){
                $rt['errcode']=0;
                $rt['errmsg']='角色-权限:修改 成功';
                return Response::json($rt);
            }
        }
        if($this->params['op'] =='strike'){
            $role_priv = SuperRolePriv::select("*")->where(array('super_role_id'=>$this->params['role_id'],'priv_id'=>$this->params['priv_id']))->first();
            if(empty($role_priv)){
                $rt['errcode']=0;
                $rt['errmsg']='角色-权限:修改 成功';
                return Response::json($rt);
            }
        }

        //--------------保存数据,记录日志并返回 start-----------------
        DB::beginTransaction();
        try{
            //版本:保存数据
            $rs_Role = SuperRolePriv::update_data($this->params['role_id'],$this->params['priv_id'],$this->params['op']);
            if(empty($rs_Role)){
                $rt['errcode']=100026;
                $rt['errmsg']='角色-权限:保存数据 失败';
                return Response::json($rt);
            }
            //后台操作日志
            //$data_UserLog['user_id']=Auth::user()->id;
            $data_UserLog['action']='put/superrolepriv.json';
            $data_UserLog['url']='put/superrolepriv.json';
            $data_UserLog['requests']=json_encode($this->params);
            $data_UserLog['returns']='';
            $data_UserLog['ip']=get_client_ip();
            AdminLog::insert_data($data_UserLog);

            DB::commit();
        }catch (\Exception $e) {
            $rt['errcode']=100027;
            $rt['errmsg']='角色-权限:修改 失败';
            dd($e);
            return Response::json($rt);
        }
        $rt['errcode']=0;
        $rt['errmsg']='角色-权限:修改 成功';
        return Response::json($rt);
        //--------------保存数据,记录日志并返回 end-----------------
    }




}
