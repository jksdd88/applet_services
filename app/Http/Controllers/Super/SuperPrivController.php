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

class SuperPrivController extends Controller
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
     * 获取权限列表
     *
     */
    public function getSuperPriv(){

        $offset = isset($this->params['offset']) ? $this->params['offset'] : 0;

        $limit = isset($this->params['limit']) ? $this->params['limit'] : 10;

        $name = isset($this->params['name']) ? trim($this->params['name']) : '';

        $query = $this->superpriv->select('*');

        $query->where('is_delete','=',1);

        if (!empty($name)) {  //角色名称搜索

            $query->where('name','like','%' . $name . '%');
        }
        $count = $query->count();

        $list = $query->orderBy('created_time', 'DESC')->skip($offset)->take($limit)->get();

        return Response::json(['errcode'=>0,'_count'=>$count,'data'=>$list]);
    }

    /**
     * 新增权限
     *
     */
    public function postAddSuperPriv(){

        if(empty($this->params['name'])  || empty($this->params['code'])){

            return Response::json(['errcode'=>10001,'errmsg'=>'参数错误']);
        }

        $super_priv = [

            'name' => $this->params['name'],

            'parent_id' => $this->params['parent_id'],

            'code' => $this->params['code'],

            'is_delete' => 1,

        ];

        //--------------保存数据,记录日志并返回 start-----------------
        DB::beginTransaction();
        try{
            //权限模块:保存数据
            $rs_Priv_id = SuperPriv::insert_data($super_priv);
            if(empty($rs_Priv_id)){
                $rt['errcode']=100026;
                $rt['errmsg']='权限模块:保存数据 失败';
                return Response::json($rt);
            }
            //后台操作日志
            //$data_UserLog['type']=28;
            $data_UserLog['action']='post/priv.json';
            $data_UserLog['url']='post/priv.json';
            $data_UserLog['requests']=json_encode($this->params);
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
     *  删除权限
     *
     */

    public function deleteSuperPriv(){

        $id = $this->request->id;

        $result = SuperPriv::where(['id'=>$id,'is_delete'=>1])->delete();

        return $result ? Response::json(['errcode'=>0,'errmsg'=>'删除成功']) : Response::json(['errcode'=>10001,'errmsg'=>'删除失败']);
    }

    /**
     * 修改权限
     */

    public function putSuperPriv(){

        $id = $this->request->id;

        $role = SuperPriv::where(['id'=>$id,'is_delete'=>1])->first();

        if(empty($role)){

            return Response::json(['errcode'=>10001,'errmsg'=>'权限不存在']);

        }
        if(!empty($this->params['name'])){

            $privdata['name'] = $this->params['name'];

        }else{

            return Response::json(['errcode'=>10001,'errmsg'=>'参数错误']);
        }

       /* if(!empty($this->params['parent_id'])){

            $privdata['parent_id'] = $this->params['parent_id'];

        }else{

            return Response::json(['errcode'=>10001,'errmsg'=>'参数错误']);
        }*/

        if(!empty($this->params['code'])){

            $privdata['code'] = $this->params['code'];

        }else{

            return Response::json(['errcode'=>10001,'errmsg'=>'参数错误']);
        }

        $privdata['parent_id'] = $this->params['parent_id'];

        //--------------保存数据,记录日志并返回 start-----------------
        DB::beginTransaction();
        try{
            //权限模块:保存数据
            $rs_Priv = SuperPriv::update_data($id,$privdata);
            if(empty($rs_Priv)){
                $rt['errcode']=100026;
                $rt['errmsg']='权限模块:保存数据 失败';
                return Response::json($rt);
            }
            //后台操作日志
            //$data_UserLog['type']=29;
            $data_UserLog['action']='put/priv.json';
            $data_UserLog['url']='put/priv.json';
            $data_UserLog['requests']=json_encode($this->params);
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
     * 权限详情
     */
    public function getSuperPrivDetail(){

        $id = $this->request->id;

        $query = SuperPriv::select('*');

        $priv = $query->where(['is_delete'=>1,'id'=>$id])->get()->toArray();

        if(empty($priv)){

            return Response::json(['errcode'=>10001,'errmsg'=>'记录不存在']);
        }

        $data['errcode'] = 0;

        $data['msg'] = '查询成功';

        $data['data'] = $priv;

        return Response::json($data);
    }

    /**
     * 父级权限
     */
    public function getAllParentPriv(){

        $alldata = SuperPriv::where('parent_id','=',0)->get()->toArray();

        if(empty($alldata)){

            return Response::json(['errcode'=>10001,'errmsg'=>'权限不存在']);
        }

        $data['errcode'] = 0;

        $data['msg'] = '查询成功';

        $data['data'] = $alldata;

        return Response::json($data);
    }


    /**
     * 所有权限
     *
     */
    public function getAllPrivs(){

        $query = $this->superpriv->select('*');

        $query->where('is_delete','=',1);

        $list = $query->orderBy('id', 'DESC')->get();

        return Response::json(['errcode'=>0,'data'=>$list]);

    }




}
