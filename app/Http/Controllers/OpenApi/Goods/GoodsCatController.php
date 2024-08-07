<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/9/5
 * Time: 11:15
 * Author: DuMing
 * 商品分类
 */
namespace App\Http\Controllers\OpenApi\Goods;

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
    protected $merchant_id;//商户id

    public function __construct(Request $request, GoodsCat $goods_cat, Merchant $merchant)
    {
        $this->params = $request->all();
        $this->goods_cat = $goods_cat;
        $user_openapi = $request->user();
        $this->merchant_id = $user_openapi['merchant_id'];
        $this->version = 1;
        $result = $merchant->get_data_by_id($this->merchant_id);
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
        //父级商品类目
        if( isset($data['parent_id']) && $data['parent_id']!=='' ){
            $parent_id = intval($data['parent_id']);
        }
        $wheres = array();
        if( isset($parent_id) ){
            $wheres[] = array('column' => 'parent_id', 'value' => $parent_id, 'operator' => '=');
        }
        
        //商品类目类型
        $type = isset($data['type']) ? $data['type'] : 0;
        $arr_cat_type = explode(',', $type);
        //是否有虚拟商品权限的商品类目
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
        if(!empty($arr_cat_type)){
            $wheres[] = array('column' => 'cat_type', 'value' => $arr_cat_type, 'operator' => 'wherein');
        }
        
        $offset = isset($data['offset']) ? $data['offset'] : null;
        $limit = isset($data['limit']) ? $data['limit'] : null;
        $sort = array(
            array('column' => 'listorder', 'direction' => 'ASC')
        );
        $result['data'] = $this->goods_cat->getList($wheres, $offset, $limit, $sort);
        if( !$result['data']->isEmpty() ){
            foreach ( $result['data'] as $key=>$val ){
                unset($result['data'][$key]['listorder']);
                unset($result['data'][$key]['created_time']);
                unset($result['data'][$key]['updated_time']);
            }
        }
        $result['_count'] = $this->goods_cat->getCountByWhere($wheres);
        $result['errcode'] = 0;
        $result['errmsg'] = '';
        return Response::json($result, 200);
    }


}