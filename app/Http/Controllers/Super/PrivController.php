<?php
namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\Priv;
use App\Models\AdminLog;

use Illuminate\Support\Facades\DB;

class PrivController extends Controller
{
    protected $request;

    protected  $priv;

    public function __construct(Request $request, Priv $priv) {
        $this->request = $request;
        $this->params = $request->all();
        $this->priv = $priv;
    }

    /**
     * 获取权限列表
     *  Author:zhangyu1@dodoca.com
     */
    public function getManagePriv(){
        $offset = isset($this->params['offset']) ? $this->params['offset'] : 0;
        $limit = isset($this->params['limit']) ? $this->params['limit'] : 10;
        $name = isset($this->params['name']) ? trim($this->params['name']) : '';
        $query = $this->priv->select('*');
        $query->where('is_delete','=',1);
        if (!empty($name)) {  //角色名称搜索
            $query->where('name','like','%' . $name . '%');
        }
        $count = $query->count();
        $list = $query->orderBy('created_time', 'DESC')->skip($offset)->take($limit)->get();
        return Response::json(['errcode'=>0,'errmsg'=>'查询成功','_count'=>$count,'data'=>$list]);
    }

    /**
     * 新增权限
     *Author:zhangyu1@dodoca.com
     */
    public function postAddMagPriv(){
        //权限名称
        if(empty($this->params['name'])  || empty($this->params['code'])){
            return Response::json(['errcode'=>10001,'errmsg'=>'参数错误']);
        }
        $name = $this->params['name'] ;
        //权限码
        $code = $this->params['code'] ;
        $copy = $this->priv->where(array('code'=>$code,'is_delete'=>1))->first();
        if(!empty($copy)){
            $rt['errcode']=10001;
            $rt['errmsg']='权限模块:此权限码已存在';
            return Response::json($rt);
        }
        //权限父级
        if(empty($this->params['parent_id'])){
            $parent_sort = $this->priv->where(['parent_id'=>0,'is_delete'=>1])->orderby('sort','desc')->first();
            $sort = $parent_sort['sort'] + 1;
        }else{
            $parent_sort = $this->priv->where(['parent_id'=>$this->params['parent_id'],'is_delete'=>1])->orderby('sort','desc')->first();
            if(!empty($parent_sort)){
                $sort = $parent_sort['sort'] + 1;
            }else{
                $sort = 1;
            }
        }
        $priv = [
            'parent_id' => $this->params['parent_id'],
            'path_name' => $this->params['path_name'],
            'path' => $this->params['path'],
            'name' => $name,
            'code' => $code,
            'is_delete' => 1,
            'sort' => $sort,
        ];

        //--------------保存数据,记录日志并返回 start-----------------
        DB::beginTransaction();
        try{
            //权限模块:保存数据
            $rs_Priv_id = Priv::insert_data($priv);
            if(empty($rs_Priv_id)){
                $rt['errcode']=100026;
                $rt['errmsg']='权限模块:保存数据 失败';
                return Response::json($rt);
            }
            //后台操作日志
            //$data_UserLog['type']=28;
            $data_UserLog['action']='post/addmagpriv.json';
            $data_UserLog['url']='post/addmagpriv.json';
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
     *Author:zhangyu1@dodoca.com
     */

    public function deleteMagPriv(){
        $id = $this->request->id;
        if(empty($id)){
            return Response::json(['errcode'=>10001,'errmsg'=>'参数错误']);
        }
        $data['is_delete'] = -1;
        $result = $this->priv->update_data($id,$data);
        return $result ? Response::json(['errcode'=>0,'errmsg'=>'删除成功']) : Response::json(['errcode'=>10001,'errmsg'=>'删除失败']);
    }

    /**
     * 修改权限
     */
    public function putMagPriv(){
        $id = $this->request->id;
        $role = Priv::where(['id'=>$id,'is_delete'=>1])->first();
        if(empty($role)){
            return Response::json(['errcode'=>10001,'errmsg'=>'权限不存在']);
        }
        //权限名称
        if(!empty($this->params['name'])){
            $privdata['name'] = $this->params['name'];
        }else{
            return Response::json(['errcode'=>10001,'errmsg'=>'参数错误']);
        }
        //权限code
        if(!empty($this->params['code'])){
            $privdata['code'] = $this->params['code'];
        }else{
            return Response::json(['errcode'=>10001,'errmsg'=>'参数错误']);
        }
        //权限排序
        if(!empty($this->params['sort'])){
            $privdata['sort'] = $this->params['sort'];
        }else{
            return Response::json(['errcode'=>10001,'errmsg'=>'参数错误']);
        }
        $privdata['parent_id'] = $this->params['parent_id'];
        $privdata['path_name'] = $this->params['path_name'];
        $privdata['path'] = $this->params['path'];

        //--------------保存数据,记录日志并返回 start-----------------
        DB::beginTransaction();
        try{
            //权限模块:保存数据
            $rs_Priv = Priv::update_data($id,$privdata);
            if(empty($rs_Priv)){
                $rt['errcode']=100026;
                $rt['errmsg']='权限模块:保存数据 失败';
                return Response::json($rt);
            }
            //后台操作日志
            //$data_UserLog['type']=29;
            $data_UserLog['action']='put/putmagpriv.json';
            $data_UserLog['url']='put/putmagpriv.json';
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
     * Author:zhangyu1@dodoca.com
     */
    public function getMagPrivDetail(){
        $id = $this->request->id;
        $query = Priv::select('*');
        $priv = $query->where(['is_delete'=>1,'id'=>$id])->get()->toArray();
        if(empty($priv)){
            return Response::json(['errcode'=>10001,'errmsg'=>'权限 不存在']);
        }
        $data['errcode'] = 0;
        $data['msg'] = '查询成功';
        $data['data'] = $priv;
        return Response::json($data);
    }

    /**
     * 父级权限
     * Author:zhangyu1@dodoca.com
     */
    public function getAllMagParPriv(){
        $alldata = Priv::where(array('is_delete'=>1,'parent_id'=>0))->get()->toArray();
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
     *Author:zhangyu1@dodoca.com
     */
    public function getAllPrivs(){
        $query = $this->priv->select('*');
        $query->where('is_delete','=',1);
        $list = $query->orderBy('id', 'DESC')->get();
        return Response::json(['errcode'=>0,'data'=>$list]);
    }
}
