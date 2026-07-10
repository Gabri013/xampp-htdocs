<?php

namespace App\Http\Controllers;

use App\Models\Notificacao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificacaoController extends Controller
{
    public function index()
    {
        $notificacoes = Notificacao::paraUsuario(Auth::id())
            ->orderByDesc('id')
            ->paginate(20);

        return view('notificacoes.index', compact('notificacoes'));
    }

    public function marcarLida(Request $request, int $id)
    {
        $notificacao = Notificacao::paraUsuario(Auth::id())->findOrFail($id);
        $notificacao->update(['lida' => true]);

        return back()->with('status', 'Notificação marcada como lida.');
    }
}