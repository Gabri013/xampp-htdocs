@extends('layouts.app')

@section('titulo', 'OS #' . $ordemServico->id)

@section('conteudo')
    <h1 class="text-2xl font-semibold text-slate-900 mb-6">OS #{{ $ordemServico->id }}</h1>

    <div class="cz-card mb-6">
        <h3 class="text-lg font-semibold mb-3">Detalhes da O.S.</h3>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div><strong>Cliente:</strong> {{ $ordemServico->cliente->razao_social ?? $ordemServico->cliente->nome ?? '-' }}</div>
            <div><strong>Status:</strong> {{ ucfirst($ordemServico->status) }}</div>
            <div><strong>Etapa atual:</strong> {{ ucfirst($ordemServico->etapa_atual) }}</div>
        </div>
    </div>

    <div class="cz-card mb-6">
        <h3 class="text-lg font-semibold mb-3">Etapas de Produção</h3>
        <div class="space-y-2">
            @forelse ($ordemServico->etapasProducao as $etapa)
                <div class="flex justify-between items-center border-b pb-2">
                    <span>{{ ucfirst($etapa->etapa) }}</span>
                    <span class="cz-badge-{{ $etapa->status === 'concluida' ? 'concluida' : 'pendente' }}">
                        {{ ucfirst($etapa->status) }}
                    </span>
                </div>
            @empty
                <p class="text-slate-400">Nenhuma etapa registrada.</p>
            @endforelse
        </div>
    </div>

    <div class="cz-card">
        <h3 class="text-lg font-semibold mb-3">Histórico de Status</h3>
        <div class="space-y-2">
            @forelse ($ordemServico->historicoStatus as $hist)
                <div class="text-sm border-b pb-2">
                    <span class="text-slate-500">{{ $hist->created_at ? \Carbon\Carbon::parse($hist->created_at)->format('d/m/Y H:i') : '-' }}</span>
                    — {{ ucfirst($hist->status_anterior) }} → {{ ucfirst($hist->status_novo) }}
                </div>
            @empty
                <p class="text-slate-400">Sem histórico.</p>
            @endforelse
        </div>
    </div>
@endsection