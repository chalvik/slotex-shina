<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmFeedback extends Model
{
    public $fillable = [
        'name',
        'city',
        'email',
        'phone',
        'content',
        'image',
        'prompt',
        'source',
    ];
}
