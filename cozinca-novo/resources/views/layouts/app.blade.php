<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('titulo', 'Cozinca ERP')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-800 font-sans">
    <div class="flex">
        <aside class="cz-sidebar">
            <div class="px-4 py-5 text-lg font-bold border-b border-white/10">
                Cozinca ERP
            </div>
            <nav class="flex-1 py-4">
                <a href="{{ route('notificacoes.index') }}"
                   class="cz-sidebar-link {{ request()->routeIs('notificacoes.*') ? 'active' : '' }}">
                    Notificações
                </a>
                <a href="{{ route('vendas.index') }}"
                   class="cz-sidebar-link {{ request()->routeIs('vendas.*') ? 'active' : '' }}">
                    Vendas
                </a>
                <a href="{{ route('os.index') }}"
                   class="cz-sidebar-link {{ request()->routeIs('os.*') ? 'active' : '' }}">
                    Ordens de Serviço
                </a>
                <a href="{{ route('financeiro.index') }}"
                   class="cz-sidebar-link {{ request()->routeIs('financeiro.*') ? 'active' : '' }}">
                    Financeiro
                </a>
                <a href="{{ route('contas-pagar.index') }}"
                   class="cz-sidebar-link {{ request()->routeIs('contas-pagar.*') ? 'active' : '' }}">
                    Contas a Pagar
                </a>
                <a href="{{ route('producao.index') }}"
                   class="cz-sidebar-link {{ request()->routeIs('producao.*') ? 'active' : '' }}">
                    Produção
                </a>
            </nav>
        </aside>

        <main class="flex-1 p-8">
            @if (session('status'))
                <div class="cz-card mb-6 border-emerald-200 bg-emerald-50 text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="cz-card mb-6 border-red-200 bg-red-50 text-red-800">
                    {{ session('error') }}
                </div>
            @endif

            @yield('conteudo')
        </main>
    </div>
</body>
</html>