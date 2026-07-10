<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OsHistoricoStatus extends Model
{
    protected $table = 'os_historico_status';
    public $timestamps = false;
    protected $guarded = ['id'];

    public function ordemServico()
    {
        return $this->belongsTo(OrdemServico::class, 'os_id');
    }
}