<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LearnQuestion extends Model
{
    protected $table = 'learn_questions';

    protected $fillable = [
        'module_id',
        'question',
        'options',
        'correct_option',
        'explanation',
        'sort_order',
    ];

    protected $casts = [
        'options' => 'array',
    ];
}
