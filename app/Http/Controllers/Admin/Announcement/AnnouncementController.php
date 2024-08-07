<?php
/**
 * Created by PhpStorm.
 * User: lizhenheng
 * Date: 2017-11-29
 * Time: 下午 04:00
 */
namespace App\Http\Controllers\Admin\Announcement;


use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Response;
use App\Http\Requests;
use Illuminate\Support\Facades\Auth;
use App\Models\AnnouncementUser;
use App\Models\Announcement;
use App\Models\Feedback;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class AnnouncementController extends Controller {
    protected $request;
    protected $params;

    public function __construct(Request $request) {
        $this->request = $request;
        $this->params = $request->all();
        $this->merchant_id = Auth::user()->merchant_id;
        $this->user_id = Auth::user()->id;
        $key = CacheKey::get_announce_by_id_key();
        $data = Cache::get($key);
        $this->version_id=$data;
    }
    //查询未读数
    public function unreadCount(){
        $announcementuser_query=AnnouncementUser::select("*");
        $announcementuser_query->where('user_id','=',$this->user_id);
        $announcementuser_query->orderBy('version_id','desc');
        $announcementuser = $announcementuser_query->first();

        //查询是否显示弹框
        $query=Announcement::select("*");
        $query->where('status','=',1);
        $query->where('pop_up','=',1);
        $data=$query->first();
        $count=0;//已读数
        if($data){
            $pop_up=1; //弹窗
        }else{
            $pop_up=0; //不弹窗
        }
        $announcement_ids=array();
        if($announcementuser["announcement_id"]){
            $announcement_ids=json_decode($announcementuser["announcement_id"],true);
        }
        if($announcementuser['version_id']==$this->version_id){
            if(in_array($data["id"],$announcement_ids)){
                $data=array();
                $pop_up=0; //不弹窗
            }
        }elseif($announcementuser['version_id']<$this->version_id){
            if($data){
                $pop_up=1;
            }
        }elseif ($announcementuser['version_id']>$this->version_id) {
            if (in_array($data["id"], $announcement_ids)) {
                $data = array();
                $pop_up=0; //不弹窗
            }
        }
        $query=Announcement::select("id");
        $query->where('status','=',1);
        $announcements=$query->count();//全部文章
        $announcements_list=$query->get();
        if($announcements_list){
            foreach ($announcements_list as $key=>$val){
                if(in_array($val["id"],$announcement_ids)){
                    $announcements--;
                }
            }
        }
        if($data){
            if(in_array($data["id"],$announcement_ids)){
                if($announcementuser['version_id']<$this->version_id){
                    $announcements++;
                }
            }
        }

        //未读数
        $feedback_query = Feedback::select('id');
        $feedback_count = $feedback_query->where(array('user_id'=>$this->user_id,'is_delete'=>1,'unread'=>0))->count();
        if($feedback_count){
            $unreads = $announcements + $feedback_count;
        }else{

            $unreads = $announcements;
        }

        $res['errcode'] = 0;
        $res['unreads'] = $unreads;
        $res['data'] = $data;
        $res['pop_up']=$pop_up;
        return Response :: json($res);
    }

    //公告列表
    public function announcementList(){
        $offset = isset($this->params['offset']) && $this->params['offset'] ? $this->params['offset'] : 0;
        $limit = isset($this->params['limit']) && $this->params['limit'] ? $this->params['limit'] : 10;
        $query=Announcement::select("*");
        $query->where('status','=',1);
        $count = $query->count();
        $query->skip($offset);
        $query->take($limit);
        $query->orderBy('created_time','desc');
        $list = $query->get();
        if($list){
            $announcementuser_query=AnnouncementUser::select("*");
            $announcementuser_query->where('user_id','=',$this->user_id);
            $announcementuser_query->orderBy('version_id','desc');
            $announcementuser = $announcementuser_query->first();
            $announcement_ids=array();
            if($announcementuser["announcement_id"]){
                $announcement_ids=json_decode($announcementuser["announcement_id"],true);
            }
            foreach($list as $key=>$val){
                $list[$key]['is_reads']=0; //未读
                if(in_array($val["id"],$announcement_ids)){
                    $list[$key]['is_reads']=1; //已读
                }
            }
        }

        $data['errcode'] = 0;
        $data['_count'] = $count;
        $data['data'] = $list;
        return Response :: json($data);
    }

    //公告详情
    public function announcementDetail(){
        $id = isset($this->params['id']) && $this->params['id'] ? $this->params['id'] : 0;
        $query=Announcement::select("*");
        $query->where('status','=',1);
        $query->where('id','=',$id);
        $list=$query->first();
        $data['errcode'] = 0;
        $data['_count'] = 1;
        $data['data'] = $list;
        return Response :: json($data);
    }

    //标记为已读
    public function announcementRead(){
        $id = isset($this->params['id']) && $this->params['id'] ? $this->params['id'] : 0;
        $announcementuser_query=AnnouncementUser::select("*");
        $announcementuser_query->where('user_id','=',$this->user_id);
        $announcementuser_query->orderBy('version_id','desc');
        $announcementuser = $announcementuser_query->first();
        $announcement_ids=array();
        if($announcementuser["announcement_id"]) {
            $announcement_ids = json_decode($announcementuser["announcement_id"], true);
        }

        if($id){
            if($announcementuser['version_id']>=$this->version_id){ //版本回退的时候
                if(!in_array($id,$announcement_ids)){
                    array_push($announcement_ids,$id);
                    $data=array();
                    $data['announcement_id']=json_encode($announcement_ids);
                    $data['updated_time']=date('Y-m-d H:i:s');
                    $where=[];
                    $where[]=['column' => 'user_id', 'value' => $this->user_id, 'operator' => '='];
                    $where[]=['column' => 'version_id', 'value' => $announcementuser['version_id'], 'operator' => '='];
                    AnnouncementUser::update_data_by_where($where,$data);
                }
            }else{
                if($announcement_ids){
                    if(!in_array($id,$announcement_ids)){
                        array_push($announcement_ids,$id);
                    }
                    $data=array();
                    $data['user_id']=$this->user_id;
                    $data['version_id']=$this->version_id;
                    $data['announcement_id']=json_encode($announcement_ids);
                    $data['created_time']=date('Y-m-d H:i:s');
                    AnnouncementUser::insert($data);
                }else{
                    $data=array();
                    $data['user_id']=$this->user_id;
                    $data['version_id']=$this->version_id;
                    $data['announcement_id']=json_encode(array($id));
                    $data['created_time']=date('Y-m-d H:i:s');
                    AnnouncementUser::insert($data);
                }
            }
        }
        $announcementuser_query=AnnouncementUser::select("*");
        $announcementuser_query->where('user_id','=',$this->user_id);
        $announcementuser_query->orderBy('version_id','desc');
        $announcementuser = $announcementuser_query->first();
        $count=0;
        $announcement_ids=array();
        if($announcementuser["announcement_id"]){
            $announcement_ids=json_decode($announcementuser["announcement_id"],true);
        }
        $query=Announcement::select("id");
        $query->where('status','=',1);
        $announcements=$query->count();//全部文章
        $announcements_list=$query->get();
        if($announcements_list){
            foreach ($announcements_list as $key=>$val){
                if(in_array($val["id"],$announcement_ids)){
                    $announcements--;
                }
            }
        }

        //未读数
        $feedback_query = Feedback::select('id');
        $feedback_count = $feedback_query->where(array('user_id'=>$this->user_id,'is_delete'=>1,'unread'=>0))->count();
        if($feedback_count){
            $unreads = $announcements + $feedback_count;
        }else{

            $unreads = $announcements;
        }
        $res['errcode'] = 0;
        $res['_count'] = 0;
        $res['unreads'] = $unreads;
        $res['data'] = '';
        return Response :: json($res);

    }
}
