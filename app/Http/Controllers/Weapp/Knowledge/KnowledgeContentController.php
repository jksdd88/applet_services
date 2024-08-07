<?php

namespace App\Http\Controllers\Weapp\Knowledge;

use App\Models\KnowledgeContent;
use App\Models\KnowledgeColumn;
use App\Models\KnowledgeContentColumnRel;
use App\Models\OrderKnowledge;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Services\KnowledgeService;
use App\Facades\Member;

class KnowledgeContentController extends Controller
{
    private $merchant_id;
    private $member_id;

    public function __construct()
    {
        $this->member_id = Member::id();//商户id
        $this->merchant_id = Member::merchant_id();//商户id
    }

    /**
     *
     *查询所有内容
     * @author renruiqi@dodoaca.com
     */
    public function index(Request $request)
    {
        $param = $request->all();
        $param['status'] = KnowledgeContent::STATUS_ONSHELVE;
        $param['weapp'] = 1;//前台、小程序端调用
        //获取商户id,获取商户id


        //根据栏目id搜索内容时 调用对应接口
        if (isset($param['column_id']) && (int)$param['column_id'] > 0) {
            $column_id = (int)$param['column_id'];
            $data = KnowledgeService::get_content_by_id($column_id, $this->merchant_id, $param);
        } else {
            $data = KnowledgeService::listsContent($param, $this->merchant_id);
        }
        if (!empty($data['lists'])) {
            foreach ($data['lists'] as &$content) {
                $content['img'] = json_decode($content['img']);
                //所属专栏
//                $content['columns'] = KnowledgeContentColumnRel::get_lists_by_content_id($content['id'], $this->merchant_id);
            }
        }
        return ['errcode' => 0, 'errmsg' => '操作成功', 'data' => $data];

    }
    /**
     *
     *查询所有内容 //装修用
     * @author renruiqi@dodoaca.com
     */
    public function get_all_design(Request $request)
    {
        $param = request()->all();
        $param['status'] = KnowledgeContent::STATUS_ONSHELVE;
        $param['weapp'] = 1;//前台、小程序端调用
        //获取商户id,获取商户id
        $merchant_id = $request->input('merchant_id',false);
        if(!$merchant_id ) return['errcode' => 99001, 'errmsg' => '请求参数非法', 'data' => []];

        //根据栏目id搜索内容时 调用对应接口
        if (isset($param['column_id']) && (int)$param['column_id'] > 0) {
            $column_id = (int)$param['column_id'];
            $data = KnowledgeService::get_content_by_id($column_id, $merchant_id, $param);
        } else {
            $data = KnowledgeService::listsContent($param, $merchant_id);
        }
        if (!empty($data['lists'])) {
            foreach ($data['lists'] as &$content) {
                $content['img'] = json_decode($content['img']);
                //所属专栏
//                $content['columns'] = KnowledgeContentColumnRel::get_lists_by_content_id($content['id'], $this->merchant_id);
            }
        }
        return ['errcode' => 0, 'errmsg' => '操作成功', 'data' => $data];

    }



    /**
     * 详情
     * @param $id
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function show($id)
    {
        $content = KnowledgeContent::get_data_by_id($id, $this->merchant_id);
        if (!empty($content)) {
            $content=$content->toArray();
            if ($content['is_delete'] !== 1) return ['errcode' => 0, 'errmsg' => '【内容已下架】', 'data' => ['is_del' => -1]];
            $content['csale'] = $content['csale'] + $content['base_csale'];
            unset($content['base_csale']);
            $content['columns'] = KnowledgeContentColumnRel::get_lists_by_content_id($content['id'], $this->merchant_id);
            if (!empty($content['columns']) && !empty($content['columns']['lists'])) {
                foreach ($content['columns']['lists'] as &$value) {
                    $value['img'] = json_decode($value['img']);
                }
            }
            $content['if_paid'] = KnowledgeService::ifPaid($id, OrderKnowledge::K_TYPE_CONTENT, $this->member_id, $this->merchant_id);
            if (!$content['if_paid']) {//未付款
                if ($content['status'] == KnowledgeContent::STATUS_UNSHELVE) return ['errcode' => 0, 'errmsg' => '内容已下架', 'data' => ['is_del' => -1]];
                if ($content['type'] == KnowledgeContent::TYPE_ARTICLE) {
                    unset($content['details'], $content['video_url']);
                } else {
                    unset($content['video_url']);
                }
            } else {
                if ($content['type'] == KnowledgeContent::TYPE_ARTICLE) {
                    unset($content['video_url']);
                }
            }

            $content['img'] = json_decode($content['img']);
        }
        return ['errcode' => 0, 'errmsg' => '操作成功', 'data' => $content];
    }

    /**
     * 根据内容id 返回所属专栏(1:N)
     * @param $id
     * @author: renruiqi@dodoca.com
     */

    public function getColumnByContentId(Request $request)
    {
        //验证内容id
        $content_id = $request->input('content_id', 0);
        if (!$content_id) return ['errcode' => 99001, 'errmsg' => '参数错误', 'data' => []];

        //判定内容是否存在
        $info = KnowledgeContent::get_data_by_id($content_id, $this->merchant_id);
        if (!$info || $info['is_delete'] !== 1) return ['errcode' => 1, 'errmsg' => '【内容已下架】'];

        $param = $request->all();
        $param['status'] = KnowledgeColumn::STATUS_ONSHELVE;//上架
        $data = KnowledgeService::get_column_by_content_id((int)$content_id, $this->merchant_id, $param);
        $data['if_paid'] = KnowledgeService::ifPaid($content_id, OrderKnowledge::K_TYPE_CONTENT, $this->member_id, $this->merchant_id);
        if (count($data['lists']) > 0) {
            foreach ($data['lists'] as &$content) {
                $content['img'] = json_decode($content['img']);
                $content['csale'] = $content['csale'] + $content['base_csale'];
                unset($content['base_csale']);
                //是否购买过专栏
                $content['if_paid'] = KnowledgeService::ifPaid($content['id'], OrderKnowledge::K_TYPE_COLUMN, $this->member_id, $this->merchant_id);
            }
        }
        return ['errcode' => 0, 'errmsg' => '操作成功', 'data' => $data];

    }

}
