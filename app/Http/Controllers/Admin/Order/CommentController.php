<?php
/**
 * Created by PhpStorm.
 * User: jiangnan
 * Date: 2017/9/7
 * Time: 14:51
 */

namespace App\Http\Controllers\Admin\Order;

use App\Http\Controllers\Controller;
use App\Models\ApptStaff;
use App\Models\Comment;
use App\Models\Goods;
use App\Models\GoodsAppt;
use App\Models\GoodsProp;
use App\Models\Prop;
use App\Models\OrderGoods;
use App\Models\OrderInfo;
use App\Models\CommentImg;
use App\Models\Member;
use App\Models\CommentVirtualMember;
use App\Models\MerchantSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;

class CommentController extends Controller
{
    private $model;
    private $merchant_id;

    function __construct(Comment $comment)
    {
        $this->model = $comment;
        $this->merchant_id = Auth::user()->merchant_id;
        //$this->merchant_id = 6;
    }

    //订单商品评论列表
    function getCommentList(Request $request)
    {
        $params = $request->all();
        $offset = isset($params['offset']) ? $params['offset'] : 1;
        $limit = isset($params['limit']) ? $params['limit'] : 10;
        $starcounts = isset($params['starcounts']) ? trim($params['starcounts']) : "全部";
        $query = Comment::where('merchant_id', $this->merchant_id);
        //订单号
        if (isset($params['order_no']) && $params['order_no']) {
            $orderId = OrderInfo::where('order_sn', htmlspecialchars($params['order_no']))->pluck('id');
            $query->where('order_id', $orderId);
        }

        if (isset($params['ordersn']) && $params['ordersn']) {
            $orderId = Order::where('order_sn', htmlspecialchars($params['ordersn']))->pluck('id');
            $query->where('order_id', $orderId);
        }

        //商品名称
        if (isset($params['goodsname']) && $params['goodsname']) {
            $goodsIds = Goods::where('title', 'like', '%' . htmlspecialchars($params['goodsname']) . '%')->lists('id');
            $query->whereIn('goods_id', $goodsIds);
        }
        if (isset($params['goods_id']) && $params['goods_id']) {
            $query->where('goods_id', intval($params['goods_id']));
        }
        //会员帐号
        if (isset($params['member_id']) && $params['member_id']) {
            $query->where('member_id', intval($params['member_id']) - MEMBER_CONST);
        }
        //星级评分
        if ($starcounts && $starcounts != '全部') {
            $query->where('score', '=', $starcounts);
        }
        $count = $query->count();
        $data = array();
        if ($count > 0) {
            $limit_to = ($offset - 1) * $limit;
            $comment_list = $query->skip($limit_to)
                ->take($limit)
                ->orderBy('id', 'DESC')
                ->get();
            foreach ($comment_list as $key => $value) {
                $comment_list[$key]['order_sn'] = OrderInfo::where('id', $value['order_id'])->value('order_sn');
                //如果有订单子表关联id,查询商品信息
                if ($value['order_goods_id']) {
                    $goods_info = OrderGoods::select('goods_id', 'quantity', 'price', 'goods_name', 'goods_img')->where('id', $value['order_goods_id'])->first();

                    $is_sku = Goods::where('id', $goods_info->goods_id)->value('is_sku');

                } else {
                    //没有，则查询商品主表信息
                    $goods_info = Goods::select('price', 'title AS goods_name', 'img AS goods_img', 'is_sku')->where('id', $value['goods_id'])->first();
                    $goods_info['quantity'] = 1;
                    $is_sku = $goods_info->is_sku;
                }
                //商品名称字数限制为17个。超出17个用....代替
                if (mb_strlen($goods_info['goods_name']) > 17) {
                    $goods_info['goods_name'] = mb_substr($goods_info['goods_name'], 0, 17, 'utf-8') . '...';
                }
                //用户昵称字数限制为17个。超出17个用....代替
                if (mb_strlen($value['nickname']) > 7) {
                    $value['nickname'] = mb_substr($value['nickname'], 0, 7, 'utf-8') . '...';
                }
                $goods_info['goods_img'] = env('QINIU_DOMAIN') . $goods_info['goods_img'];
                if (isset($is_sku)) {
                    $goods_info['is_sku'] = $is_sku;
                }
                $comment_list[$key]['goods_info'] = $goods_info;
                //获取评论图片
                $imgs = CommentImg::select('img')->where('comment_id', '=', $value['id'])->get();
                foreach ($imgs as &$v) {
                    $v['img'] = env('QINIU_DOMAIN') . $v['img'];
                }
                //获取用户头像
                if ($value['order_id'] == 0) {
                    //如果为预设评论
                    $avatar = CommentVirtualMember::select('avatar')->where('id', '=', $value['member_id'])->get();
                } else {
                    //订单评论
                    $avatar = Member::select('avatar')->where('id', '=', $value['member_id'])->get();
                }

                $comment_list[$key]['img'] = $imgs;
                $avatar_img = '';
                foreach ($avatar as $val) {
                    $avatar_img = $val['avatar'];
                }
                $comment_list[$key]['avatar'] = $avatar_img;
                $value['member_id'] = $value['member_id'] + MEMBER_CONST;

            }
            $data = $comment_list;
        }

        $res = array('errcode' => 0, 'errmsg' => '获取数据成功', 'data' => array('_count' => $count, 'list' => $data));
        return Response::json($res, 200);
    }

