<?php
namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;

class AnnounceController extends Controller
{
    protected  $announce_ver;

    protected  $announce_key;

    public function __construct()
    {
        $key = CacheKey::get_announce_by_id_key();

        $data = Cache::get($key);

        $this->announce_key = $key;

        $this->announce_ver = $data;
    }

    /**
     * 公告列表
     */
    public function getAnnouncements(Request $request){

        $params = $request->all();

        $offset = isset($params['offset']) ? $params['offset'] : 0;

        $limit = isset($params['limit']) ? $params['limit'] : 10;

        $query = Announcement::select('id','announce_name','announce_type','announce_content','update_ver','back_ver','pop_up','status','created_time');

        $query->where('status','=',1);

        if(!empty($params['name'])){     //公告名查询

            $query->where('announce_name', 'like', '%' . $params['name'] . '%');
        }
        if(!empty($params['type'])){      //公告类型查询

            $query->where('announce_type','=',$params['type']);
        }

        $count = $query->count();

        $query->orderby('created_time','desc');

        $query->skip($offset);

        $query->take($limit);

        $list = $query->get();

        return Response::json(['errcode'=>0,'_count'=>$count,'current_ver'=>$this->announce_ver,'data'=>$list]);
    }

    /**
     * 公告详情
     */
    public function getAnnounce(Request $request){
        $id = $request->id;

        $query = Announcement::select('id','announce_name','announce_type','announce_content','update_ver','back_ver','pop_up','status','created_time');

        $announce = $query->where(['status'=>1,'id'=>$id])->first();

        if(empty($announce)){

            return Response::json(['errcode'=>10001,'errmsg'=>'记录不存在']);
        }

        $announce['errcode'] = 0;

        $announce['msg'] = '查询成功';

        return Response::json($announce);
    }

    /**
     * 新增公告
     */
    public function addAnnounce(Request $request){

        $params = $request->all();

        if(empty($params['name']) || empty($params['type']) || empty($params['content'])){

            return Response::json(['errcode'=>10001,'errmsg'=>'参数错误']);
        }

        $announce = [
            'announce_name' => $params['name'],
            'announce_type' => $params['type'],
            'announce_content'=> $params['content'],
            //'version_id'=> $params['version_ids'],
            'pop_up' => 0,
            'update_ver'=>0,
            'back_ver'=>0,
            'status' => 1,

        ];

        $result = Announcement::insert_data($announce);

        return $result ? Response::json(['errcode'=>0,'errmsg'=>'新增成功']) : Response::json(['errcode'=>10003,'errmsg'=>'新增失败']);
    }

    /**
     * 修改公告
     */
    public function putAnnounce(Request $request){

        $id = $request->id;

        $announce = Announcement::where(['id'=>$id,'status'=>1])->first();

        if(empty($announce)){

            return Response::json(['errcode'=>10001,'errmsg'=>'公告不存在']);

        }
        if(!empty($request['name'])){

            $announcedata['announce_name'] = $request['name'];

        }else{

            return Response::json(['errcode'=>10001,'errmsg'=>'参数错误']);
        }
        if(!empty($request['type'])){

            $announcedata['announce_type'] = $request['type'];

        } else{

            return Response::json(['errcode'=>10001,'errmsg'=>'参数错误']);
        }
        if(!empty($request['content'])){

            $announcedata['announce_content'] = $request['content'];

        } else{

            return Response::json(['errcode'=>10001,'errmsg'=>'参数错误']);
        }

        $result = Announcement::update_data($id,$announcedata);

        return $result ? Response::json(['errcode'=>0,'errmsg'=>'更新成功']) : Response::json(['errcode'=>10002,'errmsg'=>'更新失败']);
    }

    /*
     * 删除公告
     */
    public function deleteAnnounce(Request $request){

        $id = $request->id;

        $result = Announcement::where(['id'=>$id,'status'=>1])->update(['status'=>0]);

        return $result ? Response::json(['errcode'=>0,'errmsg'=>'删除成功']) : Response::json(['errcode'=>10001,'errmsg'=>'删除失败']);
    }

