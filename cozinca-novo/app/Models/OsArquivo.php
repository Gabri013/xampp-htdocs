<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OsArquivo extends Model
{
    protected $table = 'os_arquivos';
    public $timestamps = false;
    protected $guarded = ['id'];

    public function ordemServico()
    {
        return $this->belongsTo(OrdemServico::class, 'os_id');
    }
}