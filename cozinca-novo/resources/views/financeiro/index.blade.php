@extends('layouts.app')

@section('titulo', 'Financeiro - Contas a Receber')

@section('conteudo')
    <h1 class="text-2xl font-semibold text-slate-900 mb-6">Contas a Receber</h1>

    <div class="mb-4">
        <span class="cz-badge-pendente">Vendas pendentes: {{ $vendasPendentes }}</span>
    </div>

    <div class="space-y-3">
        @forelse ($contasReceber as $conta)
            <div class="cz-card flex items-center justify-between">
                <div>
                    <p class="font-medium text-slate-900">{{ $conta->venda->numero ?? 'Venda #' . $conta->venda_id }}</p>
                    <p class="text-sm text-slate-500">{{ $conta->venda->cliente->nome ?? 'Cliente' }}</p>
                    <p class="text-sm font-semibold text-cozinca-blue">R$ {{ number_format($conta->valor_bruto, 2, ',', '.') }}</p>
                </div>

                <div class="flex items-center gap-3">
                    <span class="cz-badge-{{ $conta->status === 'PAGO' ? 'concluida' : 'pendente' }}">
                        {{ $conta->status }}
                    </span>

                    @if ($conta->status !== 'PAGO')
                        <form method="POST" action="{{ route('financeiro.marcar-pago', $conta->id) }}">
                            @csrf
                            <button type="submit" class="cz-btn-accent text-sm">
                                Marcar paga
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <div class="cz-card text-center text-slate-400">
                Nenhuma conta encontrada.
            </div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $contasReceber->links() }}
    </div>
@endsection