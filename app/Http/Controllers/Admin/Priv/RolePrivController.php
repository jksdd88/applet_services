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
use function Predis\select;

class RolePrivController extends Controller
{
    
    
    /**
     * 已设置的角色权限:显示列表
     * Author: songyongshang@dodoca.com
     */
    public function getRolePriv()
    {
        if(!isset($_REQUEST['role_id']) || empty($_REQUEST['role_id'])){
            $rt['errcode']=100002;
            $rt['errmsg']='角色id 不能为空';
            $rt['data']='';
            return Response::json($rt);
        }
        $Roles = Role::where(['role.merchant_id'=>Auth::user()->merchant_id,'role.is_delete' => 1,'role.id'=>$_REQUEST['role_id']])
                ->leftjoin('role_priv','role_priv.role_id','=','role.id')
                ->leftjoin('priv','priv.id','=','role_priv.priv_id')
                ->select('priv.id','priv.code')->get();
//         $arr_role_priv=array();
//         if(!empty($Roles)){
//             foreach ($Roles as $key=>$val){
//                 $arr_role_priv[]=$val['code'];
//             }
//         }
        $rt['errcode']=0;
        $rt['errmsg']='角色:显示列表 成功';
        $rt['data']=!empty($Roles)?$Roles:'';
        return Response::json($rt);
    }
    
    
}
