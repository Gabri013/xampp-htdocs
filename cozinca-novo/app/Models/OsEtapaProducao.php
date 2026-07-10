<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OsEtapaProducao extends Model
{
    protected $table = 'os_etapas_producao';
    public $timestamps = false;
    protected $guarded = ['id'];

    public function ordemServico()
    {
        return $this->belongsTo(OrdemServico::class, 'os_id');
    }
}