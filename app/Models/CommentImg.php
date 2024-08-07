<?php
/**商品评论晒图表
 * Created by PhpStorm.
 * User: jiangnan
 * Date: 2017/9/11
 * Time: 16:46
 */
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CommentImg extends Model {

    protected $table = 'order_comment_img';

    protected $guarded = ['id'];

    public $timestamps = false;
}