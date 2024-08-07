<?php

namespace App\Http\Controllers\Custom\Vote;

use App\Models\Vote;
use App\Models\VoteDetail;
use App\Models\VoteMember;
use App\Models\VoteLog;
use App\Http\Controllers\Controller;

use App\Facades\Member;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Http\Request;

class VoteappController extends Controller
{

    public function __construct() {
        $this->config_err = config('err');
        $this->merchant_id = Member::merchant_id();
        $this->member_id = Member::id();
    }

    /**
     * 投票选项 列表
     * @return \Illuminate\Http\Response
     */
    public function getVoteDetail(Request $Request) {
        //投票主题
        $rs_vote = Vote::get_data_by_id($Request['vote_id']);
        if( empty($rs_vote) ){
            $rs_data['errcode'] = 100001;
            $rs_data['errmsg'] = '查不到此投票';
            $rs_data['data'] = [];
            return Response::json($rs_data);
        }
        
        //会员投票详情
        $arr_vote_member = array();
        $rs_vote_member = VoteMember::get_data_by_vote_id($Request['vote_id'], $this->member_id, 'vote_details_id');
        if(!empty($rs_vote_member)){
            foreach ($rs_vote_member as $key=>$val){
                $arr_vote_member[] = $val['vote_details_id'];
            }
        }
        
        //投票选项
        $rs_vote_detail = VoteDetail::get_alldata_by_vote_id($Request['vote_id']);
        if( empty($rs_vote) ){
            $rs_data['errcode'] = 100001;
            $rs_data['errmsg'] = '查不到此投票选项';
            $rs_data['data'] = [];
            return Response::json($rs_data);
        }
        
        $rs_data['errcode'] = 0;
        $rs_data['errmsg'] = '返回成功';
        $rs_data['data']['vote'] = $rs_vote;
        $rs_data['data']['vote_detail'] = $rs_vote_detail;
        $rs_data['data']['vote_member'] = $arr_vote_member;
        return Response::json($rs_data);
    }

    /**
     * 会员投票
     * @return array
     * @author: songyongshang@dodoca.com
     */
    public function postVoteMember(Request $Request) {
        //会员投票
        $arr_votes_ids = array_unique($Request['vote_ids']);
        if( !isset($arr_votes_ids) || empty($arr_votes_ids) ){
            $rs_data['errcode'] = 100001;
            $rs_data['errmsg'] = '请选择投票项';
            $rs_data['data'] = [];
            return Response::json($rs_data);
        }else if( count($arr_votes_ids)!=3 ){
            $rs_data['errcode'] = 100002;
            $rs_data['errmsg'] = '请选择3个节目再提交投票';
            $rs_data['data'] = [];
            return Response::json($rs_data);
        }
        //投票主题id
        if( !isset($Request['vote_id']) || empty($Request['vote_id']) ){
            $rs_data['errcode'] = 100004;
            $rs_data['errmsg'] = '投票主题id 不能为空';
            $rs_data['data'] = [];
            return Response::json($rs_data);
        }
        $rs_vote = Vote::get_data_by_id($Request['vote_id']);
        if( empty($rs_vote) ){
            $rs_data['errcode'] = 100001;
            $rs_data['errmsg'] = '查不到此投票主题';
            $rs_data['data'] = [];
            return Response::json($rs_data);
        }else if($rs_vote['status']!=2){
            $rs_data['errcode'] = 100001;
            $rs_data['errmsg'] = '不可投票';
            $rs_data['data'] = [];
            return Response::json($rs_data);
        }
        //是否已参加过投票
        $rs_vote_detail = VoteMember::get_data_by_vote_id($Request['vote_id'], $this->member_id);
        if(!empty($rs_vote_detail)){
            $rs_data['errcode'] = 100001;
            $rs_data['errmsg'] = '已经投过票啦,投票失败.';
            $rs_data['data'] = [];
            return Response::json($rs_data);
        }
        //投票选项
        foreach ($arr_votes_ids as $key=>$val){
            $rs_vote_detail = VoteDetail::get_data_by_id($Request['vote_id'], $val);
            if(empty($rs_vote_detail)){
                $rs_data['errcode'] = 100001;
                $rs_data['errmsg'] = '投票选项 无效';
                $rs_data['data'] = [];
                return Response::json($rs_data);
            }
        }
        DB::connection('applet_cust')->beginTransaction();
        try{
            foreach ($arr_votes_ids as $key=>$val){
                $data_votemember = $data_UserLog = array();
                
                //投票记录
                $data_votemember['vote_id'] = $Request['vote_id'];
                $data_votemember['vote_details_id'] = $val;
                $data_votemember['member_id'] = $this->member_id;
                $data_votemember['is_delete'] = 1;
                VoteMember::insert_data($data_votemember);
                
                //投票选项
                //清除缓存 单个投票选项
                $key = CacheKey::get_vote_detail_by_vote_detail_id($Request['vote_id'], $val);
                Cache::forget($key);
        
                VoteDetail::where(['id'=>$val])->increment('total_count');
                
                //-------------日志 start-----------------
                $data_UserLog['type']=8;
                $data_UserLog['vote_id']=$Request['vote_id'];
                $data_UserLog['vote_details_id']=$val;
                $data_UserLog['member_id']=$this->member_id;
                $data_UserLog['created_time']=date('Y-m-d H:i:s');
                $data_UserLog['updated_time']=date('Y-m-d H:i:s');
                VoteLog::insertGetId($data_UserLog);
                //-------------日志 end-----------------
            }
            //投票主题
            $key = CacheKey::get_vote_by_vote_id($Request['vote_id']);
            Cache::forget($key);
            //清除缓存 所有投票选项
            $key_all_votedetail = CacheKey::get_all_vote_details_by_vote_id($Request['vote_id']);
            Cache::forget($key_all_votedetail);
            
            //投票主题
            Vote::where(['id'=>$Request['vote_id']])->increment('total_peoples');
            Vote::where(['id'=>$Request['vote_id']])->increment('total_votes',count($arr_votes_ids));
            
            DB::connection('applet_cust')->commit();
        }catch (\Exception $e) {
            DB::connection('applet_cust')->rollBack();
            
            $rt['errcode']=110018;
            $rt['errmsg']='投票 失败';
            return Response::json($rt);
        }
        
        $rt['errcode']=0;
        $rt['errmsg']='投票 成功';
        return Response::json($rt);
    }

    

}
