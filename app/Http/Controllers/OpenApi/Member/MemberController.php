<?php

namespace App\Http\Controllers\OpenApi\Member;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Member;
use Auth;

class MemberController extends Controller
{
    /**
     * 会员列表
     */
    public function getList(Request $request)
    {
        $user        = $request->user();
        $merchant_id = $user['merchant_id'];
        $page        = $request->has('page') ? $request->page : 1;
        $pagesize    = $request->has('pagesize') ? $request->pagesize : 10;
        $offset      = ($page - 1) * $pagesize;
        $member_id   = $request->member_id;
        $sort        = $request->has('sort') ? $request->sort : 'desc';

        $query = Member::query();
        $query->where('merchant_id', $merchant_id);
        if(!empty($member_id)){
            $query->where('id', $member_id - MEMBER_CONST);
        }
        $count = $query->count();
        $data  = $query->skip($offset)->take($pagesize)->orderBy('id', $sort)->get();

        $list = [];
        foreach($data as $row){
            $list[] = [
                'member_id'       => $row->id + MEMBER_CONST,
                'total_amount'    => $row->total_amount,
                'purchased_count' => $row->purchased_count,
                'credit'          => $row->credit,
                'latest_buy_time' => $row->latest_buy_time,
                'name'            => $row->name,
                'avatar'          => $row->avatar,
                'mobile'          => $row->mobile,
                'balance'         => $row->balance,
                'created_time'    => $row->created_time
            ];
        }

        return ['errcode' => 0, 'errmsg' => '请求成功', 'count' => $count, 'data' => $list];
    }

    /**
     * 会员详情
     */
    public function detail(Request $request, $member_id)
    {
        $user        = $request->user();
        $merchant_id = $user['merchant_id'];

        $info = Member::get_data_by_id($member_id - MEMBER_CONST, $merchant_id);

        if(!$info){
            return ['errcode' => 10004, 'errmsg' => '会员不存在'];
        }

        $data = [
            'member_id'       => $info->id + MEMBER_CONST,
            'total_amount'    => $info->total_amount,
            'purchased_count' => $info->purchased_count,
            'credit'          => $info->credit,
            'latest_buy_time' => $info->latest_buy_time,
            'name'            => $info->name,
            'avatar'          => $info->avatar,
            'mobile'          => $info->mobile,
            'balance'         => $info->balance,
            'created_time'    => $info->created_time
        ];

        return ['errcode' => 0, 'errmsg' => '请求成功', 'data' => $data];
    }
}