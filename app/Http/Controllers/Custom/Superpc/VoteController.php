<?php

namespace App\Http\Controllers\Custom\Superpc;

use App\Models\Vote;
use App\Models\VoteDetail;
use App\Models\VoteMember;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Response;
use App\Facades\Member;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;

class VoteController extends Controller
{

    public function __construct() {
        $this->config_err = config('err');
        $this->merchant_id = Member::merchant_id();
        $this->member_id = Member::id();
    }


   /**
    * 投票活动列表
    */
    public function getVoteLists(Request $request){

        $params = $request->all();

        $offset = isset($params['offset']) ? $params['offset'] : 0;

        $limit = isset($params['limit']) ? $params['limit'] : 10;

        $query = Vote::select('*');

        $query->where('is_delete','=',1);

        if(!empty($params['title'])){     //活动名称查询

            $query->where('title', 'like', '%' . $params['title'] . '%');
        }

        $count = $query->count();

        $query->orderby('created_time','desc');

        $query->skip($offset);

        $query->take($limit);

        $list = $query->get();

        return Response::json(['errcode'=>0,'_count'=>$count,'data'=>$list]);

    }

    /**
     * 更改活动状态
     */

    public function getVoteStatus(Request $request){

        if(!isset($request->id) || ((!is_array($request->id)) && intval($request->id) == 0)){

            $res['errcode'] = 10001;

            $res['errmsg'] = '参数缺失,活动id不能为空';

            return Response::json($res);
        }

        $vote_id = $request->id;

        if(empty($request['op'])){

            $rt['errcode']=100002;

            $rt['errmsg']='操作 不能为空';

            return Response::json($rt);
        }

        if($request['op'] == 'start'){

            $data['status'] = '2';

            $data['updated_time'] = date('Y-m-d H:i:s');

            $op_res = Vote::update_data($vote_id,$data);

        }elseif ($request['op'] == 'stop'){

            $data['status'] = '1';

            $data['updated_time'] = date('Y-m-d H:i:s');

            $op_res = Vote::update_data($vote_id,$data);

        }else{

            $op_res ='';
        }

        if(!empty($op_res)){

            $rt['errcode']=0;

            $rt['errmsg']='操作成功';

            return Response::json($rt);

        }


    }



}
