<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/9/5
 * Time: 15:57
 * Author: DuMing
 */
namespace App\Http\Controllers\Admin\Goods;

use App\Http\Controllers\Controller;
use App\Models\GoodsTag;
use App\Models\GoodsTagRs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;

class GoodsGroupController extends Controller
{
    protected $request;
    protected $params;
    protected $goods_tag;
    protected $goods_tag_rs;
    protected $user_id;

    public function __construct(Request $request, GoodsTag $goods_tag, GoodsTagRs $goods_tag_rs)
    {
        $this->params = $request->all();
        $this->goods_tag = $goods_tag;
        $this->goods_tag_rs = $goods_tag_rs;
        $user = Auth::user();
        $this->user_id = $user->merchant_id;
    }

    /**
     * @Author  DuMing
     * 商品分组列表
     * 参数：title     分组名称,搜索条件
     *      offset    从第几条起
     *          limit     每页多少条
     */
    public function index()
    {
        if ($this->user_id == 0) {
            $data['errcode'] = 10001;
            $data['errmsg'] = '商户未正常登录,请重新登录';
            $data['data'] = [];
            return Response::json($data);
        }
        $wheres[] = array('column' => 'merchant_id', 'value' => $this->user_id, 'operator' => '=');
        $wheres[] = array('column' => 'is_delete', 'value' => 1, 'operator' => '=');
        $title = isset($this->params['title']) ? trim($this->params['title']) : '';
        if (!empty($title)) {
            $wheres[] = array('column' => 'title', 'value' => '%' . $title . '%', 'operator' => 'like');
        }

        $column = isset($this->params['title']) ? trim($this->params['title']) : 'created_time';
        if (!in_array($column, array('id', 'merchant_id', 'title', 'created_time', 'updated_time'))) {
            $column = 'created_time';
        }
        $direct = isset($this->params['direct']) ? trim($this->params['direct']) : 'desc';
        if (!in_array($direct, array('desc', 'asc'))) {
            $direct = 'desc';
        }
        $order = array('column' => $column, 'direct' => $direct);
        $count = $this->goods_tag->get_data_count($wheres);
        $page = isset($this->params['page']) ?
            intval($this->params['page']) <= 0 ? 1 : intval($this->params['page']) : 1;
        $pagesize = isset($this->params['pagesize']) ?
            intval($this->params['pagesize']) <= 0 ? 10 : intval($this->params['pagesize']) : 10;
        $offset = ($page - 1) * $pagesize > $count ? floor($count / $pagesize) * $pagesize : ($page - 1) * $pagesize;
        $limit = $pagesize;
        $fields = 'id,merchant_id,title,created_time,updated_time';
        $data = $this->goods_tag->get_data_list($wheres, $fields, $order, $offset, $limit);
        $data['_count'] = $count;
        return Response::json($data);
    }

    /***
     * @Author  DuMing
     * 删除分组
     * 参数： id     商品分组id
     */
    public function delete()
    {
        $id = isset($this->params['id']) ? intval($this->params['id']) : 0;
        if (empty($id)) {
            $data['errcode'] = 99001;
            $data['errmsg'] = '商品分组id不能为空';
            return Response::json($data);
        }

        //查询分组是否是当前商户创建
        $goods_tag = $this->goods_tag->get_data_by_id(array(
            array('column' => 'id', 'operator' => '=', 'value' => $id),
            array('column' => 'merchant_id', 'operator' => '=', 'value' => $this->user_id),
        ));
        if (!$goods_tag) {
            $data['errcode'] = 80001;
            $data['errmsg'] = '无权删除';
            return Response::json($data);
        }

        //查询改分组下是否有商品
        $where_goods[] = array('column' => 'merchant_id', 'value' => $this->user_id, 'operator' => '=');
        $where_goods[] = array('column' => 'tag_id', 'value' => $id, 'operator' => '=');
//        $count = $this->goods_tag_rs->get_data_count($where_goods);
        $count = GoodsTagRs::where('goods_tag_rs.merchant_id', $this->user_id)
            ->where('goods_tag_rs.tag_id', $id)
            ->leftJoin('goods', 'goods_tag_rs.goods_id', '=', 'goods.id')
            ->where('goods.merchant_id', $this->user_id)
            ->where('goods.is_delete', 1)
            ->count();
        if ($count > 0) {
            $data['errcode'] = 80002;
            $data['errmsg'] = '商品分组下存在商品,禁止删除';
            return Response::json($data);
        }

        $res = $this->goods_tag->delete_data($id);
        if ($res === false) {
            $data['errcode'] = 1;
            $data['errmsg'] = '删除失败';
        } else {
            $data['errcode'] = 0;
            $data['errmsg'] = '成功删除';
        }
        return Response::json($data);
    }

