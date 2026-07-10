<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContaReceber extends Model
{
    protected $table = 'contas_receber';
    public $timestamps = false;
    protected $guarded = ['id'];
    
    protected $casts = [
        'lida' => 'boolean',
    ];
    
    public function venda()
    {
        return $this->belongsTo(Venda::class, 'venda_id');
    }
    
    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }
}