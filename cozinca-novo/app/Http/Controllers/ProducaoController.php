<?php

namespace App\Http\Controllers;

use App\Models\OrdemServico;
use App\Models\OsEtapaProducao;
use App\Services\OSWorkflowStateMachine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProducaoController extends Controller
{
    public function index()
    {
        $osEmProducao = OrdemServico::where('status', 'em_producao')
            ->orWhere('status', 'em_revisao')
            ->orderBy('etapa_atual')
            ->paginate(20);

        return view('producao.index', compact('osEmProducao'));
    }

    public function iniciarEtapa(Request $request, int $osId)
    {
        $os = OrdemServico::findOrFail($osId);
        $etapa = $request->input('etapa');

        $stateMachine = new OSWorkflowStateMachine();

        if (!$stateMachine->podeOperarEtapa($etapa, Auth::user()->tipo ?? '')) {
            return back()->with('error', 'Usuário não tem permissão para operar nesta etapa.');
        }

        if ($os->etapa_atual !== $etapa) {
            return back()->with('error', 'Esta etapa não é a etapa atual da O.S.');
        }

        DB::beginTransaction();
        try {
            $etapaExistente = OsEtapaProducao::where('os_id', $osId)
                ->where('etapa', $etapa)
                ->first();

            if ($etapaExistente && $etapaExistente->status === 'em_andamento') {
                DB::commit();
                return back()->with('status', 'Etapa já estava em andamento.');
            }

            if ($etapaExistente) {
                $etapaExistente->update([
                    'status' => 'em_andamento',
                    'data_inicio' => now(),
                    'usuario_id' => Auth::id(),
                ]);
            } else {
                OsEtapaProducao::create([
                    'os_id' => $osId,
                    'etapa' => $etapa,
                    'status' => 'em_andamento',
                    'data_inicio' => now(),
                    'usuario_id' => Auth::id(),
                ]);
            }

            DB::commit();
            return back()->with('status', 'Etapa iniciada com sucesso.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }

    public function finalizarEtapa(Request $request, int $osId)
    {
        $os = OrdemServico::findOrFail($osId);
        $etapa = $request->input('etapa');

        $stateMachine = new OSWorkflowStateMachine();

        $proximaEtapa = $stateMachine->proximaEtapa($etapa);
        if (!$proximaEtapa) {
            return back()->with('error', 'Etapa finalizada.');
        }

        DB::beginTransaction();
        try {
            $etapaAtual = OsEtapaProducao::where('os_id', $osId)
                ->where('etapa', $etapa)
                ->first();

            if (!$etapaAtual || !$etapaAtual->data_inicio) {
                return back()->with('error', 'Etapa não foi iniciada corretamente.');
            }

            $etapaAtual->update([
                'status' => 'concluida',
                'data_fim' => now(),
            ]);

            $statusOs = $proximaEtapa === 'concluida' ? 'concluida' : 'em_producao';
            $os->update([
                'etapa_atual' => $proximaEtapa,
                'status' => $statusOs,
            ]);

            DB::commit();
            return back()->with('status', 'Etapa finalizada. Próxima: ' . $proximaEtapa);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }
}