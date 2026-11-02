<?php

namespace App;

use Illuminate\Notifications\DatabaseNotification as RefferalNotification;

class DatabaseNotification extends RefferalNotification
{
    protected $connection = 'custom_connection';
}