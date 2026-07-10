<?php

namespace App\Http\Controllers;

use App\Models\ContaPagar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContaPagarController extends Controller
{
    public function index()
    {
        $contasPagar = ContaPagar::orderByDesc('id')
            ->paginate(20);

        return view('contas-pagar.index', compact('contasPagar'));
    }

    public function marcarPago(Request $request, int $id)
    {
        $conta = ContaPagar::findOrFail($id);
        $conta->update(['status' => 'PAGO', 'data_pagamento' => now()]);

        return back()->with('status', 'Conta marcada como paga.');
    }
}