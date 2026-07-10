<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdemProducao extends Model
{
    protected $table = 'ordens_producao';
    public $timestamps = false;
    protected $guarded = ['id'];

    public function ordemServico()
    {
        return $this->belongsTo(OrdemServico::class, 'os_id');
    }

    public function itens()
    {
        return $this->hasMany(OrdemProducaoItem::class, 'op_id');
    }
}