    //预设评论商品规格信息
    function getGoodsProps(Request $request)
    {
        $params = $request->all();
        $goodsId = isset($params['id']) ? intval($params['id']) : 1;
        $goodsInfo = Goods::get_data_by_id($goodsId, $this->merchant_id);
        if ($goodsInfo['is_sku'] != 0) {//多规格查规格
            //查询商品规格信息，关联商品规格表
            $props = Prop::where('prop.is_delete', 1)
                ->where('prop.prop_type', 1)
                ->whereIn('prop.merchant_id', [$this->merchant_id, 0])
                ->join('goods_prop', 'prop.id', '=', 'goods_prop.prop_id')
                ->where('goods_prop.goods_id', $goodsId)
                ->where('goods_prop.is_delete', 1)
                ->groupBy('prop.id')
                ->get([
                    'prop.id',
                    'prop.goods_cat_id',
                    'prop.prop_type',
                    'prop.prop_value_type',
                    'prop.title',
                    'goods_prop.goods_id AS pivot_goods_id',
                    'goods_prop.prop_id AS pivot_prop_id'
                ]);
            $goodsInfo['mode'] = 0;
            if ($goodsInfo['is_sku'] == 2) {
                $goods_appt = GoodsAppt::get_data_by_goods_id($goodsId, $this->merchant_id);
                if (!empty($goods_appt)) {
                    $goodsInfo['mode'] = $goods_appt->mode;
                }
            }
            $goodsProps = [];
            $prop_store_id = Prop::getPropIdByName('store', $goodsInfo['goods_cat_id']);
            $prop_staff_id = Prop::getPropIdByName('staff', $goodsInfo['goods_cat_id']);
            foreach ($props as $_prop) {
                if ($_prop['prop_type'] == 1) {
                    if (!isset($goodsProps[$_prop['id']])) {
                        $propItem = [];
                        $goodsProp = GoodsProp::where('goods_id', $goodsId)
                            ->where('prop_id', $_prop['id'])
                            ->where('is_delete', 1)
                            ->distinct()
                            ->get(['prop_vid', 'prop_value', 'prop_id'])->toArray();
                        $propItem['id'] = $_prop['id'];
                        $propItem['name'] = $_prop['title'];
                        $propItem['listorder'] = $_prop['listorder'];

                        if ($_prop['id'] == $prop_store_id) {
                            $propItem['prop_value_type'] = 1;
                        } elseif ($_prop['id'] == $prop_staff_id) {
                            $propItem['prop_value_type'] = 2;
                        } else {
                            $propItem['prop_value_type'] = 0;
                        }

                        $propItem['props'] = $goodsProp;
                        $goodsProps[$_prop['id']] = $propItem;
                    }
                }
            }
            if (!empty($goodsInfo['goods_cat_id']) && !empty($goodsInfo['mode'])) {//预约
                if (!empty($goodsProps[$prop_staff_id]) && !empty($goodsProps[$prop_store_id])) {
                    $tmp_prop_staffs = $goodsProps[$prop_staff_id]['props'];
                    foreach ($tmp_prop_staffs as $tmp_prop_staff) {
                        $appt_staff_res = ApptStaff::get_data_by_id($tmp_prop_staff['prop_vid'], $this->merchant_id);
                        if (empty($appt_staff_res)) continue;
                        if (empty($goodsProps[$prop_store_id]['props'])) continue;
                        foreach ($goodsProps[$prop_store_id]['props'] as &$tmp_prop_store) {
                            if (!empty($appt_staff_res) && ($appt_staff_res->store_id == $tmp_prop_store['prop_vid'])) {
                                $tmp_prop_store['prop_value_type'] = 1;//门店
                                $appt_staff_res->prop_value_type = 2;//服务人员
                                $tmp_prop_store['staff_values'][] = $appt_staff_res;
                            }
                        }
                    }
                    $goodsProps[$prop_staff_id]['props'] = $goodsProps[$prop_store_id]['props'];
                }
            }

            $goodsInfo['props'] = $goodsProps;
        }
        if ($goodsInfo) {
            $res = array('errcode' => 0, 'errmsg' => '获取数据成功', 'data' => $goodsInfo);
        } else {
            $res = array('errcode' => -1, 'errmsg' => '获取数据失败');
        }

        return Response::json($res, 200);
    }

