<?php

namespace App\Http\Controllers;

use App\Models\OrdemServico;
use App\Models\OsArquivo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OSController extends Controller
{
    public function index()
    {
        $ordensServico = OrdemServico::with('cliente')
            ->orderByDesc('id')
            ->paginate(20);

        return view('os.index', compact('ordensServico'));
    }

    public function show(OrdemServico $ordemServico)
    {
        $ordemServico->load(['cliente', 'etapasProducao', 'historicoStatus']);
        
        return view('os.show', compact('ordemServico'));
    }
}