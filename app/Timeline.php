<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Timeline extends Model
{
    use SoftDeletes;

    protected $dates = ['deleted_at'];

    public function donated_item(){
    	return $this->belongsTo('App\DonatedItem', 'model_id');
    }

    public function user(){
    	return $this->belongsTo('App\DonatedItem', 'user_id');
    }
}
