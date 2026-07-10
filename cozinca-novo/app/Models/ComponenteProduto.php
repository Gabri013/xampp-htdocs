<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComponenteProduto extends Model
{
    protected $table = 'componentes_produto';
    public $timestamps = false;
    protected $guarded = ['id'];

    public function estrutura()
    {
        return $this->belongsTo(EstruturaProduto::class, 'estrutura_id');
    }

    public function insumo()
    {
        return $this->belongsTo(Insumo::class, 'insumo_id');
    }
}