    /*
     * 预设评论
     * */
    public function setPreinstallComment(Request $request)
    {
        $params = $request->all();
        $nicknameid = isset($params['nick_id']) && $params['nick_id'] ? $params['nick_id'] : '';//会员id
        $goodsid = isset($params['id']) && $params['id'] ? intval($params['id']) : '';//商品id
        $content = isset($params['content']) && $params['content'] ? $params['content'] : '';//预设评论内容
        $score = isset($params['score']) && $params['score'] ? intval($params['score']) : '';//评论星级
        $created_at = isset($params['created_at']) && $params['created_at'] ? $params['created_at'] : '';//评论时间
        $images = isset($params['images']) && $params['images'] ? $params['images'] : '';//评论图片
        $add = isset($params['add']) && $params['add'] ? $params['add'] : '';//是否为添加
        $props = isset($params['props']) && (count($params['props']) > 0) ? $params['props'] : '';//商品規格
        //查询虚拟人物信息
        $query = CommentVirtualMember::select('virtual_member.id', 'virtual_member.nickname', 'virtual_member.avatar',
            'virtual_member.level');
        $query->where('virtual_member.is_delete', '=', 1);
        $query->where('virtual_member.merchant_id', '=', $this->merchant_id);
        $result['virtual'] = $query->get();
        if (!$result['virtual']) {
            return Response::json(array('errcode' => 130001, 'errmsg' => '当前没有虚拟人物'));
        }
        if (!$goodsid) {
            return Response::json(array('errcode' => 130002, 'errmsg' => '缺少必要参数商品id'));
        }
        if ($add) {
            //如果为添加评论，查询商户评论设置信息
            $isOpen = MerchantSetting::select('is_comment_open', 'comment_type')->where(['merchant_id' => $this->merchant_id])->first();

            if ($isOpen['is_comment_open'] == '1' && $isOpen['comment_type'] == '1') {
                $is_show = '1'; //商品评论开启
                $check = '0';//评论后直接显示
            } elseif ($isOpen['is_comment_open'] == '1' && $isOpen['comment_type'] == '0') {
                $is_show = '1';//商品评论开启
                $check = '1';//审核后显示
            } else {
                $is_show = '0';//商品评论关闭
                $check = '1';//审核后显示
            }
            $query = CommentVirtualMember::select('nickname', 'avatar');
            $query->where('merchant_id', '=', $this->merchant_id);
            $query->where('id', '=', $nicknameid);
            $query->where('is_delete', '=', 1);
            $virtual = $query->first();
            //提交参数不能为空---评论内容、会员id、评论时间时间
            if (!$content) {
                return Response::json(array('errcode' => 130003, 'errmsg' => '评论内容不能为空!'));
            }
            if (!$score) {
                return Response::json(array('errcode' => 130003, 'errmsg' => '评分不能为空!'));
            }
            if (!$nicknameid) {
                return Response::json(array('errcode' => 130003, 'errmsg' => '未选择虚拟人物!'));
            }
            if (!$created_at) {
                return Response::json(array('errcode' => 130003, 'errmsg' => '评论时间不能为空!'));
            }
            if (mb_strlen($content, 'UTF-8') > 200) {
                return Response::json(array('errcode' => 130004, 'errmsg' => '评论文字最多200个字'));
            }

            if (intval($score) < 1 && intval($score) > 5) {
                return Response::json(array('errcode' => 130005, 'errmsg' => '恶意评分'));
            }

            $data = array(
                'merchant_id' => $this->merchant_id,
                'order_id' => '',//预设评论order_id为空,根据order_id是否为空来判断是用户评论还是预设评论
                'goods_id' => $goodsid,
                'member_id' => $nicknameid,
                'nickname' => $virtual['nickname'],
                'content' => $content,
                'is_show' => $is_show,
                'score' => $score,
                'is_anonymous' => '1',
                'nopass' => $check,
                'created_time' => $created_at,
                'props_str' => $props
            );

            $comment = Comment::create($data);
            if (is_array($images) && !empty($images) && $comment['id']) {
                //图片数组
                foreach ($images as $v) {
                    $comment_Img = array(
                        'comment_id' => $comment['id'],
                        'merchant_id' => $this->merchant_id,
                        'goods_id' => $goodsid,
                        'img' => $v,
                        'is_delete' => '1',
                        'created_time' => $created_at
                    );
                    //var_dump($comment_Img);exit();
                    $img = CommentImg::create($comment_Img);
                }
                //如果有评论图片
                Comment::where('id', $comment['id'])->update(['has_img' => 1]);
                $comment['data'] = $img;
            }
            if ($comment) {
                return Response::json(array('errcode' => 0, 'errmsg' => '评论发表成功!'));
            } else {
                return Response::json(array('errcode' => -1, 'errmsg' => '评论发表失败!'));
            }

        }

        return Response::json(array('errcode' => 0, 'errmsg' => '获取数据成功', 'data' => $result['virtual']));

    }


