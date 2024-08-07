<?php

namespace App\Http\Controllers\Admin\Index;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Feedback;
use App\Models\FeedbackReply;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    protected $params;

    protected $feedback;

    protected  $feedbackreply;

    public function __construct(Request $request,Feedback $feedback,FeedbackReply $feedbackreply)
    {
        $this->params =  $request->all();

        $this->feedback = $feedback;

        $this->feedbackreply = $feedbackreply;

    }
    /**
     * 提交意见反馈
     */
    function postFeedback(Request $request){
        //反馈内容
        if( !isset($request['content']) || empty($request['content']) ){
            $rt['errcode']=110001;
            $rt['errmsg']='反馈内容 不能为空';
            return Response::json($rt);
        }

        $data_feedback['merchant_id'] = Auth::user()->merchant_id;;    //Auth::user()->merchant_id;
        $data_feedback['user_id'] = Auth::user()->id;        //Auth::user()->id;
        $data_feedback['content'] = $request['content'];
        $data_feedback['status']=0;
        $data_feedback['created_time']=date('Y-m-d H:i:s');
        $data_feedback['updated_time']=date('Y-m-d H:i:s');

        $rs_feedback = Feedback::insertGetId($data_feedback);
        if(!$rs_feedback){
            $rt['errcode']=110002;
            $rt['errmsg']='提交 失败';
            $rt['data'] = array();
            return Response::json($rt);
        }

        $rt['errcode']=0;
        $rt['errmsg']='提交 成功';
        $rt['data'] = array('');
        return Response::json($rt);
    }

    /**
     * 意见反馈列表
     * Author: songyongshang@dodoca.com
     */
    public function getFeedbackList() {

        $wheres[] = array('column' => 'user_id', 'value' => Auth::user()->id, 'operator' => '=');    //Auth::user()->id

        $offset = isset($this->params['offset']) ? intval($this->params['offset']) : 0;

        $limit = isset($this->params['limit']) ? intval($this->params['limit']) : 10;

        $result = Feedback::get_data_list($wheres, 'id,content,created_time,updated_time,status,unread', $offset, $limit);

        $data['errcode'] = 0;

        $data['_count'] = Feedback::get_data_count($wheres);

        $data['data'] = $result;

        return Response :: json($data);
    }

    /**
     * 意见反馈当日次数
     * Author: zhangyu1@dodoca.com
     */
    public function getTodayCount(){

        $query = $this->feedback->select('*');

        $query->where('user_id','=',Auth::user()->id);

        $count = $query->wheredate('created_time','=',date('Y-m-d'))->count();

        $rt['errcode']=0;

        $rt['errmsg']='查询 成功';

        $rt['count'] = $count;

        return Response :: json($rt);

    }

    /**
     * 意见反馈详情
     * Author: songyongshang@dodoca.com
     */
    public function getFeedbackDetail(Request $request) {

        if( !isset($request->id) || empty($request->id) ){

            $rt['errcode']=110001;

            $rt['errmsg']='意见id 不能为空';

            return Response::json($rt);
        }

        $data['errcode'] = 0;

        $data['errmsg'] = '获取成功';

        $feedback_id = $request->id;

        $wheres[] = array('column' => 'id', 'value' => $feedback_id, 'operator' => '=');

        $feedback = $this->feedback->get_data_list($wheres,'*');

        //$all['question'] = Feedback::get_data_by_id((int)$request->id,'*');   //问题

        $all['question'] = $feedback[0];

        if($feedback[0]['status'] == 1){

            $wheres_reply[] = array('column' => 'feedback_id', 'value' => (int)$request->id, 'operator' => '=');

            $reply = $this->feedbackreply->get_data_list($wheres_reply,'*');

            $reply = $reply[0];

        }else{

            $reply = '';
        }

        $all['reply'] = $reply;

        $data['data'] = $all;

        return Response :: json($data);
    }

    /**
     * 标记已读
     */
    public function  getFeedRead(Request $request){

        if( !isset($request->id) || empty($request->id) ){

            $rt['errcode']=110001;

            $rt['errmsg']='意见id 不能为空';

            return Response::json($rt);
        }

        $data['unread'] = 1;

        $result = $this->feedback->update_data($request->id,$data);

        return $result ? Response::json(['errcode'=>0,'errmsg'=>'更新成功']) : Response::json(['errcode'=>10002,'errmsg'=>'更新失败']);

    }
}
