<?php
/**
 * Created by PhpStorm.
 * User: zhangyu1@dodoca.com
 * Date: 2018/2/6
 * Time: 10:36
 */

namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use App\Models\SuperUser;
use App\Models\Feedback;
use App\Models\FeedbackReply;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;


class FeedbackController extends Controller
{
    protected  $feedback;

    protected  $feedbackreply;

    protected $params;

    public function __construct(Request $request,Feedback $feedback,FeedbackReply $feedbackreply)
    {
        $this->params =  $request->all();

        $this->feedback = $feedback;

        $this->feedbackreply = $feedbackreply;
    }

    /**
     * 意见反馈列表
     * Author: zhangyu1@dodoca.com
     */
    public function getFeedbackList() {

        $offset = isset($this->params['offset']) ? intval($this->params['offset']) : 0;

        $limit = isset($this->params['limit']) ? intval($this->params['limit']) : 10;

        $query=Feedback::select('feedback.id','feedback.content','feedback.status','feedback.created_time','merchant.company','merchant.version_id','user.username','feedback.merchant_id')->leftJoin("merchant","feedback.merchant_id","=","merchant.id")->leftJoin("user","feedback.user_id","=","user.id");

        $query->where('feedback.is_delete',1);

        if (!empty($this->params['startTime'])) {  //创建时间
            $query->where('feedback.created_time','>=',$this->params['startTime']);
        }

        if (!empty($this->params['endTime'])) {  //创建时间
            $query->where('feedback.created_time','<=',$this->params['endTime']);
        }

        if (($this->params['status']==1 || $this->params['status']==0) && $this->params['status'] != '' ) {  //回复状态

            $query->where('feedback.status','=',$this->params['status']);
        }

        if (!empty($this->params['name'])) {  //商户公司名称

            $query->where('user.username', "like", '%' . $this->params['name'] . '%');
        }

        $count = $query->count();

        $query->skip($offset);

        $query->take($limit);

        $query->orderBy('feedback.created_time','desc');

        $list = $query->get();

        $data['errcode'] = 0;

        $data['errmsg'] = '意见反馈列表 查询成功';

        $data['_count'] = $count;

        $data['data'] = $list;

        return Response :: json($data);
    }

    /**
     * 意见反馈详情
     * Author: zhangyu1@dodoca.com
     */
    public function getFeedbackDetail(Request $request){

        $feedback_id = $request->id;

        $wheres[] = array('column' => 'id', 'value' => $feedback_id, 'operator' => '=');

        $feedback_result = $this->feedback->get_data_list($wheres,'*');

        if(empty($feedback_result[0])){

            return Response::json(['errcode'=>10001,'errmsg'=>'记录不存在']);
        }

        if($feedback_result[0]['status'] == 1){

            $wheres_reply[] = array('column' => 'feedback_id', 'value' => $feedback_id, 'operator' => '=');

            $reply = $this->feedbackreply->get_data_list($wheres_reply,'*');

            $reply = $reply[0];

        }else{

            $reply = '';
        }

        $all['question'] = $feedback_result[0];

        $all['reply'] = $reply;

        $data['errcode'] = 0;

        $data['errmsg'] = '意见反馈详情 查询成功';

        $data['data'] = $all;

        return Response :: json($data);
    }

    /**
     * 意见反馈回复
     * Author: zhangyu1@dodoca.com
     */
    public function postFeedbackData(Request $request){

        $feedback_id = $request->id;

        $data['feedback_id'] = $feedback_id;

        $data['superuser_id'] = Session::get('super_user.id');

        if(empty($this->params['content'])){

            return Response::json(['errcode'=>10001,'errmsg'=>'反馈信息不可为空']);

        }else{

            $data['content'] = $this->params['content'];
        }

        $result = $this->feedbackreply->insert_data($data);

        if(!$result){

            $rt['errcode']=110002;

            $rt['errmsg']='提交 失败';

            $rt['data'] = array();

            return Response::json($rt);

        }else{

            $feed_data['status'] = 1;

            $feed_data['unread'] = 0;

            $this->feedback->update_data($feedback_id,$feed_data);

            return Response::json(['errcode'=>0,'errmsg'=>'回复 成功']);
        }

    }



}