    /*
     * 编辑预设评论
     * */
    public function putPreinstallComment(Request $request)
    {
        $params = $request->all();
        $id = isset($params['id']) && $params['id'] ? intval($params['id']) : '';//预设评论id
        $nicknameid = isset($params['nick_id']) && $params['nick_id'] ? $params['nick_id'] : '';//会员id
        $goodsid = isset($params['goodsid']) && $params['goodsid'] ? intval($params['goodsid']) : '';//商品id
        $content = isset($params['content']) && $params['content'] ? $params['content'] : '';//评论内容
        $score = isset($params['score']) && $params['score'] ? intval($params['score']) : '';//评论星级
        $created_at = isset($params['created_at']) && $params['created_at'] ? $params['created_at'] : '';//评论时间
        $images = isset($params['images']) && $params['images'] ? $params['images'] : '';//评论图片
        $edit = isset($params['edit']) && $params['edit'] ? $params['edit'] : '';//是否为编辑状态
        //获取该评论信息
        $query = Comment::select('id', 'nickname', 'content', 'score', 'member_id', 'created_time');
        $query->where(['merchant_id' => $this->merchant_id, 'id' => $id, 'goods_id' => $goodsid, 'order_id' => 0]);
        $result['data'] = $query->first();
        //获取该评论的会员信息
        $query = CommentVirtualMember::select('id', 'nickname', 'avatar', 'level');
        $query->where(['merchant_id' => $this->merchant_id, 'id' => $result['data']['member_id']]);
        $result['data']['member'] = $query->first();
        //评论图片
        $imgs = CommentImg::select('img')->where('comment_id', '=', $id)->get();
		$result['data']['img'] = [];
		if($imgs) {
			$imgsList = [];
			foreach($imgs as $key => $info) {
				$imgsList[] = $info['img'];
			}
			$result['data']['img'] = $imgsList;
		}
        if (!$goodsid) {
            return Response::json(array('errcode' => 130001, 'errmsg' => '缺少必要参数商品id'));
        }
        if ($edit) {
            $isOpen = MerchantSetting::select('is_comment_open', 'comment_type')->where(['merchant_id' => $this->merchant_id])->first();
            if ($isOpen['is_comment_open'] == '1' && $isOpen['comment_type'] == '1') {
                $is_show = '1'; //商品评论开启
                $check = '0';//评论后直接显示
            } elseif ($isOpen['is_comment_open'] == '1' && $isOpen['comment_type'] == '0') {
                $is_show = '1';//商品评论开启
                $check = '1';//审核后显示
            } else {
                $is_show = '0';//商品评论关闭
                $check = '1';//评论后直接显示
            }
            $query = CommentVirtualMember::select('nickname', 'avatar');
            $query->where('merchant_id', '=', $this->merchant_id);
            $query->where('id', '=', $nicknameid);
            $virtual = $query->first();
            if (!$content || !$score || !$nicknameid || !$created_at) {
                return Response::json(array('errcode' => 130003, 'errmsg' => '缺少必要参数!'));
            }
            if (mb_strlen($content, 'UTF-8') > 200) {
                return Response::json(array('errcode' => 130004, 'errmsg' => '评论文字最多200个字'));
            }

            if (intval($score) < 1 && intval($score) > 5) {
                return Response::json(array('errcode' => 130005, 'errmsg' => '恶意评分'));
            }
            $wheres = array(
                'merchant_id' => $this->merchant_id,
                'id' => $id,
                'goods_id' => $goodsid
            );
            $data = array(
                'merchant_id' => $this->merchant_id,
                'order_id' => '0',//预设评论order_id为空,根据order_id是否为空来判断是用户评论还是预设评论
                'goods_id' => $goodsid,
                'member_id' => $nicknameid,
                'nickname' => $virtual['nickname'],
                'content' => $content,
                'is_show' => $is_show,
                'score' => $score,
                'is_anonymous' => '1',
                'nopass' => $check,
                'created_time' => $created_at

            );
            //var_dump($data);exit();
            $comment = $this->model->update_data($id, $this->merchant_id, $data);
            if (is_array($images) && !empty($images) && $id) {

                CommentImg::where(['merchant_id' => $this->merchant_id, 'comment_id' => $id, 'goods_id' => $goodsid])->delete();

                foreach ($images as $v) {
                    $comment_Img = array(
                        'merchant_id' => $this->merchant_id,
                        'comment_id' => $id,
                        'goods_id' => $goodsid,
                        'img' => $v,
                        'is_delete' => 1,
                        'created_time' => $created_at
                    );
                    $img = CommentImg::create($comment_Img);
                }
                Comment::where(['merchant_id' => $this->merchant_id, 'id' => $id, 'goods_id' => $goodsid])->update(['has_img' => 1]);
            }
            return Response::json(array('errcode' => 0, 'errmsg' => '评论修改成功!'));
        }
        return Response::json(array('errcode' => 0, 'errmsg' => '获取数据成功', 'data' => $result['data']));
    }

