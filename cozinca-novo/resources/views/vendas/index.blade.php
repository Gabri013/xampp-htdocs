@extends('layouts.app')

@section('titulo', 'Vendas')

@section('conteudo')
    <h1 class="text-2xl font-semibold text-slate-900 mb-6">Vendas</h1>

    <div class="space-y-3">
        @forelse ($vendas as $venda)
            <div class="cz-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-medium text-slate-900">Venda #{{ $venda->id }}</p>
                        <p class="text-sm text-slate-500">{{ $venda->cliente->razao_social ?? $venda->cliente->nome ?? 'Cliente' }}</p>
                        <p class="text-sm font-semibold text-cozinca-blue">R$ {{ number_format($venda->valor_total ?? 0, 2, ',', '.') }}</p>
                    </div>

                    <a href="#" class="cz-btn-primary text-sm">
                        Ver detalhes
                    </a>
                </div>
            </div>
        @empty
            <div class="cz-card text-center text-slate-400">
                Nenhuma venda encontrada.
            </div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $vendas->links() }}
    </div>
@endsection