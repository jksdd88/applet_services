<?php
namespace App\Http\Controllers\Super;


use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AdminLog;
use App\Models\UserLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Excel;

class UserController extends Controller
{

    public function __construct(Excel $excel){
        $this->excel = $excel;
    }

    /**
     * 用户列表
     */
    public function getUsers(Request $request){
        $params = $request->all();
        $offset = isset($params['offset']) ? $params['offset'] : 0;
        $limit = isset($params['limit']) ? $params['limit'] : 20;
        $export = isset($params['export']) ? trim($params['export']) : 0;//是否导出用户
        $ids = isset($params['ids']) ? trim($params['ids']) : '';//需要导出用户的id
        $export_start_id = isset($params['export_start_id']) ? intval($params['export_start_id']) : 0;
        $export_end_id = isset($params['export_end_id']) ? intval($params['export_end_id']) : 0;
        $query = User::select('user.id','user.username','user.avatar','user.gender','user.is_admin','user.created_time','user.merchant_id','merchant.version_id','merchant.company','merchant.contact','merchant.status');
        $query->leftJoin('merchant', 'user.merchant_id', '=', 'merchant.id');
        $where['user.is_delete'] = 1;
        if(!empty($params['username'])){
            $query->where('user.username', 'like', '%' . $params['username'] . '%');
        }
        if(!empty($params['company'])){
            $query->where('merchant.company', 'like', '%' . $params['company'] . '%');
        }
        if(!empty($params['starttime'])){
            $query = $query->where("user.created_time",">=",$params['starttime']);
        }
        if(!empty($params['endtime'])){
            $query = $query->where("user.created_time","<=",$params['endtime']);
        }
        $query->where($where);
        if($export){
            if(isset($ids) && $ids){
                $uids = explode(',', $ids);
                $query->whereIn('user.id',$uids);
            }
            if($export_start_id >0 && $export_end_id >0){
                $query->where("user.id",">=",$export_start_id);
                $query->where("user.id","<=",$export_end_id);
            }
            //最多导出500条
            $query->take(500);

            $userlist = $query->get()->toArray();
            //编码
            header('Expires: 0');
            header('Cache-control: private');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Content-Description: File Transfer');
            header('Content-Encoding: UTF-8');
            header('Content-type: text/csv; charset=UTF-8');

            $exportData[] = array(
                '用户编号'      => '',
                '用户名'        => '',
                '用户头像'      => '',
                '用户性别'      => '',
                '创建时间'      => '',
                '版本号'        => '',
                '公司名称'      => '',
                '联系人'        => '',
                '店铺状态'      => '',
            );

//            $sexarr = [0=>'未知',1=>'男',2=>'女'];
            $statusarr = [-1=>'已删除',1=>'正常',2=>'冻结'];
            foreach($userlist as $item){
                $version_id = empty($item['version_id']) ? '' : $item['version_id'];
                $company = empty($item['company']) ? '' : $item['company'];
                $contact = empty($item['contact']) ? '' : $item['contact'];
                $status = empty($item['status']) ? '' : $statusarr[$item['status']];
                $gender="";
                switch ($item['gender'])
                {
                    case 0:$gender='未知';break;
                    case 1:$gender='男';break;
                    case 2:$gender='女';break;
                }
                $exportData[] = [
                    '用户编号'  => $item['id'],
                    '用户名'    => $item['username'],
                    '用户头像'  => $item['avatar'],
                    '用户性别'  => $gender,
                    '创建时间'  => $item['created_time'],
                    '版本号'    => $version_id,
                    '公司名称'  => $company,
                    '联系人'    => $contact,
                    '店铺状态'  => $status,
                ];
            }

            $filename = '用户列表'.date('Ymd',time());
            $this->excel->create($filename, function($excel) use ($exportData) {
                $excel->sheet('export', function($sheet) use ($exportData) {
                    $sheet->fromArray($exportData);
                });
            })->export('xls');
        }

        $count = $query->count();
        $query->skip($offset);
        $query->take($limit);
        $list = $query->get();
        foreach($list as $key=>$item){
            $logintime = UserLog::where(['user_id'=>$item['id'],'type'=>3])->orderBy('id','desc')->pluck('created_time');
            $list[$key]['logintime'] = $logintime ? $logintime->toDateTimeString() : '';
        }
        return Response::json(['errcode'=>0,'_count'=>$count,'data'=>$list]);
    }

    /**
     * 用户详情
     */
    public function getUser(Request $request){
        $id = $request->id;
        $query = User::select('user.id','user.username','user.realname','user.avatar','user.gender','user.is_admin','user.created_time','merchant.version_id','merchant.company','merchant.contact','merchant.status');
        $query->leftJoin('merchant', 'user.merchant_id', '=', 'merchant.id');
        $user = $query->where(['user.is_delete'=>1,'user.id'=>$id])->first();
        if(empty($user)){
            return Response::json(['errcode'=>10001,'errmsg'=>'记录不存在']);
        }
//        $user['errcode'] = 0;
        return Response::json(['errcode'=>0,'_count'=>1,'data'=>$user]);
    }

    /**
     * 修改密码
     */
    public function ModifyPassWord(Request $request){
        $id = $request->id;
        $user = User::where(['id'=>$id,'is_delete'=>1])->first();
        if(empty($user)){
            $rt['errcode'] = 100001;
            $rt['errmsg'] = '用户不存在';
            return Response::json($rt);
        }
        //新登录密码
        if(empty($request['password'])){
            $rt['errcode']=100002;
            $rt['errmsg']='密码不能为空';
            return Response::json($rt);
        }else if( !preg_match('/[A-Z]/', $request['password']) || !preg_match('/[a-z]/', $request['password']) || !preg_match('/[0-9]/', $request['password']) || strlen('1qaz!QAZ')<6 || strlen('1qaz!QAZ')>20 ){
            $rt['errcode']=100001;
            $rt['errmsg']='密码必须同时包含大小写字母和数字,长度为6到20个字符';
            return Response::json($rt);
        }else {
            $data_User['password'] = bcrypt($request['password']);
        }
        //确认新密码
        if(empty($request['confirmpassword'])){
            $rt['errcode']=100003;
            $rt['errmsg']='确认密码不能为空';
            return Response::json($rt);
        }else if($request['confirmpassword'] != $request['password']){
            $rt['errcode']=100003;
            $rt['errmsg']='密码与确认密码不相同';
            return Response::json($rt);
        }

        $rs_User = User::where(['id'=>$id])->update($data_User);
        if(!$rs_User){
            $rt['errcode']=100010;
            $rt['errmsg']='修改密码失败';
            return Response::json($rt);
        }

        $rt['errcode']=0;
        $rt['errmsg']='修改成功';
        //-------------日志 start-----------------
        $data = array(
            /*'user_id'    => Auth::user()->id,
            'merchant_id' => Auth::user()->merchant_id,*/
            'user_id'    => $id,
            'merchant_id' => $user['merchant_id'],
            'action'=> 'post/super/'.$id.'/modifypwd.json',
            'url' => 'post/super/'.$id.'/modifypwd.json',
            'requests' => json_encode($request),
            'ip' => $request->ip(),
            'returns'=>json_encode($rt)
        );
        AdminLog::insert_data($data);
        //-------------日志 end-----------------

        return Response::json($rt);
    }

	/**
     * 获取管理员的权限
     */
    public function getUserLoginPrivs()
    {
		$result['privs'] = '';
        $result['errcode'] = 0;
        $result['errmsg'] = '获取成功';
		return $result;
    }
}