    /**
     *
     *获取商家回复
     */
    public function getSellerReply(Request $request)
    {
        $params = $request->all();
        $id = isset($params['id']) && $params['id'] ? intval($params['id']) : '';//评论id
        $reply = isset($params['reply']) && $params['reply'] ? $params['reply'] : '';//评论回复内容
        $type = isset($params['type']) && $params['type'] ? $params['type'] : ''; //添加回复
        //$amend = isset($params['amend']) && $params['amend'] ? $params['amend'] : '';//修改回复

        $result['data'] = Comment::where(array('merchant_id' => $this->merchant_id, 'id' => $id))->pluck('reply');
        if ($type || $type == 'add' || $type == 'amend') {
            if (!$reply) {
                return Response::json(array('errcode' => 130006, 'errmsg' => '回复内容不能为空!'));
            }
            if (mb_strlen($reply) > 200) {
                return Response::json(array('errcode' => 130007, 'errmsg' => '回复内容最多200个字'));
            }
            $wheres = array(
                'merchant_id' => $this->merchant_id,
                'id' => $id,
            );
            $data = array(
                'reply' => $reply,
                'reply_time' => date('Y-m-d H:i:s', time())
            );
            $comment['data'] = Comment::where($wheres)->update($data);
            if (!$comment['data']) {
                return Response::json(array('errcode' => 130008, 'errmsg' => '回复评论失败'));
            }
            return Response::json(array('errcode' => 0, 'errmsg' => '回复评论成功'));
        }
        if (!$result) {
            $result['data']['reply'] = '';
            $result['data']['id'] = $id;
            return Response::json(array('errcode' => 0, 'errmsg' => '获取数据成功', 'data' => $result['data']));
        }

        $result['id'] = $id;
        return Response::json(array('errcode' => 0, 'errmsg' => '获取数据成功', 'data' => $result['data']));

    }

