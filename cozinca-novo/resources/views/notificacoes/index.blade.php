@extends('layouts.app')

@section('titulo', 'Notificações')

@section('conteudo')
    <h1 class="text-2xl font-semibold text-slate-900 mb-6">Notificações</h1>

    <div class="space-y-3">
        @forelse ($notificacoes as $notificacao)
            <div class="cz-card flex items-center justify-between">
                <div>
                    <p class="font-medium text-slate-900">{{ $notificacao->titulo }}</p>
                    <p class="text-sm text-slate-500">{{ $notificacao->mensagem }}</p>
                </div>

                <div class="flex items-center gap-3">
                    <span class="cz-badge-{{ $notificacao->lida ? 'concluida' : 'pendente' }}">
                        {{ $notificacao->lida ? 'Lida' : 'Pendente' }}
                    </span>

                    @unless ($notificacao->lida)
                        <form method="POST" action="{{ route('notificacoes.marcar-lida', $notificacao->id) }}">
                            @csrf
                            <button type="submit" class="cz-btn-primary text-sm">
                                Marcar como lida
                            </button>
                        </form>
                    @endunless
                </div>
            </div>
        @empty
            <div class="cz-card text-center text-slate-400">
                Nenhuma notificação encontrada.
            </div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $notificacoes->links() }}
    </div>
@endsection