<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Venda extends Model
{
    protected $table = 'vendas';
    public $timestamps = false;
    protected $guarded = ['id'];
    
    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function itens()
    {
        return $this->hasMany(VendaItem::class, 'venda_id');
    }

    public function contasReceber()
    {
        return $this->hasMany(ContaReceber::class, 'venda_id');
    }
}