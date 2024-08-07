<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/9/5
 * Time: 11:15
 * Author: DuMing
 * 商品分类
 */
namespace App\Http\Controllers\Admin\Goods;

use App\Models\GoodsCat;
use App\Services\UserPrivService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use App\Models\Merchant;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class GoodsCatController extends Controller
{
    protected $params;//参数
    protected $goods_cat;//商品分类service
    protected $version;//版本信息

    public function __construct(Request $request, GoodsCat $goods_cat, Merchant $merchant)
    {
        $this->params = $request->all();
        $this->goods_cat = $goods_cat;
        $user = Auth::user();
        $this->user_id = $user->merchant_id;
        $this->version = 1;
        $result = $merchant->get_data_by_id($this->user_id);
        if (!empty($result) && !empty($result['version_id'])) {
            $this->version = $result['version_id'];
        }
    }

    /**
     * @Author  DuMing
     * 获取商品分类
     */
    public function getGoodsCat()
    {
        $data = $this->params;
        $parent_id = isset($data['parent_id']) ? intval($data['parent_id']) : 0;
        $type = isset($data['type']) ? $data['type'] : 0;
        $arr_cat_type = explode(',', $type);
        
        //dd($type);
        //是否有预约商品的权限
        $appointservice_priv = UserPrivService::getUserPrivs();

        if ( in_array(1, $arr_cat_type) && !in_array('trade_apptmanage', $appointservice_priv)) {
            return ['errcode' => 1, 'errmsg' => '无创建预约商品权限'];
        }
        //dd($arr_cat_type);
        //dd($appointservice_priv);
        $version = config('version');
        if ( in_array(2, $arr_cat_type) ) {
            foreach($arr_cat_type as $key=>$val){
                if($val==2){
                    if( !isset($version[$this->version]['virtual_goods']) || empty($version[$this->version]['virtual_goods']) ){
                        unset($arr_cat_type[$key]);
                    }
                }
            }
        }
        //dd($arr_cat_type);
        //dd($this->version);
        if ($this->version == 1) {
            $wheres = array(
                array('column' => 'parent_id', 'value' => $parent_id, 'operator' => '='),
                array('column' => 'cat_type', 'value' => $arr_cat_type, 'operator' => 'wherein'),
            );
        } else {
            $wheres = array(
                array('column' => 'parent_id', 'value' => $parent_id, 'operator' => '='),
                array('column' => 'cat_type', 'value' => $arr_cat_type, 'operator' => 'wherein'),
            );
        }

        $offset = isset($data['offset']) ? $data['offset'] : null;
        $limit = isset($data['limit']) ? $data['limit'] : null;
        $sort = array(
            array('column' => 'listorder', 'direction' => 'ASC')
        );
        $result['data'] = $this->goods_cat->getList($wheres, $offset, $limit, $sort);
        $result['_count'] = $this->goods_cat->getCount($wheres);
        $result['errcode'] = 0;
        $result['errmsg'] = '';
        return Response::json($result, 200);
    }


}