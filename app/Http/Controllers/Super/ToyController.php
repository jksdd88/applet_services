<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/1/19
 * Time: 10:15
 */
namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Response;
use App\Facades\Member;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use App\Models\ToyAssist;
use App\Models\ToyMember;
use App\Models\ToyExchange;
use App\Models\Region;
use App\Models\ToyGrabLog;
use App\Models\Merchant;
use Maatwebsite\Excel\Excel;


class ToyController extends Controller
{

    public function __construct(Excel $excel){
        $this->excel = $excel;
    }


    public function getActivityList(Request $request){

        $params = $request->all();

        $offset = isset($params['offset']) ? $params['offset'] : 0;

        $limit = isset($params['limit']) ? $params['limit'] : 10;

        $export = isset($params['export']) ? trim($params['export']) : 0;//是否导出记录

        $query = ToyExchange::select('*');

        $query->where('is_delete',1);

        if(!empty($params['consignee'])){    //收货人名称搜索

            $query->where('consignee','like','%' . $params['consignee'] . '%');
        }

        if(!empty($params['mobile'])){    //收货人号码搜索

            $query->where('mobile','like','%' . $params['mobile'] . '%');
        }

        if(!empty($params['type'])){      //娃娃类型搜索

            $query->where('type','=',$params['type']);

        }

        /**导出记录**/
        if($export){

            //最多导出500条
            $query->take(500);

            $actlist = $query->get()->toArray();
            //编码
            header('Expires: 0');
            header('Cache-control: private');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Content-Description: File Transfer');
            header('Content-Encoding: UTF-8');
            header('Content-type: text/csv; charset=UTF-8');

            $exportData[] = array(
                '编号'      => '',
                '收货人姓名'        => '',
                '收货人手机号'      => '',
                '收货地址'      => '',
                '兑换时间'      => '',
                '娃娃类型'        => '',
            );

            foreach($actlist as $item){

                $type="";
                switch ($item['type'])
                {
                    case 1:$type='10元娃娃';break;
                    case 2:$type='20元娃娃';break;
                    case 3:$type='30元娃娃';break;
                    case 4:$type='优惠券';break;

                }
                $exportData[] = [
                    '编号'      => $item['id'],
                    '收货人姓名'        => $item['consignee'],
                    '收货人手机号'      => $item['mobile'],
                    '收货地址'      =>$item['country_name'].$item['province_name'].$item['city_name'].$item['district_name'].$item['address'],
                    '兑换时间'      => $item['created_time'],
                    '娃娃类型'        => $type,
                ];
            }

            $filename = '活动记录'.date('Ymd',time());
            $this->excel->create($filename, function($excel) use ($exportData) {
                $excel->sheet('export', function($sheet) use ($exportData) {
                    $sheet->fromArray($exportData);
                });
            })->export('xls');
        }


        $count = $query->count();

        $query->orderby('created_time','desc');

        $query->skip($offset);

        $query->take($limit);

        $list = $query->get();

        return Response::json(['errcode'=>0,'_count'=>$count,'errmsg'=>'获取数据 成功','data'=>$list]);

    }

    public function getDataDetail(){

        //参与活动总人数
        $allers = ToyMember::count();

        //参与兑换娃娃总人数
        $exchangers = ToyExchange::where(array('is_delete'=>1))->count();

        //虚拟娃娃抓取数
        $toys = ToyGrabLog::where('result',1)->count();

        //10元总人数
        $teners = ToyExchange::where(array('type'=>1,'is_delete'=>1))->count();

        //20元总人数
        $twentyers = ToyExchange::where(array('type'=>2,'is_delete'=>1))->count();

        //30元总人数
        $thirtyers = ToyExchange::where(array('type'=>3,'is_delete'=>1))->count();

        $data['allers'] = !empty($allers) ? $allers : 0;

        $data['exchangers'] = !empty($exchangers) ? $exchangers : 0;

        $data['toys'] = !empty($toys) ? $toys : 0;

        $data['teners'] = !empty($teners) ? $teners : 0;

        $data['twentyers'] = !empty($twentyers) ? $twentyers : 0;

        $data['thirtyers'] = !empty($thirtyers) ? $thirtyers : 0;

        $rt['errcode'] = 0;

        $rt['errmsg'] = '数据获取成功';

        $rt['data'] = $data;

        return Response::json($rt);

    }
}