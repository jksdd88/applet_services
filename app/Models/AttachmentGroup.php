<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttachmentGroup extends Model {

    protected $table = 'attachment_group';
    protected $guarded = ['id'];
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';
}