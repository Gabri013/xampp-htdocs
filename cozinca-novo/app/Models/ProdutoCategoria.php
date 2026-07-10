<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProdutoCategoria extends Model
{
    protected $table = 'produto_categorias';
    public $timestamps = false;
    protected $guarded = ['id'];
}