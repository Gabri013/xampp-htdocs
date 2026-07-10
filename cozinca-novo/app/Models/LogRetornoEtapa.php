<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogRetornoEtapa extends Model
{
    protected $table = 'logs_retorno_etapa';
    public $timestamps = false;
    protected $guarded = ['id'];
}