@extends('layouts.app')

@section('titulo', 'Produção - Dashboard')

@section('conteudo')
    <h1 class="text-2xl font-semibold text-slate-900 mb-6">Ordens em Produção</h1>

    <div class="space-y-3">
        @forelse ($osEmProducao as $os)
            <div class="cz-card">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <p class="font-medium text-slate-900">OS #{{ $os->id }} - {{ $os->venda->cliente->nome ?? 'Cliente' }}</p>
                        <p class="text-sm text-slate-500">Etapa atual: <strong>{{ ucfirst($os->etapa_atual) }}</strong></p>
                    </div>

                    <span class="cz-badge-pendente">
                        {{ ucfirst($os->status) }}
                    </span>
                </div>

                <div class="flex gap-2">
                    <form method="POST" action="{{ route('producao.iniciar-etapa', $os->id) }}">
                        @csrf
                        <input type="hidden" name="etapa" value="{{ $os->etapa_atual }}">
                        <button type="submit" class="cz-btn-primary text-sm">
                            Iniciar etapa
                        </button>
                    </form>

                    <form method="POST" action="{{ route('producao.finalizar-etapa', $os->id) }}">
                        @csrf
                        <input type="hidden" name="etapa" value="{{ $os->etapa_atual }}">
                        <button type="submit" class="cz-btn-accent text-sm">
                            Finalizar etapa
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="cz-card text-center text-slate-400">
                Nenhuma O.S. em produção no momento.
            </div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $osEmProducao->links() }}
    </div>
@endsection