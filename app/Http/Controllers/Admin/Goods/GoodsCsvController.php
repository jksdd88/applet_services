<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/9/5
 * Time: 11:01
 * Author: DuMing
 * 后台商品
 */
namespace App\Http\Controllers\Admin\Goods;


use App\Models\GoodsCsv;
use App\Services\AttachmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use DB;

use App\Jobs\GoodsCsvImg;


class GoodsCsvController extends Controller
{

    protected $params;//参数
    protected $goods_model;//商品model
    protected $fields;           //查询字段
    protected $user_id;          //商户id

    public function __construct(Request $request,AttachmentService $AttachmentService)
    {
        $this->params = $request->all();
        if (app()->isLocal()) {
            $this->user_id = 2;
        } else {
            $user = Auth::user();
            $this->user_id = $user->merchant_id;
        }


        $this->AttachmentService = $AttachmentService;

    }

    /**
     * 商品导入
     * @Author  qinyuan
     */
    public function postGoodsCsv(){

        $file = $_FILES['file'];
        $valid = $this->checkCsvValid($file);
        if($valid !== true) {
            return $valid;
        }

        $s = file_get_contents($file['tmp_name']); //读取文件到变量

        if ($s != mb_convert_encoding($s, "UTF-16LE", "UTF-16LE")) {
            return ['errcode'=>1, 'errmsg'=>'请上传最新版的淘宝助理导出的CSV文件'];exit;
        }
        $s = iconv('UTF-16LE', 'utf-8', substr($s,2));//转码,substr($s,2)的作用是去掉 BOM 头 FFFE

        //有的第三方数据包很不规范,控制符没有用空双引号,可能导致列数不对应，在此加上空双引号
        $s = str_replace("\t\t", "\t\"\"\t", $s);
        $s = str_replace("\t\t", "\t\"\"\t", $s);
        $results = $this->tbStrGetcsv($s, "\t");
        $n = 0;
        $titleRow = [];

        foreach($results as $_row) {
            if($_row[0] == 'title') {
                $titleRow = $_row;
            }
            if($_row[0] != 'version 1.00' && $_row[0] != 'title' && $_row[0] != '宝贝名称' && $_row[0] != '') {
                $row = $this->parseRow($_row, $titleRow);
                /*if($this->addTaobaoGoods($goodsRow)) {
                    $n++;
                }*/
                $imgs_array = $this->parsePicture($row['picture']);
                $imgs = $imgs_array ? json_encode($imgs_array) : '';

                $update_data = [
                    'merchant_id' => $this->user_id,
                    'title' => $row['title'],
                    'price' => $row['price'],
                    'img' => $imgs,
                    'content' => $row['description'],
                    'stock' => $row['num'],
                    'is_delete' => 1
                ];

                $CSV_id = GoodsCsv::insert_data($update_data);
                if($CSV_id){
                    $refundJob = new GoodsCsvImg($CSV_id,$this->user_id);
                    $this->dispatch($refundJob);

                    $n++;
                }
            }
        }

        $data['errcode'] = 0;
        $data['errmsg'] = $n.'个商品导入成功，正在同步商品信息，请稍后刷新查看！';
        //$data['data']['count'] = $n > 0 ? $n : 0 ;
        return Response::json($data);
    }
    private function parsePicture($picture)
    {
        $imgs = [];
        if(!$picture) {
            return $imgs;
        }
        $pictures = explode(';', $picture);
        unset($pictures[count($pictures)-1]);
        if($pictures) {
            foreach($pictures as $_img) {
                $imgs[] = ltrim(strstr($_img, '|'), '|');
            }
        }
        return $imgs;
    }
    private function checkCsvValid($file)
    {
        $mimes = array('application/vnd.ms-excel','application/octet-stream','text/plain','text/csv','text/tsv');

        if(isset($file['error']) && $file['error'] > 0) {
            return ['errcode'=>1, 'errmsg'=>$file['error']];
        }else if(!in_array($file['type'],$mimes)){
            return ['errcode'=>1, 'errmsg'=>'请上传csv文件'];
        } else if($file['size'] > 1024*1024) {
            return ['errcode'=>1, 'errmsg'=>'上传的CSV文件必须小于1MB'];
        }
        return true;
    }
    private function tbStrGetcsv($string, $delimiter=',', $enclosure='"') {
        $fp = fopen('php://temp/', 'r+');
        fputs($fp, $string);
        rewind($fp);
        $r = [];
        while($t = fgetcsv($fp, strlen($string), $delimiter, $enclosure)) {
            $r[] = $t;
        }
        if(count($r) == 1) {
            return current($r);
        }
        return $r;
    }
    private function parseRow($row, $titleRow)
    {
        $parsedRow = [];
        if(!empty($row) && is_array($row)){
            foreach($row as $key=>$_row) {
                if(isset($titleRow[$key])){
                    $parsedRow[$titleRow[$key]] = $_row;
                }
            }
        }

        return $parsedRow;
    }