    /***
     * @Author  DuMing
     * 商品分组添加
     */
    public function add()
    {
        $title = isset($this->params['title']) ? trim($this->params['title']) : '';
        if (!$title) {
            $data['errcode'] = 99001;
            $data['errmsg'] = '参数缺失';
            return Response::json($data);
        }
        $tag = $this->goods_tag->getDataByWhere(array(
            array('column' => 'merchant_id', 'value' => $this->user_id, 'operator' => '='),
            array('column' => 'title', 'value' => $title, 'operator' => '='),
            array('column' => 'is_delete', 'value' => 1, 'operator' => '='),
        ), array('id'))->toArray();
        if (!empty($tag)) {
            $data['errcode'] = 1;
            $data['errmsg'] = '该分组已经存在';
            return Response::json($data);
        }

        $insert_data['merchant_id'] = $this->user_id;
        $insert_data['title'] = $title;
        $insert_data['is_delete'] = 1;
        $res = $this->goods_tag->insert_data($insert_data);
        if ($res) {
            $data['errcode'] = 0;
            $data['errmsg'] = '添加成功';
        } else {
            $data['errcode'] = 1;
            $data['errmsg'] = '添加失败';
        }
        return Response::json($data);

    }

    /***
     * @Author  DuMing
     * 商品分组编辑
     */
    public function edit()
    {
        $title = isset($this->params['title']) ? trim($this->params['title']) : '';
        if (!$title) {
            $data['errcode'] = 99001;
            $data['errmsg'] = '参数缺失,分组名不能为空';
            return Response::json($data);
        }

        $id = isset($this->params['id']) ? trim($this->params['id']) : 0;
        $id = intval($id);
        if (!$id) {
            $data['errcode'] = 99001;
            $data['errmsg'] = '参数错误,商品分组id不能为空';
            return Response::json($data);
        }

        $tag = $this->goods_tag->getDataByWhere(array(
            array('column' => 'merchant_id', 'value' => $this->user_id, 'operator' => '='),
            array('column' => 'title', 'value' => $title, 'operator' => '='),
            array('column' => 'is_delete', 'value' => 1, 'operator' => '='),
        ), array('id'))->toArray();
        $ids = array_column($tag, 'id');
        $diff = array_diff($ids, array($id));
        if (!empty($diff)) {
            $data['errcode'] = 1;
            $data['errmsg'] = '该分组已经存在';
            return Response::json($data);
        }

        $update_data['title'] = $title;
        $update_data['updated_time'] = date('Y-m-d H:i:s');
        $wheres = array(
            array('column' => 'id', 'operator' => '=', 'value' => $id),
            array('column' => 'merchant_id', 'operator' => '=', 'value' => $this->user_id),
            array('column' => 'is_delete', 'operator' => '=', 'value' => 1),
        );

        $res = $this->goods_tag->updateByWhere($wheres, $update_data);
        if ($res === false) {
            $data['errcode'] = 1;
            $data['errmsg'] = '编辑失败';
        } else {
            $data['errcode'] = 0;
            $data['errmsg'] = '编辑成功';
        }
        return Response::json($data);
    }

    /***
     * @Author  DuMing
     * 商品分组详情
     */
    public function detail()
    {
        $id = isset($this->params['id']) ? trim($this->params['id']) : 0;
        $id = intval($id);
        if (!$id) {
            $data['errcode'] = 99001;
            $data['errmsg'] = '参数错误,商品分组id不能为空';
            $data['errcode'] = [];
            return Response::json($data);
        }
        $wheres = array(
            array('column' => 'id', 'operator' => '=', 'value' => $id),
            array('column' => 'merchant_id', 'operator' => '=', 'value' => $this->user_id),
            array('column' => 'is_delete', 'operator' => '=', 'value' => 1),
        );
        $query = $this->goods_tag->query();
        foreach ($wheres as $where) {
            $query = $query->where($where['column'], $where['operator'], $where['value']);
        }
        $data['data'] = $query->select(array('id', 'merchant_id', 'title', 'created_time', 'updated_time'))->first();
        $data['errcode'] = 0;
        $data['errmsg'] = '';
        return Response::json($data);
    }
}