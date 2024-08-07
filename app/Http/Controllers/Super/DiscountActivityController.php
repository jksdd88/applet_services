<?php

namespace App\Http\Controllers\Super;

use App\Models\DiscountActivity;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

//满就减管理
class DiscountActivityController extends Controller
{

    function __construct(DiscountActivity $discountActivity, Request $request)
    {
        $this->params = $request->all();
        $this->model = $discountActivity;
    }

    /**
     *  满就减活动列表
     */
    function getActivitys()
    {
        $params = $this->params;
        $offset = isset($params['offset']) ? $params['offset'] : 0;
        $limit = isset($params['limit']) ? $params['limit'] : 10;
        $title = isset($params['title']) ? trim($params['title']) : '';
        $query = $this->model->where(['is_delete' => 1]);
        if ($title) {
            $query->where('title', 'like', '%' . $title . '%');
        }

        $count = $query->count();
        $info = $query->orderBy('created_time', 'DESC')
            ->skip($offset)
            ->take($limit)
            ->get();
        foreach ($info as $key => $value) {
            switch ($value['status']) {
                case '0':
                    $info[$key]['status'] = '未生效';
                    break;
                case '1':
                    $info[$key]['status'] = '已生效';
                    break;
                case '2':
                    $info[$key]['status'] = '已过期';
                    break;
                default:
                    break;
            }
        }
        return Response::json(['errcode' => 0, '_count' => $count, 'data' => $info]);
    }

}