    /**
     * @Author  chang
     * 商品列表（导入的商品列表）
     */
    public function getGoodsList()
    {

        //排序
        $column = isset($this->params['column']) ? $this->params['column'] : 'created_time';
        if (!in_array($column, array('price', 'stock', 'created_time'))) {
            $res['errcode'] = 99001;
            $res['errmsg'] = '排序字段参数错误';
            return Response::json($res, 200);
        }
        //排序值
        $direction = isset($this->params['direct']) ? $this->params['direct'] : 'desc';
        if (!in_array($direction, array('desc', 'asc'))) {
            $res['errcode'] = 99001;
            $res['errmsg'] = '排序参数错误';
            return Response::json($res, 200);
        }
        $order['column'] = $column;
        $order['direct'] = $direction;
        $query = GoodsCsv::select('id', 'title','price','stock','img','created_time','is_delete','state')->where('merchant_id', $this->user_id)->where('is_delete','=','1')->where('state','=','1')->orderBy($column, $direction);


        //标题查询
        $title = isset($this->params['title']) ? trim($this->params['title']) : '';
        if (!empty($title)) {
            $query->where('title', 'like', '%' . $title . '%');
        }


        $count = $query->count();
        $page = isset($this->params['page']) ?
            intval($this->params['page']) <= 0 ? 1 : intval($this->params['page']) : 1;
        $pagesize = isset($this->params['pagesize']) ?
            intval($this->params['pagesize']) <= 0 ? 10 : intval($this->params['pagesize']) : 10;
        $offset = ($page - 1) * $pagesize > $count ? floor($count / $pagesize) * $pagesize : ($page - 1) * $pagesize;
        $limit = $pagesize;

        $data['_count'] = $count;
        $data['errcode'] = 0;
        $data['errmsg'] = 'ok';

        $data['data'] = $query->offset($offset)->limit($limit)->get();
        if(!empty($data['data'])){
            foreach ($data['data'] as $key => $item) {
                if (!empty($item['img'])) {
                    $imgarr = json_decode($item['img'],true);
                    $data['data'][$key]['img'] = isset($imgarr[0]) ? $imgarr[0] : '';
                }
            }
        }


        return Response::json($data);
    }



    /***
     * @Author
     * 商品信息
     */
    public function getGoodsInfo($id)
    {
        $id = intval($id);
        if (empty($id)) {
            $res['errcode'] = 99001;
            $res['errmsg'] = '参数错误';
            return Response::json($res, 200);
        }
        $tem_res = GoodsCsv::get_data_by_id($id, $this->user_id);
        if(!empty($tem_res)){
            $tem_res['img'] = json_decode($tem_res['img'],true);
        }


        $data['errcode'] = 0;
        $data['errmsg'] = 'ok';
        $data['data'] = $tem_res;
        return Response::json($data);
    }


    /***
     * @Author
     * 单个，多个批量删除商品
     */
    public function putGoods()
    {
        if (!isset($this->params['id']) || ((!is_array($this->params['id'])) && intval($this->params['id']) == 0)) {
            $res['errcode'] = 99001;
            $res['errmsg'] = '参数缺失,商品id不能为空';
            return Response::json($res, 200);
        }
        $id = $this->params['id'];
        $ids = [];
        if (!is_array($id)) {
            $id = intval($id);
            $ids = [$id];
        } else if (is_array($id)) {
            $ids = $id;
        }
        if (empty($ids)) {
            $res['errcode'] = 99001;
            $res['errmsg'] = '参数错误';
            return Response::json($res, 200);
        }

        foreach ($ids as $id) {
            $goods_res = GoodsCsv::delete_data($id, $this->user_id);
            if ($goods_res === false) {
                break;
            }
        }

        if ($goods_res === false) {
            $res['errcode'] = 1;
            $res['errmsg'] = '删除失败';
        } else {
            $res['errcode'] = 0;
            $res['errmsg'] = '删除成功';
        }
        return Response::json($res);
    }

}