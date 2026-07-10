<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdemProducaoItem extends Model
{
    protected $table = 'ordens_producao_itens';
    public $timestamps = false;
    protected $guarded = ['id'];

    public function ordemProducao()
    {
        return $this->belongsTo(OrdemProducao::class, 'op_id');
    }
}