<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ItemReview extends Model
{
    use SoftDeletes;

    protected $dates = ['deleted_at'];
}
