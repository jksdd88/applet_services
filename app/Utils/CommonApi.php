<?php
/**
 * 通用类库文件
 * @author zhangchangchun@dodoca.com
 */

namespace App\Utils;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use App\Models\ExceptData;
use App\Models\QueueData;
use App\Models\Wlog;

class CommonApi
{

    /**
     * 记录程序异常数据
     * @data = [
     * 'activity_id'    =>    0,    //活动id
     * 'data_type'        =>    '',    //类型
     * 'content'        =>    '',    //说明、备注
     * ];
     */
    public static function errlog($data = [])
    {
        if ($data) {
            return ExceptData::insert_data($data);
        }
        return true;
    }

    /**
     * 验证
     * @data = [
     * 'activity_id'    =>    0,    //活动id
     * 'data_type'        =>    '',    //类型
     * 'content'        =>    '',    //说明、备注
     * ];
     */
    public static function verifyJob($data = [])
    {
        if ($data) {
            $activity_id = isset($data['activity_id']) ? (int)$data['activity_id'] : 0;
            $data_type = isset($data['data_type']) ? $data['data_type'] : '';
            $content = isset($data['content']) ? $data['content'] : '';
            if ($activity_id && $data_type) {
                if (QueueData::where(['activity_id' => $activity_id, 'data_type' => $data_type])->first()) {
                    return true;
                } else {
                    QueueData::insert_data(['activity_id' => $activity_id, 'data_type' => $data_type, 'content' => $content]);
                }
            }
        }
        return false;
    }

    /**
     * 订单号
     * @param $type
     * @return array|string
     * @author: tangkang@dodoca.com
     */
    static public function getOrderNO($type)
    {
        if (empty($type)) return ['errcode' => 1, 'errmsg' => '单号生成失败，type类型不能为空'];
        if ($type > 100 && $type < 1) return ['errcode' => 1, 'errmsg' => '单号生成失败，type必须为大于0小于100的整数'];
        $type = 'B' . str_pad($type, 2, '0', STR_PAD_LEFT);
        $num = str_pad(rand(0, 99999), 5, 0, STR_PAD_LEFT);
        return ['errcode' => 0, 'errmsg' => 'ok', 'data' => $type . date('ymdHis') . $num];
    }
	
	/**
     * 记录日志数据
     * @data = [
     * 'custom'    		=>    	'',    	//标识字段数据
     * 'merchant_id'    =>    	'',    	//商户id
     * 'member_id'      =>    	'',    	//会员id
	 * 'content'		=>		'',		//日志内容
     * ];
     */
    public static function wlog($data = [])
    {
        return Wlog::insert_data($data);
    }
	
}
