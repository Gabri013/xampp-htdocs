<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendaItem extends Model
{
    protected $table = 'vendas_itens';
    public $timestamps = false;
    protected $guarded = ['id'];

    public function venda()
    {
        return $this->belongsTo(Venda::class, 'venda_id');
    }

    public function produto()
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }
}