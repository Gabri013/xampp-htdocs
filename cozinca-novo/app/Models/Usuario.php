<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Database\Eloquent\Model;

class Usuario extends Model implements Authenticatable
{
    use AuthenticatableTrait;

    protected $table = 'usuarios';

    public $timestamps = false;

    protected $fillable = [];
    protected $guarded = ['id'];

    protected $hidden = [
        'senha',
    ];

    public function getAuthPassword()
    {
        return $this->senha;
    }

    public function isMaster(): bool
    {
        return $this->tipo === 'master';
    }

    public function isVendedor(): bool
    {
        return $this->tipo === 'vendedor';
    }

    public function isProjetista(): bool
    {
        return $this->tipo === 'projetista';
    }
}