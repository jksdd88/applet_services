<?php
namespace App\Http\Controllers\Super;


use App\Http\Controllers\Controller;
use App\Models\SuperUser;
use App\Models\AdminLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;

class SuperUserController extends Controller
{
    /**
     * 管理员列表
     */
    public function getUsers(Request $request){
        $params = $request->all();
        $offset = isset($params['offset']) ? $params['offset'] : 0;
        $limit = isset($params['limit']) ? $params['limit'] : 20;
        $query = SuperUser::select('id','username','mobile','email','realname','avatar','gender','is_admin','is_delete','created_time');
        $where['is_delete'] = 1;
        if(!empty($params['username'])){
            $query->where('username', 'like', '%' . $params['username'] . '%');
        }
        if(!empty($params['starttime'])){
            $where['created_time'] >= $params['startTime'];
        }
        if(!empty($params['endtime'])){
            $where['created_time'] >= $params['endTime'];
        }
        $query->where($where);
        $count = $query->count();
        $query->skip($offset);
        $query->take($limit);
        $list = $query->get();
        return Response::json(['errcode'=>0,'_count'=>$count,'data'=>$list]);
    }

    /**
     * 管理员详情
     */
    public function getUser(Request $request){
        $id = $request->id;
        $query = SuperUser::select('id','username','mobile','email','realname','avatar','gender','is_admin','is_delete','super_role_id','created_time');
        $user = $query->where(['is_delete'=>1,'id'=>$id])->first();
        if(empty($user)){
            return Response::json(['errcode'=>10001,'errmsg'=>'记录不存在']);
        }
        $user['errcode'] = 0;
        return Response::json($user);
    }

    /**
     * 新增管理员
     */
    public function addUser(Request $request){
        $params = $request->all();
        if(empty($params['username']) || empty($params['password']) || empty($params['confirmpassword'])){
            return Response::json(['errcode'=>10001,'errmsg'=>'参数错误']);
        }
        if($params['password'] != $params['confirmpassword']){
            return Response::json(['errcode'=>10002,'errmsg'=>'密码与确认密码不一致']);
        }
        $user = [
            'username' => $params['username'],
            'password' => bcrypt($params['password']),
            'mobile'=> !empty($params['mobile']) ? $params['mobile'] : '',
            'email'=> !empty($params['email']) ? $params['email'] : '',
            'realname' => !empty($params['realname']) ? $params['realname'] : '',
            'avatar' => !empty($params['avatar']) ? $params['avatar'] : '',
            'gender' => isset($params['gender']) ? $params['gender'] : 0,
            'super_role_id' => isset($params['super_role_id']) ? $params['super_role_id'] : 0,
            'is_admin' => 0,
            'is_delete' => 1
        ];
        $result = SuperUser::insert_data($user);
        return $result ? Response::json(['errcode'=>0,'errmsg'=>'新增成功']) : Response::json(['errcode'=>10003,'errmsg'=>'新增失败']);
    }

    /**
     * 修改管理员
     */
    public function putUser(Request $request){
        $id = $request->id;
        $user = SuperUser::where(['id'=>$id,'is_delete'=>1])->first();
        if(empty($user)){
            return Response::json(['errcode'=>10001,'errmsg'=>'用户不存在']);
        }
        if(isset($request['username'])){
            $user->username = $request['username'];
        }
        if(isset($request['mobile'])){
            $user->mobile = $request['mobile'];
        }
        if(isset($request['email'])){
            $user->email = $request['email'];
        }
        if(isset($request['realname'])){
            $user->realname = $request['realname'];
        }
        if(isset($request['avatar'])){
            $user->avatar = $request['avatar'];
        }
        if(isset($request['gender'])){
            $user->gender = $request['gender'];
        }
        if(isset($request['super_role_id'])){
            $user->super_role_id = $request['super_role_id'];
        }
        if(isset($request['password']) && isset($request['confirmpassword']) && $request['password'] == $request['confirmpassword']){
            $user->password = bcrypt($request['password']);
        }
        $result = $user->save();
        return $result ? Response::json(['errcode'=>0,'errmsg'=>'更新成功']) : Response::json(['errcode'=>10002,'errmsg'=>'更新失败']);
    }

    /*
     * 删除管理员
     */
    public function deleteUser(Request $request){
        $id = $request->id;
        $result = SuperUser::where(['id'=>$id,'is_delete'=>1])->delete();
        return $result ? Response::json(['errcode'=>0,'errmsg'=>'删除成功']) : Response::json(['errcode'=>10001,'errmsg'=>'删除失败']);
    }

}
