<?php
/**
 * Created by PhpStorm.
 * User: tang
 * Date: 2018-02-05
 * Time: 19:20
 */

namespace App\Models;

use App\Services\MemberService;
use Illuminate\Database\Eloquent\Model;

class MemberBalanceDetail extends Model
{

    protected $table = 'member_balance_detail';
    protected $guarded = ['id'];
    /**
     * The name of the "created at" column.
     *
     * @var string
     */
    const CREATED_AT = 'created_time';

    /**
     * The name of the "updated at" column.
     *
     * @var string
     */
    const UPDATED_AT = 'updated_time';

    /**
     * demo 查询一条记录
     * @return array
     */
    static function get_data_by_id($id, $merchant_id)
    {
        if (!$id || !is_numeric($id)) return;
        if (!$merchant_id || !is_numeric($merchant_id)) return;
        $data = self::query()->where('id', '=', $id)
            ->where('merchant_id', $merchant_id)
            ->first();
        return $data;
    }

    static function insert_data($data)
    {
        $data['is_delete'] = 1;
        return self::create($data);
    }

    /**
     * demo 修改一条记录(仅后台发布修改商品调用，会清redis)
     * @return int|修改成功条数
     */
    static function update_data($id, $merchant_id, $data)
    {
        if (!$id || !is_numeric($id)) return;
        if (!$merchant_id || !is_numeric($merchant_id)) return;
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where('id', '=', $id)->where('merchant_id', '=', $merchant_id)->update($data);
    }

    /**
     * 提现、佣金变动记录列表
     * @param $param 详见表结构，分页参数page,pagesize，日期范围created_time=['2018-02-12','2018-03-12']
     * @param $merchant_id
     * @return mixed
     * @author: tangkang@dodoca.com
     */
    static function get_lists($param, $merchant_id)
    {
        $query = self::query();
        $query->whereMerchantId($merchant_id);
        if (!empty($param['member_id'])) $query->whereMemberId($param['member_id']);
        if (!empty($param['wxinfo_id'])) $query->whereWxinfoId($param['wxinfo_id']);
        if (!empty($param['status'])) $query->whereStatus($param['status']);
        if (!empty($param['account_number'])) $query->whereAccountNumber($param['account_number']);
        if (!empty($param['alipay'])) $query->whereAlipay($param['alipay']);
        if (!empty($param['type'])) {
            if (mb_strlen($param['type']) > 1) {
                $param['type'] = explode(',', $param['type']);
                if (!array_diff($param['type'], [MemberService::BALANCE_WEIXIN, MemberService::BALANCE_ALIPAY, MemberService::BALANCE_BANK])) {
                    $query->whereIn('type', $param['type']);
                }
            } else {
                $query->whereType($param['type']);
            }
        } else {
            $query->whereIn('type', [MemberService::BALANCE_WEIXIN, MemberService::BALANCE_ALIPAY, MemberService::BALANCE_BANK, MemberService::BALANCE_COMMISSION]);
        }
        if (!empty($param['created_time']) && is_array($param['created_time'])) {
            sort($param['created_time']);
            $query->whereDate('created_time', '>=', $param['created_time'][0]);
            $query->whereDate('created_time', '<=', $param['created_time'][1]);
        }
        $query->where('is_delete', 1);
        $param['page'] = isset($param['page']) ? $param['page'] : 1;
        $param['pagesize'] = isset($param['pagesize']) ? $param['pagesize'] : 10;
        $res['_count'] = $query->count();
        $fields = [
            'merchant_id',
            'member_id',
            'wxinfo_id',
            'order_no',
            'amount',
            'status',
            'type',
            'remark',
            'created_time',
            'handle_time',
        ];
        $res['lists'] = $query->forPage($param['page'], $param['pagesize'])->orderBy('created_time','desc')->get($fields);
        //其它信息调用处处理
//        if(!empty($res['lists'])){
//            foreach ($res['lists'] as $list){
//
//            }
//        }
        return $res;
    }
}
