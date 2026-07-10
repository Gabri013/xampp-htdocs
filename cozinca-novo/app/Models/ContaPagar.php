<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContaPagar extends Model
{
    protected $table = 'contas_pagar';
    public $timestamps = false;
    protected $guarded = ['id'];
}