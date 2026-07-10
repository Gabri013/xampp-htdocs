<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsuarioExpediente extends Model
{
    protected $table = 'usuarios_expedientes';
    public $timestamps = false;
    protected $guarded = ['id'];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}