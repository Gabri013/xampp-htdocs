<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificacaoEnvio extends Model
{
    protected $table = 'notificacoes_envios';
    public $timestamps = false;
    protected $guarded = ['id'];

    public function notificacao()
    {
        return $this->belongsTo(Notificacao::class, 'notificacao_id');
    }
}