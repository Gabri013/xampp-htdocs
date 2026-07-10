<?php

namespace App\Http\Controllers;

use App\Models\Venda;
use App\Models\OrdemServico;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VendaController extends Controller
{
    public function index()
    {
        $vendas = Venda::with('cliente')
            ->orderByDesc('id')
            ->paginate(20);

        return view('vendas.index', compact('vendas'));
    }

    public function create()
    {
        return view('vendas.create');
    }
}