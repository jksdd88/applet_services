<?php

namespace App\Http\Controllers\Admin\ServeLabel;

use App\Models\MerchantSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Config;
use App\Models\ServeLabel;
use App\Models\GoodsServeLabel;

class ServeLabelController extends Controller
{

    public function getServeLabels(Request $request)
    {
        $data = $request->all();
        $title = isset($data['title']) ? $data['title'] : '';
        $from_type = isset($data['from_type']) ? $data['from_type'] : 0;

        //先查询当前商户有木有添加或者设置服务标签内容
        $merchant_id = Auth::user()->merchant_id;
        if ($from_type == 1) {
            $merchant_setting_res = MerchantSetting::get_data_by_id($merchant_id);
            if (!empty($merchant_setting_res) && $merchant_setting_res->label_enabled != 1) {//商家设置里未开启
                return ['errcode' => 0, 'errmsg' => '商家未开启服务标签', 'data' => [], '_count' => 0];
            }
        }
//        $wheres = array(
//            array('column' => 'merchant_id', 'value' => Auth::user()->merchant_id, 'operator' => '=')
//        );
        $res = ServeLabel::where(['merchant_id' => Auth::user()->merchant_id])->get();
        if ($res->isEmpty()) {
            $config = Config::get('servelabel');
            if ($config) {
                foreach ($config as $v) {
                    $indata['merchant_id'] = $merchant_id;
                    $indata['title'] = $v['title'];
                    $indata['content'] = $v['content'];
                    $indata['logo'] = $v['logo'];
                    $indata['label_type'] = $v['label_type'];
                    $indata['is_enabled'] = $v['is_enabled'];
                    $indata['status'] = $v['status'];
                    ServeLabel::create($indata);
                }
            }
        }
        $wheres = array(
            array('column' => 'merchant_id', 'value' => $merchant_id, 'operator' => '='),
        );
        if ($title) {
            $wheres[] = array('column' => 'title', 'value' => '%' . $title . '%', 'operator' => 'like');
        }
        $offset = isset($data['offset']) ? $data['offset'] : null;
        $limit = isset($data['limit']) ? $data['limit'] : null;

        $query = ServeLabel::query();
        foreach ($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        $count = $query->count();
        if (!empty($offset)) {
            $query->skip($offset);
        }
        if (!empty($limit)) {
            $query->take($limit);
        }
        $list = $query->get();
        $result['errcode'] = $count > 0 ? 0 : 100001;
        $result['data'] = $list;
        $result['_count'] = $count;
        return Response::json($result);
    }

    public function getLabels()
    {
        $merchant_id = Auth::user()->merchant_id;
        $result = ServeLabel::select('id', 'title')->where(array('merchant_id' => $merchant_id, 'is_enabled' => 1))->get();
        if ($result->isEmpty()) {
            return Response::json(['errcode' => 100001]);
        }
        $result['errcode'] = 0;
        return Response::json($result);
    }

    public function getServeLabel($id)
    {
        $result = ServeLabel::where(['id' => $id, 'merchant_id' => Auth::user()->merchant_id])->first();
        $result['errcode'] = $result ? 0 : 100001;
        return Response::json($result);
    }

    public function postServeLabel(Request $request)
    {
        $data = $request->all();
        $merchant_id = Auth::user()->merchant_id;
        $serveData = array(
            'merchant_id' => $merchant_id,
            'title' => isset($data['title']) ? $data['title'] : '',
            'logo' => isset($data['logo']) ? $data['logo'] : '',
            'content' => isset($data['content']) ? $data['content'] : '',
            'label_type' => isset($data['label_type']) ? $data['label_type'] : 1,
            'is_enabled' => isset($data['is_enabled']) ? $data['is_enabled'] : 0,
            'status' => isset($data['status']) ? $data['status'] : 0,
        );
        $result = ServeLabel::create($serveData);
        if ($result) {
            return Response::json(array('errcode' => 0));
        } else {
            return Response::json(array('errcode' => 100001, 'errmsg' => '添加失败'));
        }
    }

    public function putServeLabel(Request $request, $id)
    {
        $data = $request->all();
        $merchant_id = Auth::user()->merchant_id;
        $servelabel = ServeLabel::where(['id' => $id, 'merchant_id' => $merchant_id])->first();
        $updatedata = array(
            'title' => isset($data['title']) ? $data['title'] : $servelabel->title,
            'logo' => isset($data['logo']) ? $data['logo'] : $servelabel->logo,
            'content' => isset($data['content']) ? $data['content'] : $servelabel->content,
            'label_type' => isset($data['label_type']) ? $data['label_type'] : $servelabel->label_type,
            'is_enabled' => isset($data['is_enabled']) ? $data['is_enabled'] : $servelabel->is_enabled,
            'status' => isset($data['status']) ? $data['status'] : $servelabel->status
        );
        $serve_id = ServeLabel::where(['id' => $id, 'merchant_id' => $merchant_id])->update($updatedata);
        if ($serve_id) {
            return Response::json(array('errcode' => 0));
        } else {
            return Response::json(array('errcode' => 100001, 'errmsg' => '修改失败'));
        }
    }

    public function putServeLabelStatus($id)
    {
        if (Input::has('is_enabled')) {
            $edit['is_enabled'] = Input::get('is_enabled');
        }
        if (Input::has('status')) {
            $edit['status'] = Input::get('status');
        }
        $result = ServeLabel::where(['id' => $id, 'merchant_id' => Auth::user()->merchant_id])->update($edit);
        if ($result) {
            return Response::json(array('errcode' => 0));
        } else {
            return Response::json(array('errcode' => 100001, 'errmsg' => '修改失败'));
        }
    }

    public function deleteServeLabel($id)
    {
        $merchant_id = Auth::user()->merchant_id;
        //检查是否有关联的服务承诺的商品
        GoodsServeLabel::where('label_id', $id)->delete();

        $result = ServeLabel::where(['id' => $id, 'merchant_id' => $merchant_id])->delete();
        if ($result) {
            return Response::json(array('errcode' => 0));
        } else {
            return Response::json(array('errcode' => 100001, 'errmsg' => '删除失败'));
        }
    }

}
