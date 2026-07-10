<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdemServico extends Model
{
    protected $table = 'ordens_servico';
    public $timestamps = false;
    protected $guarded = ['id'];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function etapasProducao()
    {
        return $this->hasMany(OsEtapaProducao::class, 'os_id');
    }

    public function historicoStatus()
    {
        return $this->hasMany(OsHistoricoStatus::class, 'os_id')->orderByDesc('id');
    }
}