<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VerificationCode extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $fillable = ['user_id', 'email_otp', 'expire_at'];
}
