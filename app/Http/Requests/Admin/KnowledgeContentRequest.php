<?php
/**
 * Created by PhpStorm.
 * User: tang
 * Date: 2018-04-09
 * Time: 11:25
 */

namespace App\Http\Requests\Admin;

use App\Http\Requests\Request;
use App\Models\KnowledgeContent;

class KnowledgeContentRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {

        $rule = [
            'name' => 'required | max:50',
            'summary' => 'max:100',
            'img' => 'required',
            'details' => 'required',
            'price' => 'required',
            'type' => 'required|in:1,2,3',
            'status' => 'required|in:1,2',
            'base_csale' => 'integer|min:0',
        ];
        if (($type = $this->get('type', 0)) == KnowledgeContent::TYPE_VIDEO || $type == KnowledgeContent::TYPE_AUDIO) {
            $rule['video_url'] = 'required';
        }
        return $rule;
    }

    public function messages()
    {
        $type_msg = '图文';
        if (($type = $this->get('type', 0)) == KnowledgeContent::TYPE_VIDEO) {
            $type_msg = '视频';
        } elseif ($type == KnowledgeContent::TYPE_AUDIO) {
            $type_msg = '音频';
        }
        $msg = [
            'name.required' => '请输入' . $type_msg . '名称',
            'name.max' => $type_msg . '名称最多50个字符',
            'summary.max' => $type_msg . '简介最多100个字符',
            'img.required' => '请上传' . $type_msg . '封面',
            'details.required' => '请输入' . $type_msg . '详情',
            'price.required' => '请输入售卖价格',
            'type.required' => '请选择知识内容类型',
            'type.in' => '知识内容类型错误',
            'status.required' => '请选择知识内容上下架状态',
            'status.in' => '知识内容上下架状态错误',
            'video_url.required' => '请输入完整' . $type_msg . '路径',
            'base_csale.integer' => '基础销量必须为整数',
            'base_csale.min' => '基础销量不能小于0',
        ];
        return $msg;
    }

    public function validate_param($param)
    {
        $img_arr = $param['img'];
        if (count($img_arr) > 3) {
            return ['errcode' => 1, 'errmsg' => '最多可上传3张封面'];
        }
        if ($param['price'] < 0) {
            return ['errcode' => 1, 'errmsg' => '价格不能小于0'];
        }
        if (!preg_match('/^[0-9]+(.[0-9]{1,2})?$/', $param['price'])) {
            return ['errcode' => 1, 'errmsg' => '价格格式错误，最多两位小数'];
        }
        $param['price'] = number_format($param['price'], 2, '.', '');
        $param['img'] = json_encode($param['img']);
        return ['errcode' => 0, 'errmsg' => '', 'data' => $param];

    }
}
