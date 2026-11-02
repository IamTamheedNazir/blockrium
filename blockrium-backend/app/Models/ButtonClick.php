<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ResUser;
class ButtonClick extends Model
{
    protected $fillable = ['user_id','rewards_start_time','wallet','button_status'];
    public function user()
    {
        return $this->belongsTo('App\Models\ResUser');
    }
}