    /**
     *  订单商品评论开启&&关闭
     *
     */
    public function putComment(Request $request)
    {
        $params = $request->all();
        $res = Comment::select('is_show')
            ->where('merchant_id', $this->merchant_id, '=')
            ->where('id', $params['id'], '=')
            ->first();
        if (!$res) {
            return Response::json(array('errcode' => 1300010, 'errmsg' => '网络繁忙，请稍后再试'));
        }
        //判断参数
        switch ($params['isshow']) {
            //如果等于open--开启
            case '1':
                $status = 1;
                $nopass = 0;
                break;
            //如果等于close--关闭
            case '0':
                $status = 0;
                $nopass = 1;
                break;
            //默认为开启状态
            default:
                $status = 1;
                $nopass = 0;
                break;
        }
        $wheres = array(
            'merchant_id' => $this->merchant_id,
            'id' => $params['id'],
        );
        $data = array('is_show' => $status, 'nopass' => $nopass);
        $result['data'] = $this->model->update_data($params['id'], $this->merchant_id, $data);
        if (!$result['data']) {
            return Response::json(array('errcode' => -1, 'errmsg' => '设置失败'));
        }
        return Response::json(array('errcode' => 0, 'errmsg' => '设置成功'));

    }

    // 商户评论是否开启
    public function getComment()
    {
        $result = MerchantSetting::get_data_by_id($this->merchant_id);
        if (!$result) {
            $info = MerchantSetting::create(['is_comment_open' => 1, 'comment_type' => 1, 'merchant_id' => $this->merchant_id]);
            if (!$info) {
                return Response::json(array('errcode' => -1, 'errmsg' => '添加商户评价设置失败'));
            }
            $result['data'] = $info;
        }

        return Response::json(array('errcode' => 0, 'errmsg' => '获取数据成功', 'data' => $result));
    }

    // 开启 && 关闭商户评论设置
    function setComment(Request $request)
    {
        $params = $request->all();
        $data = [
            'is_comment_open' => 0,
            'comment_type' => 1,
        ];
        $is_open = isset($params['is_open']) ? $params['is_open'] : 'close';
        $type = isset($params['type']) ? $params['type'] : 'nopass';//评论设置 0:审核后显示评论 1:评价后直接显示评论
        if ($is_open == "open") {
            $data['is_comment_open'] = 1;
        }
        if ($type == "nopass") {
            $data['comment_type'] = 0;
        }
        $res = MerchantSetting::get_data_by_id($this->merchant_id);
        if (!$res) {
            return Response::json(array('errcode' => 130010, 'errmsg' => '获取数据失败'));
        }

        MerchantSetting::update_data($this->merchant_id, $data);
        return Response::json(array('errcode' => 0, 'errmsg' => '设置成功'));
    }
}