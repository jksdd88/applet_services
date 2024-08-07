<?php

namespace App\Http\Controllers\Admin\Attachment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\VirtualMember;

class VirtualMemberController extends Controller {
    /**
     * 获取虚拟人物列表
     */
    public function getMembers(Request $request) {
        $merchant_id = Auth::user()->merchant_id;
        $params = $request->all();
        $offset = isset($params['offset']) ? $params['offset'] : 0;
        $limit = isset($params['limit']) ? $params['limit'] : 20;

        $where = ['virtual_member.merchant_id'=>$merchant_id,'virtual_member.is_delete'=>1];
        if(isset($params['search']) && $params['search']){
            $where['virtual_member.nickname'] = $params['search'];
        }
        $query = VirtualMember::select('virtual_member.id','virtual_member.nickname','virtual_member.avatar','member_card.card_name','virtual_member.created_time','virtual_member.updated_time');
        $query->where($where);
        $query->leftJoin('member_card', 'member_card.id', '=', 'virtual_member.level');

        $count = $query->count();
        $query->skip($offset);
        $query->take($limit);
        $members = $query->get();
        $data['errcode'] = 0;
        $data['data'] = $members;
        $data['_count'] = $count;
        return Response::json($data);
    }

    /**
     * 获取虚拟人物详情
     */
    public function getMember(Request $request) {
        $id = $request->id;
        if(!$id){
            return Response::json(['errcode'=>100031,'errmsg'=>'参数错误']);
        }
        $query = VirtualMember::select('virtual_member.id','virtual_member.nickname','virtual_member.avatar','virtual_member.level','member_card.card_name','virtual_member.created_time','virtual_member.updated_time');
        $query->where(['virtual_member.id'=>$id,'virtual_member.merchant_id'=>Auth::user()->merchant_id,'virtual_member.is_delete'=>1]);
        $query->leftJoin('member_card', 'member_card.id', '=', 'virtual_member.level');
        $member = $query->first();
        if(!$member){
            return Response::json(['errcode'=>100032,'errmsg'=>'记录不存在']);
        }
        $member['errcode'] = 0;
        return Response::json($member);
    }

    /**
     * 新增虚拟人物
     */
    public function postMember(Request $request){
        $params = $request->all();
        $member['is_delete'] = 1;
        $member['merchant_id'] = Auth::user()->merchant_id;
        $member['nickname'] = isset($params['nickname']) ? $params['nickname'] : '';
        $member['avatar'] = isset($params['avatar']) ? $params['avatar'] : '';
        $member['level'] = isset($params['level']) ? $params['level'] : 0;
        $result = VirtualMember::create($member);
        return $result ? Response::json(['errcode'=>0]) : Response::json(['errcode'=>100033,'errmsg'=>'创建失败']);
    }

    /**
     * 修改虚拟人物
     */
    public function putMember(Request $request){
        $params = $request->all();
        $id = $request->id;
        if(!$id){
            return Response::json(['errcode'=>100034,'errmsg'=>'参数错误']);
        }
        $member = VirtualMember::where(['id'=>$id,'merchant_id'=>Auth::user()->merchant_id,'is_delete'=>1])->first();
        if(isset($params['nickname'])){
            $member->nickname = $params['nickname'];
        }
        if(isset($params['avatar'])){
            $member->avatar = $params['avatar'];
        }
        if(isset($params['level'])){
            $member->level = $params['level'];
        }
        $result = $member->save();
        return $result ? Response::json(['errcode'=>0]) : Response::json(['errcode'=>100035,'errmsg'=>'修改失败']);
    }

    /**
     * 批量删除虚拟人物
     */
    public function batchDeleteMember(Request $request){
        $params = $request->all();
        if(!isset($params['id']) || empty($params['id'])){
            return Response::json(['errcode'=>100036,'errmsg'=>'参数错误']);
        }
        $result = VirtualMember::where(['merchant_id'=>Auth::user()->merchant_id])->whereIn('id',$params['id'])->update(['is_delete'=>-1]);
        return $result ? Response::json(['errcode'=>0]) : Response::json(['errcode'=>100036,'errmsg'=>'删除失败']);
    }
}
