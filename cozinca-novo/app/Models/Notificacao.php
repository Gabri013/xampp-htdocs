<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notificacao extends Model
{
    protected $table = 'notificacoes';
    public $timestamps = false;
    protected $guarded = ['id'];

    protected $casts = [
        'lida' => 'boolean',
    ];

    public function envios()
    {
        return $this->hasMany(NotificacaoEnvio::class, 'notificacao_id');
    }

    public function scopeNaoLidas($query)
    {
        return $query->where('lida', false);
    }

    public function scopeParaUsuario($query, int $usuarioId)
    {
        return $query->where('usuario_id', $usuarioId);
    }
}