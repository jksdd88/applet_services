<?php
/**
 * Created by PhpStorm.
 * User: jiangnan
 * Date: 2017/9/12
 * Time: 13:10
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommentVirtualMember extends Model
{
    protected $table = 'virtual_member';

    protected $guarded = ['id'];
}