    /**
     * 更新/后退版本号
     */
    public function getAnnouncever(Request $request){

        //操作
        if(empty($request['op'])){
            $rt['errcode']=100002;
            $rt['errmsg']='操作 不能为空';
            return Response::json($rt);
        }

        $announcement = Announcement::where('status','=',1)->get()->toArray();
        if(empty($announcement)){
            $rt['errcode']=100002;
            $rt['errmsg']='暂无公告';
            return Response::json($rt);

        }

        if($request['op'] == 'upd'){

            if($this->announce_ver){

                $data = $this->announce_ver + 1;

                Cache::forever($this->announce_key,$data);

            }else{

                $data = 1;

                Cache::forever($this->announce_key,$data);
            }

            foreach ($announcement as $k => $v){

                $updatedata['update_ver'] = 1;

                $updatedata['back_ver'] = 0;

                $updata = Announcement::where('id','=',$v['id'])->update($updatedata);

            }

            if($updata){

                $rt['errcode']=0;

                $rt['errmsg']='更新成功';

                $rt['current_ver']=Cache::get(CacheKey::get_announce_by_id_key());

                return Response::json($rt);

            }
        }

        if($request['op'] == 'back'){

            $query = Announcement::select('id','announce_name','announce_type','announce_content','update_ver','back_ver','pop_up','status','created_time');

            $query->where(['status'=>1,'back_ver'=>1,'update_ver'=>0]);

            $back_res = $query->first();

            if($back_res){

                $rt['errcode']=100002;

                $rt['errmsg']='不可连续后退版本';

                return Response::json($rt);

            }

            if($this->announce_ver > 1){

                $data = $this->announce_ver - 1;

                Cache::forever($this->announce_key,$data);

            }else{

                $rt['errcode']=100002;

                $rt['errmsg']='已经是最初版本';

                return Response::json($rt);

            }

            foreach ($announcement as $k => $v){

                $updatedata['update_ver'] = 0;

                $updatedata['back_ver'] = 1;

                $updata = Announcement::where('id','=',$v['id'])->update($updatedata);

            }
            if($updata){

                $rt['errcode']=0;

                $rt['errmsg']='更新成功';

                $rt['current_ver']=Cache::get(CacheKey::get_announce_by_id_key());

                return Response::json($rt);

            }
        }

    }


    /**
     * 弹框操作
     */

    public function getAnnounceshow(Request $request){

        $id = $request['id'];

        //操作
        if(empty($request['op']) || empty($request['id'])){
            $rt['errcode']=100002;
            $rt['errmsg']='缺少参数';
            return Response::json($rt);
        }

        $announce = Announcement::where(['id'=>$id,'status'=>1])->first();

        if(empty($announce)){

            return Response::json(['errcode'=>10001,'errmsg'=>'公告不存在']);

        }

        if($request['op'] == 'show'){

            /* $pop_res = Announcement::where(['status'=>1,'pop_up'=>1])->first();

            if($pop_res){

                $rt['errcode']=100002;

                $rt['errmsg']='已有公告正启用弹窗';

                return Response::json($rt);
            }*/
            $other = Announcement::where(['status'=>1])->update(['pop_up'=>0]);

            if($other){

                $result = Announcement::where(['id'=>$id,'status'=>1])->update(['pop_up'=>1]);
            }else{

                $rt['errcode']=100002;

                $rt['errmsg']='请重新操作!';

                return Response::json($rt);
            }


        }elseif ($request['op'] == 'hide'){

            $result = Announcement::where(['id'=>$id,'status'=>1])->update(['pop_up'=>0]);
        }

        return $result ? Response::json(['errcode'=>0,'errmsg'=>'更新成功']) : Response::json(['errcode'=>10001,'errmsg'=>'更新失败']);
    }

}
