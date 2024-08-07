<?php

namespace App\Http\Controllers\Weapp\Goods;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\Response;
use App\Models\OrderComment;
use App\Models\OrderCommentImg;
use App\Models\Member;
use App\Facades\Member as MMember;
use App\Models\OrderGoods;
use App\Services\GoodsService;
use App\Models\CommentVirtualMember;


class CommentController extends Controller
{
    protected $merchant_id = null;                  //商户id

    public function __construct()
    {
        $this->merchant_id = MMember::merchant_id();         //商户id

    }

    /**
     *
     *查询某个商品的所有评论
     * @param int $_GET ['id']  商品ID(必选)
     * @param string $_GET ['has_img']  1只显示有图评论,其他值为显示所有评论
     * @param int $_GET ['pagesize']  每页显示条数(可选,默认10)
     * @param int $_GET ['page']      显示页数(可选,默认1)
     *邮箱  renruiqi@dodoca.com
     *时间  2017-9-5
     */
    public function index(GoodsService $goodsService, Request $request, $id)
    {
        $goods_id = (int)$id;
        if ($goods_id <= 0) {
            return Response::json(array('errcode' => 80001, 'errmsg' => '网络繁忙，请稍后再试'));
        }
        $_search = $request->all();
        $_search['goods_id'] = $goods_id;
        $_search['merchant_id'] = $this->merchant_id;
        $hash = ($goodsService->comment_list($_search));
        return Response::json($hash);
    }

    /**
     *
     *查询某条评论
     * @param $id 评论id
     *邮箱  renruiqi@dodoca.com
     *时间  2017-9-5
     */
    public function getOne(Request $request, $id)
    {
        return;
//         $comment_info = OrderComment::query()
//             ->select('id', 'member_id', 'order_id', 'nickname', 'has_img', 'content', 'reply', 'reply_time', 'score', 'is_anonymous', 'created_time', 'props_str as props')
//             ->where('is_show', 1)
//             ->where('nopass', 0)
//             ->find($id);

//         if (count($comment_info) > 0) {
//             $comment_info = $comment_info->toArray();
//             $comment_info['props'] = explode(';', $comment_info['props']);
//             if (count($comment_info['props']) > 2) {
//                 array_pop($comment_info['props']);
//                 array_pop($comment_info['props']);
//             } else {
//                 $comment_info['props'] = ['error'];
//             }

//             //如果是有图评论则查图片表
//             if ($comment_info['has_img'] == 1) {
//                 $img_list = OrderCommentImg::query()
//                     ->where('comment_id', $id)
//                     ->where('is_delete', 1)//未删除
//                     ->lists('img');                  //保留图片地址列
//                 $v['img_list'] = count($img_list) > 0 ? $img_list : [];
//                 //如果评论不匿名,则查询用户头像,用户昵称
//                 // $member_info = Member::find($comment_info['member_id']);
//                 // if($member_info){
//                 //     $comment_info['member_avatar'] = $member_info->avatar;
//                 //     $comment_info['member_name'] = $member_info->name;
//                 // }else{
//                 //     $comment_info['is_anonymous'] =1 ;
//                 // }


//             }
//             //获取用户头像
//             if ((int)$comment_info['order_id'] === 0) {
//                 //如果为预设评论
//                 $qiniu = env('QINIU_STATIC_DOMAIN');
//                 $avatar = CommentVirtualMember::select('avatar')->where('id', '=', $comment_info['member_id'])->first();
//                 $comment_info['member_avatar'] = $avatar == null ? '' : $qiniu . '/' . $avatar->avatar;
//             } else {
//                 //订单评论
//                 $avatar = Member::select('avatar')->where('id', '=', $comment_info['member_id'])->first();
//                 $comment_info['member_avatar'] = $avatar == null ? '' : $avatar->avatar;
//             }
//             //获取产品规格
// //                $props = OrderGoods::query()
// //                        ->where('order_id',$comment_info['order_id'])
// //                        ->first();
// //                $comment_info['props'] = $props ? $props->props : '-';
//         } else {
//             $comment_info = [];
//         }

//         return Response::json(array('errcode' => 0, 'errmsg' => '请求成功', 'data' => $comment_info));
    }

}
