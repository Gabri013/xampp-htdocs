<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstruturaProduto extends Model
{
    protected $table = 'estrutura_produto';
    public $timestamps = false;
    protected $guarded = ['id'];

    public function produto()
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }

    public function componentes()
    {
        return $this->hasMany(ComponenteProduto::class, 'estrutura_id');
    }
}