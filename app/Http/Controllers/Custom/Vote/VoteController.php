<?php

namespace App\Http\Controllers\Custom\Vote;

use App\Http\Controllers\Controller;

use App\Models\User;
use App\Models\Vote;
use App\Models\VoteDetail;
use App\Models\VoteLog;
use App\Models\VoteMember;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class VoteController extends Controller
{
    
    /**
     * 添加 投票主题 a
     * 
     */
    public function postVote(Request $request) {
        $param = $request->all();
        
        //投票页背景图片
        if( !isset($param['bgpic']) || empty($param['bgpic']) ){
            return array('errcode'=>10001,'errmsg'=>'投票页背景图片 不能为空');
        }
        $data_vote['bgpic'] = $param['bgpic'];
        //投票主题
        if( !isset($param['title']) || empty($param['title']) ){
            return array('errcode'=>10001,'errmsg'=>'投票主题 不能为空');
        }
        $data_vote['title'] = $param['title'];
        //投票内容
        if( !isset($param['content']) || empty($param['content']) ){
            return array('errcode'=>10001,'errmsg'=>'投票内容 不能为空');
        }
        $data_vote['content'] = $param['content'];
        //投票页说明
        if( !isset($param['notice']) || empty($param['notice']) ){
            return array('errcode'=>10001,'errmsg'=>'投票页说明 不能为空');
        }
        $data_vote['notice'] = $param['notice'];
        //投票开始时间
        if( !isset($param['start_time']) || empty($param['start_time']) ){
            return array('errcode'=>10001,'errmsg'=>'投票开始时间 不能为空');
        }
        $data_vote['start_time'] = $param['start_time'];
        //投票结束时间
        if( !isset($param['end_time']) || empty($param['end_time']) ){
            return array('errcode'=>10001,'errmsg'=>'投票结束时间 不能为空');
        }
        $data_vote['end_time'] = $param['start_time'];
        //投票结束时间
        if( !isset($param['end_time']) || empty($param['end_time']) ){
            return array('errcode'=>10001,'errmsg'=>'投票结束时间 不能为空');
        }
        $data_vote['end_time'] = $param['start_time'];
        //每人可投票数
        if( !isset($param['person_sum']) || empty($param['person_sum']) ){
            return array('errcode'=>10001,'errmsg'=>'每人可投票数 不能为空');
        }
        $data_vote['person_sum'] = $param['person_sum'];
        //是否显示投票结果
        if( !isset($param['show_vote']) || empty($param['show_vote']) ){
            return array('errcode'=>10001,'errmsg'=>'是否显示投票结果 不能为空');
        }
        $data_vote['show_vote'] = $param['show_vote'];
        
        $data_vote['status'] = 1;
        $data_vote['is_delete'] = 1;
        $data_vote['merchant_id'] = Auth::user()->merchant_id;
        $data_vote['user_id'] = Auth::user()->id;
        
        //-------------日志 start-----------------
        $data_UserLog['merchant_id']=Auth::user()->merchant_id;
        $data_UserLog['user_id']=Auth::user()->id;
        $data_UserLog['type']=1;
        $data_UserLog['content']=json_encode($request->all());
        $data_UserLog['ip']=get_client_ip();
        $data_UserLog['created_time']=date('Y-m-d H:i:s');
        $data_UserLog['updated_time']=date('Y-m-d H:i:s');
        $rs_votelog = VoteLog::insertGetId($data_UserLog);
        //-------------日志 end-----------------
        
        $rs_vote = Vote::insert_data($data_vote);
        if($rs_vote){
            VoteLog::where(['id'=>$rs_votelog])->update_data(['vote_id'=>$rs_vote]);
            $user['errcode'] = 0;
            $user['errmsg'] = '添加成功';
            return Response::json($user, 200);
        }else{
            $user['errcode'] = 1;
            $user['errmsg'] = '添加失败';
            return Response::json($user, 200);
        }
        
    }
    
    /**
     * 修改 投票主题
     */
    public function putVote(Request $request) {
        $param = $request->all();
    
        //投票主题id
        if( !isset($param['id']) || empty($param['id']) ){
            return array('errcode'=>10001,'errmsg'=>'投票主题id 不能为空');
        }
        $rs_vote = Vote::get_data_by_id($param['id']);
        if(empty($rs_vote)){
            return array('errcode'=>10001,'errmsg'=>'查不到此投票主题');
        }
        //投票页背景图片
        if( isset($param['bgpic']) && !empty($param['bgpic']) ){
            $data_vote['bgpic'] = $param['bgpic'];
        }
        
        //投票主题
        if( !isset($param['title']) || empty($param['title']) ){
            return array('errcode'=>10001,'errmsg'=>'投票主题 不能为空');
        }
        $data_vote['title'] = $param['title'];
        //投票内容
        if( !isset($param['content']) || empty($param['content']) ){
            return array('errcode'=>10001,'errmsg'=>'投票内容 不能为空');
        }
        $data_vote['content'] = $param['content'];
        //投票页说明
        if( !isset($param['notice']) || empty($param['notice']) ){
            return array('errcode'=>10001,'errmsg'=>'投票页说明 不能为空');
        }
        $data_vote['notice'] = $param['notice'];
        //投票开始时间
        if( !isset($param['start_time']) || empty($param['start_time']) ){
            return array('errcode'=>10001,'errmsg'=>'投票开始时间 不能为空');
        }
        $data_vote['start_time'] = $param['start_time'];
        //投票结束时间
        if( !isset($param['end_time']) || empty($param['end_time']) ){
            return array('errcode'=>10001,'errmsg'=>'投票结束时间 不能为空');
        }
        $data_vote['end_time'] = $param['end_time'];
        //每人可投票数
        if( !isset($param['person_sum']) || empty($param['person_sum']) ){
            return array('errcode'=>10001,'errmsg'=>'每人可投票数 不能为空');
        }
        $data_vote['person_sum'] = $param['person_sum'];
        //是否显示投票结果
        if( !isset($param['show_vote']) || empty($param['show_vote']) ){
            return array('errcode'=>10001,'errmsg'=>'是否显示投票结果 不能为空');
        }
        $data_vote['show_vote'] = $param['show_vote'];
    
        $data_vote['user_id'] = Auth::user()->id;
    
        //-------------日志 start-----------------
        $data_UserLog['vote_id']=$param['id'];
        $data_UserLog['user_id']=Auth::user()->id;
        $data_UserLog['type']=2;
        $data_UserLog['content']=json_encode($request->all());
        $data_UserLog['ip']=get_client_ip();
        $data_UserLog['created_time']=date('Y-m-d H:i:s');
        $data_UserLog['updated_time']=date('Y-m-d H:i:s');
        $rs_votelog = VoteLog::insertGetId($data_UserLog);
        //-------------日志 end-----------------
    
        $rs_vote = Vote::update_data($param['id'], $data_vote);
        if($rs_vote){
            $user['errcode'] = 0;
            $user['errmsg'] = '修改成功';
            return Response::json($user, 200);
        }else{
            $user['errcode'] = 1;
            $user['errmsg'] = '修改失败';
            return Response::json($user, 200);
        }
    
    }

    /**
     * 投票主题 删除
     */
    public function deleteVote(Request $request) {
        $param = $request->all();
    
        //投票主题id
        if( !isset($param['id']) || empty($param['id']) ){
            return array('errcode'=>10001,'errmsg'=>'投票主题id 不能为空');
        }
        $rs_vote = Vote::get_data_by_id($param['id']);
        if(empty($rs_vote)){
            return array('errcode'=>10001,'errmsg'=>'查不到此投票主题');
        }
        //投票开关
        if( !isset($param['is_delete']) || empty($param['is_delete']) ){
            return array('errcode'=>10001,'errmsg'=>'是否删除 不能为空');
        }
        $data_vote['is_delete'] = $param['is_delete'];
    
        //-------------日志 start-----------------
        $data_UserLog['vote_id']=$param['id'];
        $data_UserLog['user_id']=Auth::user()->id;
        $data_UserLog['type']=3;
        $data_UserLog['content']=json_encode($request->all());
        $data_UserLog['ip']=get_client_ip();
        $data_UserLog['created_time']=date('Y-m-d H:i:s');
        $data_UserLog['updated_time']=date('Y-m-d H:i:s');
        $rs_votelog = VoteLog::insertGetId($data_UserLog);
        //-------------日志 end-----------------
    
        $rs_vote = Vote::update_data($param['id'], $data_vote);
        if($rs_vote){
            $user['errcode'] = 0;
            $user['errmsg'] = '删除成功';
            return Response::json($user, 200);
        }else{
            $user['errcode'] = 1;
            $user['errmsg'] = '添加失败';
            return Response::json($user, 200);
        }
    
    }
    
    /**
     * 投票开关 开启/关闭
     */
    public function putVoteSwitch(Request $request) {
        $param = $request->all();
    
        //投票主题id
        if( !isset($param['id']) || empty($param['id']) ){
            return array('errcode'=>10001,'errmsg'=>'投票主题id 不能为空');
        }
        $rs_vote = Vote::get_data_by_id($param['id']);
        if(empty($rs_vote)){
            return array('errcode'=>10001,'errmsg'=>'查不到此投票主题');
        }
        //投票开关
        if( !isset($param['status']) || empty($param['status']) ){
            return array('errcode'=>10001,'errmsg'=>'投票开关 不能为空');
        }else if( !in_array($param['status'], array(1,2,3))){
            return array('errcode'=>10001,'errmsg'=>'投票开关 设置出错');
        }
        $data_vote['status'] = $param['status'];
    
        //-------------日志 start-----------------
        $data_UserLog['vote_id']=$param['id'];
        $data_UserLog['user_id']=Auth::user()->id;
        $data_UserLog['type']=7;
        $data_UserLog['content']=json_encode($request->all());
        $data_UserLog['ip']=get_client_ip();
        $data_UserLog['created_time']=date('Y-m-d H:i:s');
        $data_UserLog['updated_time']=date('Y-m-d H:i:s');
        $rs_votelog = VoteLog::insertGetId($data_UserLog);
        //-------------日志 end-----------------
    
        $rs_vote = Vote::update_data($param['id'], $data_vote);
        if($rs_vote){
            $user['errcode'] = 0;
            $user['errmsg'] = '切换 成功';
            return Response::json($user, 200);
        }else{
            $user['errcode'] = 1;
            $user['errmsg'] = '切换 失败';
            return Response::json($user, 200);
        }
    
    }
    
    /**
     * 获取 投票主题 列表
     */
    public function getVoteList(Request $request) {
        $params = $request->all();
        $offset = isset($params['offset']) ? $params['offset'] : 0;
        $limit = isset($params['limit']) ? $params['limit'] : 20;

        $wheres = array(
            array('column'=>'is_delete', 'value'=>1, 'operator'=>'=')
        );
        //按主题名称搜索
        if(isset($params['search']) && $params['search']){
            $where = array(
                array('column'=>'title', 'value'=>"%".$params['search']."%", 'operator'=>'like'),
            );
            $wheres = array_merge($wheres,$where);
        }
        $query = Vote::query();
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        $count = $query->count();
        $query->skip($offset);
        $query->take($limit);
        $rs_vote = $query->get();

        $data['errcode'] = 0;
        $data['errmsg'] = '获取成功';
        $data['data'] = $rs_vote;
        $data['_count'] = $count;
        return Response::json($data, 200);
    }


    /**
     * 添加 投票选项
     */
    public function postVoteDetail(Request $request) {
        $param = $request->all();
    
        //投票主题id
        if( !isset($param['vote_id']) || empty($param['vote_id']) ){
            return array('errcode'=>10001,'errmsg'=>'投票主题id 不能为空');
        }
        $rs_vote = Vote::get_data_by_id($param['vote_id']);
        if(empty($rs_vote)){
            return array('errcode'=>10001,'errmsg'=>'查不到此投票主题');
        }
        $data_vote['vote_id'] = $param['vote_id'];
        //投票选项标题
        if( !isset($param['title']) || empty($param['title']) ){
            return array('errcode'=>10001,'errmsg'=>'投票选项标题 不能为空');
        }
        $data_vote['title'] = $param['title'];
        //投票选项排序
        if( !isset($param['order_num']) || empty($param['order_num']) ){
            return array('errcode'=>10001,'errmsg'=>'投票选项排序 不能为空');
        }
        $data_vote['order_num'] = $param['order_num'];
        
        $data_vote['is_delete'] = 1;
    
        //-------------日志 start-----------------
        $data_UserLog['vote_id']=$param['vote_id'];
        $data_UserLog['user_id']=Auth::user()->id;
        $data_UserLog['type']=4;
        $data_UserLog['content']=json_encode($request->all());
        $data_UserLog['ip']=get_client_ip();
        $data_UserLog['created_time']=date('Y-m-d H:i:s');
        $data_UserLog['updated_time']=date('Y-m-d H:i:s');
        $rs_votelog = VoteLog::insertGetId($data_UserLog);
        //-------------日志 end-----------------
    
        $rs_vote_detail = VoteDetail::insert_data($data_vote);
        if($rs_vote_detail){
            $key = CacheKey::get_vote_by_vote_id($param['vote_id']);
            Cache::forget($key);
            
            Vote::where(['id'=>$param['vote_id']])->increment('total_items');
            
            VoteLog::update_data($rs_votelog, array('vote_details_id'=>$rs_vote_detail));
            $user['errcode'] = 0;
            $user['errmsg'] = '添加成功';
            return Response::json($user, 200);
        }else{
            $user['errcode'] = 1;
            $user['errmsg'] = '添加失败';
            return Response::json($user, 200);
        }
    
    }
    
    /**
     * 修改 投票选项
     */
    public function putVoteDetail(Request $request) {
        $param = $request->all();
    
        //投票主题id
        if( !isset($param['vote_id']) || empty($param['vote_id']) ){
            return array('errcode'=>10001,'errmsg'=>'投票主题id 不能为空');
        }
        $rs_vote = Vote::get_data_by_id($param['vote_id']);
        if(empty($rs_vote)){
            return array('errcode'=>10001,'errmsg'=>'查不到此投票主题');
        }
        //投票选项id
        if( !isset($param['id']) || empty($param['id']) ){
            return array('errcode'=>10001,'errmsg'=>'投票选项id 不能为空');
        }
        $rs_vote_detail = VoteDetail::get_data_by_id($param['vote_id'], $param['id']);
        if(empty($rs_vote_detail)){
            return array('errcode'=>10001,'errmsg'=>'查不到此投票选项');
        }
        //投票选项标题
        if( !isset($param['title']) || empty($param['title']) ){
            return array('errcode'=>10001,'errmsg'=>'投票选项标题 不能为空');
        }
        $data_vote['title'] = $param['title'];
        //投票选项排序
        if( !isset($param['order_num']) || empty($param['order_num']) ){
            return array('errcode'=>10001,'errmsg'=>'投票选项排序 不能为空');
        }
        $data_vote['order_num'] = $param['order_num'];
    
        //-------------日志 start-----------------
        $data_UserLog['vote_id']=$param['vote_id'];
        $data_UserLog['vote_details_id']=$param['id'];
        $data_UserLog['user_id']=Auth::user()->id;
        $data_UserLog['type']=5;
        $data_UserLog['content']=json_encode($request->all());
        $data_UserLog['ip']=get_client_ip();
        $data_UserLog['created_time']=date('Y-m-d H:i:s');
        $data_UserLog['updated_time']=date('Y-m-d H:i:s');
        $rs_votelog = VoteLog::insertGetId($data_UserLog);
        //-------------日志 end-----------------
    
        $rt_vote_detail = VoteDetail::update_data($param['vote_id'], $param['id'], $data_vote);
        if($rt_vote_detail){
            $user['errcode'] = 0;
            $user['errmsg'] = '修改 成功';
            return Response::json($user, 200);
        }else{
            $user['errcode'] = 1;
            $user['errmsg'] = '修改 失败';
            return Response::json($user, 200);
        }
    
    }
    
    /**
     * 投票选项 删除
     */
    public function deleteVoteDetail(Request $request) {
        $param = $request->all();
        //投票主题id
        if( !isset($param['vote_id']) || empty($param['vote_id']) ){
            return array('errcode'=>10001,'errmsg'=>'投票主题id 不能为空');
        }
        $rs_vote = Vote::get_data_by_id($param['vote_id']);
        if(empty($rs_vote)){
            return array('errcode'=>10001,'errmsg'=>'查不到此投票主题');
        }
        //投票选项id
        if( !isset($param['id']) || empty($param['id']) ){
            return array('errcode'=>10001,'errmsg'=>'投票选项id 不能为空');
        }
        $rs_vote_detail = VoteDetail::get_data_by_id($param['vote_id'], $param['id']);
        if(empty($rs_vote_detail)){
            return array('errcode'=>10001,'errmsg'=>'查不到此投票选项');
        }
        
        $data_vote['is_delete'] = $param['is_delete'];
    
        //-------------日志 start-----------------
        $data_UserLog['vote_id']=$param['vote_id'];
        $data_UserLog['vote_details_id']=$param['id'];
        $data_UserLog['user_id']=Auth::user()->id;
        $data_UserLog['type']=6;
        $data_UserLog['content']=json_encode($request->all());
        $data_UserLog['ip']=get_client_ip();
        $data_UserLog['created_time']=date('Y-m-d H:i:s');
        $data_UserLog['updated_time']=date('Y-m-d H:i:s');
        $rs_votelog = VoteLog::insertGetId($data_UserLog);
        //-------------日志 end-----------------
    
        $rt_vote_detail = VoteDetail::update_data($param['vote_id'], $param['id'], $data_vote);
        if($rt_vote_detail){
            //投票主题 投票项数量 少了1个
            $key = CacheKey::get_vote_by_vote_id($param['vote_id']);
            Cache::forget($key);
            Vote::where(['id'=>$param['vote_id']])->decrement('total_items');
            
            //投票表 删除此投票选项的记录
            $key_all_votedetail = CacheKey::get_all_vote_details_by_vote_id($param['vote_id']);
            Cache::forget($key_all_votedetail);
            $key = CacheKey::get_vote_detail_by_vote_detail_id($param['vote_id'],$param['id']);
            Cache::forget($key);
            VoteMember::where(['vote_details_id'=>$param['id']])->update(array('is_delete'=>-1));
            
            //投票主题 参与人数从投票表中计算
            $rs_vote_member = VoteMember::where(['vote_id'=>$param['vote_id'],'is_delete'=>1])->distinct('member_id')->count('member_id');
            Vote::update_data($param['vote_id'], array('total_peoples'=>$rs_vote_member));
            //投票主题 累计投票数减少
            Vote::where(['id'=>$param['vote_id']])->decrement('total_votes',$rs_vote_detail['total_count']);
            
            $user['errcode'] = 0;
            $user['errmsg'] = '删除 成功';
            return Response::json($user, 200);
        }else{
            $user['errcode'] = 1;
            $user['errmsg'] = '删除 失败';
            return Response::json($user, 200);
        }
    
    }
    
    /**
     * 获取 投票选项 列表
     */
    public function getVoteDetailList(Request $request) {
        //投票主题
        $rs_vote = Vote::get_data_by_id($request['vote_id']);
        if( empty($rs_vote) ){
            $rs_data['errcode'] = 100001;
            $rs_data['errmsg'] = '查不到此投票主题';
            $rs_data['data'] = [];
            return Response::json($rs_data);
        }
        
        //投票选项
        $rs_vote_detail = VoteDetail::get_alldata_by_vote_id($request['vote_id']);
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
        return Response::json($rs_data);
    }
    
    /**
     * 手工清缓存
     */
    public function getflushcache() {
        //清除 投票主题 缓存
        $rs_vote = Vote::query()->get();
        if(!empty($rs_vote)){
            foreach ($rs_vote as $key1=>$val1){
                $redis_key = '';
                $redis_key = CacheKey::get_vote_by_vote_id($val1['id']);
                Cache::forget($redis_key);
                
                $redis_key = '';
                $redis_key = CacheKey::get_all_vote_details_by_vote_id($val1['id']);
                Cache::forget($redis_key);
            }
        }
        
        //清除 投票选项 缓存
        $rs_vote_detail = VoteDetail::get_alldata_by_vote_id($redis_key);
        if(!empty($rs_vote_detail)){
            foreach ($rs_vote_detail as $key3=>$val3){
                $redis_key = '';
                $redis_key = CacheKey::get_vote_detail_by_vote_detail_id($val3['vote_id'], $val3['id']);
                Cache::forget($redis_key);
            }
        }
        
        //清除 会员投票 缓存
        $rs_vote_member = VoteMember::query()->groupBy('vote_id')->groupBy('member_id')->get();
        if(!empty($rs_vote_member)){
            foreach ($rs_vote_member as $key2=>$val2){
                $redis_key = '';
                $redis_key = CacheKey::get_vote_member_by_vote_id($val2['vote_id'], $val2['member_id']);
                Cache::forget($redis_key);
            }
        }
        return array('errcode'=>0,'errmsg'=>'清除缓存 成功');
    }
    
    //去掉重复项
    private function _handleArray2d($array) {
        if(!is_array($array)) {
            return array();
        }

        $tempid = array();
        $result = array();
        foreach($array as $key=>$val) {
            if(!in_array($val['id'],$tempid)) {
                $tempid[] = $val['id'];
                $result[] = $val;
            }
        }
        return $result;
    }

    //去掉数组中值为空的项
    private function array_filter_empty($arr) {
        $res = array();
        if(!$arr) {
            return $res;
        }
        foreach($arr as $v) {
            if($v) {
                $res[] = $v;
            }
        }
        return $res;
    }
}
