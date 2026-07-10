@extends('layouts.app')

@section('titulo', 'Ordens de Serviço')

@section('conteudo')
    <h1 class="text-2xl font-semibold text-slate-900 mb-6">Ordens de Serviço</h1>

    <div class="space-y-3">
        @forelse ($ordensServico as $os)
            <div class="cz-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-medium text-slate-900">OS #{{ $os->id }}</p>
                        <p class="text-sm text-slate-500">{{ $os->cliente->razao_social ?? $os->cliente->nome ?? 'Cliente' }}</p>
                    </div>

                    <a href="{{ route('os.show', $os->id) }}" class="cz-btn-primary text-sm">
                        Ver detalhes
                    </a>
                </div>
            </div>
        @empty
            <div class="cz-card text-center text-slate-400">
                Nenhuma O.S. encontrada.
            </div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $ordensServico->links() }}
    </div>
@endsection