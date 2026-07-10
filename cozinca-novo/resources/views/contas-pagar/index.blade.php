@extends('layouts.app')

@section('titulo', 'Contas a Pagar')

@section('conteudo')
    <h1 class="text-2xl font-semibold text-slate-900 mb-6">Contas a Pagar</h1>

    <div class="space-y-3">
        @forelse ($contasPagar as $conta)
            <div class="cz-card flex items-center justify-between">
                <div>
                    <p class="font-medium text-slate-900">{{ $conta->descricao }}</p>
                    <p class="text-sm text-slate-500">{{ $conta->fornecedor ?? '-' }}</p>
                    <p class="text-sm font-semibold text-cozinca-orange">Venc: {{ $conta->data_vencimento }}</p>
                </div>

                <span class="cz-badge-{{ $conta->status === 'PAGO' ? 'concluida' : 'pendente' }}">
                    {{ ucfirst($conta->status) }}
                </span>
            </div>
        @empty
            <div class="cz-card text-center text-slate-400">
                Nenhuma conta encontrada.
            </div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $contasPagar->links() }}
    </div>
@endsection