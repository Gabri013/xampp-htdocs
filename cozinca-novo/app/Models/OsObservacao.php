<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OsObservacao extends Model
{
    protected $table = 'os_observacoes';
    public $timestamps = false;
    protected $guarded = ['id'];

    public function ordemServico()
    {
        return $this->belongsTo(OrdemServico::class, 'os_id');
    }
}