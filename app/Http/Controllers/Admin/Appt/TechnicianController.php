<?php

namespace App\Http\Controllers\Admin\Appt;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Models\Store;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Models\ApptStaff;
class TechnicianController extends Controller
{
    public function __construct()
    {
        $this->merchant_id = Auth::user()->merchant_id;
        // $this->merchant_id = 1;
    }


    /**
     * 技师列表
     *@param  $page  int 页码 (可选,默认为1)
     *@param  $pagesize  int 显示条数(可选,默认为3)
     *@param  $filter  str 搜索条件(可选,搜索昵称和手机号)
     *@param  $store_id  int 门店(可选,非空时显示指定门店技师,反之显示该商户下的所有技师)
     *@param  $is_all   int  是否显示全部(可选,值为1时显示全部,优先级高于$page和$pagesize)
     */
    public function index(Request $request)
    {
        $merchant_id =$this->merchant_id;   //商户id
        if((int)$merchant_id < 1) return array('errcode'=>150001,'errmsg'=>'缺少商户id','data'=>['count'=>0,'data'=>[]]);
        $page = (int)$request->page <1 ? 1 :(int)$request->page;                    //页码
        $pagesize = (int)$request->pagesize <1 ? 3 :(int)$request->pagesize;        //每页多少条数据
        $offset = ($page-1)*$pagesize;                                              //偏移量
        // dd($page,$pagesize,$offset);
        $filter = trim($request->filter);
        $query = DB::table('appt_staff as staff')->Join('store','staff.store_id','=','store.id')
                ->select('staff.id','staff.nickname','staff.store_id','staff.name','staff.mobile','store.name as store_name')
                ->where('staff.is_delete',1)
                ->where('store.is_delete',1)
                ->where('staff.merchant_id',$merchant_id);
        if((int)($request->store_id)>0) $query = $query->where('staff.store_id',(int)$request->store_id);    //选择门店
        if($filter){                                                                //查询昵称或者手机号
            $query = $query->where(function($query) use ($filter){
                $query->where('staff.nickname','like','%'.$filter.'%')
                    ->orWhere('staff.mobile','like','%'.$filter.'%');
            });
        } 

        $count= $query->count();                                                    //总条数
        if($count < 1) return Response::json(array('errcode'=>0,'errmsg'=>'查询成功','data'=>['count'=>0,'data'=>[]]));
        if((int)($request->is_all) !== 1){
            $query = $query->offset($offset)                                        //前台分页数据
                    ->limit($pagesize);
        }
        $data = $query->orderBy('staff.id','desc')                                        //排序
                    ->get();
        // $data = json_decode($data,true);
        // $test = [
        //     'page'=>$page,
        //     'pagesize'=>$pagesize,
        //     'offset'=>$offset,
        //     'store_id'=>$request->store_id
        // ];
        return Response::json(array('errcode'=>0,'errmsg'=>'查询成功','data'=>['count'=>$count,'data'=>$data]));
    }

    /**
     * 添加技师
     *@param $store_id int 门店id (必选)
     *@param $nickname str 昵称 (必选)
     *@param $mobile str 手机号 (可选)
     *@param $name str 技师名称 (可选)
     */
    public function create(Request $request)
    {

        //store_id=1&nickname=test123&mobile=12364789651&name=123234
        $data['merchant_id'] = $this->merchant_id;
        $data['is_delete'] = 1;
        $data['store_id'] = (int)($request->store_id);
        $data['nickname'] = trim($request->nickname);
        $data['mobile'] = trim($request->mobile);
        $data['name'] = trim($request->name);
        if($data['merchant_id']<1 || $data['store_id']<1 || $data['nickname']== '' ){
            return Response::json(array('errcode'=>150002,'errmsg'=>'参数值缺失'));
        }
        //判定是否重名
        $is_repetition = ApptStaff::select('id')
                        ->where('merchant_id',$this->merchant_id)
                        ->where('is_delete',1)
                        ->where('nickname',$data['nickname'])
                        ->count();
        if( $is_repetition >0) return Response::json(array('errcode'=>15004,'errmsg'=>'该技师已经存在'));


        $res = ApptStaff::insert_data($data);
        return Response::json(array('errcode'=>0,'errmsg'=>'添加成功'));

    }



    /**
     * 技师信息编辑
     *@param  $staff_id   技师id
     *@param $store_id int 门店id (必选)
     *@param $nickname str 昵称 (必选)
     *@param $mobile str 手机号 (可选)
     *@param $name str 技师名称 (可选)
     */
    public function edit(Request $request,$staff_id)
    {
        $data['merchant_id'] = $this->merchant_id;
        $data['store_id'] = (int)($request->store_id);
        $data['nickname'] = trim($request->nickname);
        $data['mobile'] = trim($request->mobile);
        $data['name'] = trim($request->name);
        if($data['merchant_id']<1 || $data['store_id']<1 || $data['nickname']== '' ){
            return Response::json(array('errcode'=>150002,'errmsg'=>'参数值缺失'));
        }
        //判定是否重名
        $is_repetition = ApptStaff::select('id')
                        ->where('merchant_id',$this->merchant_id)
                        ->where('is_delete',1)
                        ->where('nickname',$data['nickname'])
                        ->where('id','<>',$staff_id)
                        ->count();
        if( $is_repetition >0) return Response::json(array('errcode'=>15004,'errmsg'=>'该技师已经存在'));
        $res = ApptStaff::update_data($staff_id, $data['merchant_id'], $data);
        return Response::json(array('errcode'=>0,'errmsg'=>'修改成功'));
    }



    /**
     * 删除技师
     * @param  $staff_id   技师id
     */
    public function destroy(Request $request,$staff_id)
    {
        $res = ApptStaff::update_data($staff_id, $this->merchant_id, ['is_delete'=>-1]);
        return Response::json(array('errcode'=>0,'errmsg'=>'删除成功'));
    }

   /**
    *根据id获取技师详情
    */
    public function getOneById(Request $request,$staff_id)
    {
        $merchant_id=(int)$this->merchant_id;
        $data['staff_info'] = ApptStaff::where('is_delete',1)                                   //技术信息
                        ->select('id','store_id','merchant_id','nickname','name','mobile')
                        ->where('merchant_id',$merchant_id)
                        ->find($staff_id);
        // $data['staff_info'] = ApptStaff::get_data_by_id($staff_id, $merchant_id);
        if($data['staff_info'] == null){
            return Response::json(array('errcode'=>150003,'errmsg'=>'该技师已删除'));
        }else{
            $data['store_list'] = Store::select('id','name')
                        ->where('merchant_id',$merchant_id)                      //总店下的所有门店
                        ->where('is_delete',1)
                        ->get();
            return Response::json(array('errcode'=>0,'errmsg'=>'查询成功','data'=>$data));
        }
    }
}
