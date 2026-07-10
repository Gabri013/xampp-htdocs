<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoCaixa extends Model
{
    protected $table = 'tipos_caixa';
    public $timestamps = false;
    protected $guarded = ['id'];
}