<?php

namespace App\Http\Controllers;

use App\Models\Venda;
use App\Models\ContaReceber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FinanceiroController extends Controller
{
    public function index()
    {
        $contasReceber = ContaReceber::with('venda.cliente')
            ->orderByDesc('id')
            ->paginate(20);

        $vendasPendentes = Venda::where('status', 'aprovada')
            ->whereNull('faturado_em')
            ->count();

        return view('financeiro.index', compact('contasReceber', 'vendasPendentes'));
    }
    
    public function marcarPago(Request $request, int $id)
    {
        $conta = ContaReceber::findOrFail($id);
        $conta->update(['status' => 'PAGO', 'data_pagamento' => now()]);

        return back()->with('status', 'Conta marcada como paga.');
    }
}