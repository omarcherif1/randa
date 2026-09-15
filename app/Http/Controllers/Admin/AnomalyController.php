<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Admin\AnomalyResolver;
use App\Http\Controllers\Controller;
use App\Models\Anomalie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Section 4.8 / règle 2.9 — liste des anomalies détectées, avec validation ou
 * exclusion manuelle par un administrateur.
 */
class AnomalyController extends Controller
{
    public function index(Request $request): Response
    {
        $statut = in_array($request->input('statut'), ['a_verifier', 'validee', 'exclue'], true)
            ? $request->input('statut') : 'a_verifier';
        $type = $request->input('type') ?: null;

        $anomalies = Anomalie::query()
            ->when($statut, fn ($q) => $q->where('statut', $statut))
            ->when($type, fn ($q) => $q->where('type', $type))
            ->with('resolvedBy:id,name', 'batch:id,original_filename,created_at')
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Anomalie $a) => [
                'id' => $a->id,
                'type' => $a->type,
                'severity' => $a->severity,
                'resume' => $a->resume,
                'article' => $a->article,
                'statut' => $a->statut,
                'details' => $a->details,
                'note' => $a->note,
                'resolu_par' => $a->resolvedBy?->name,
                'resolu_le' => $a->resolved_at?->toDateTimeString(),
                'import' => $a->batch?->original_filename,
                'corrigeable_ailleurs' => ! in_array($a->type, ['outlier_quantite', 'numero_commande_non_reconnu', 'famille_reclassee'], true),
            ]);

        return Inertia::render('Admin/Anomalies', [
            'anomalies' => $anomalies,
            'filtres' => ['statut' => $statut, 'type' => $type],
            'types' => Anomalie::query()->select('type')->distinct()->orderBy('type')->pluck('type'),
            'compteurs' => Anomalie::query()->selectRaw('statut, COUNT(*) as n')->groupBy('statut')->pluck('n', 'statut'),
        ]);
    }

    public function resolve(Request $request, Anomalie $anomalie, AnomalyResolver $resolver): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in([Anomalie::STATUT_VALIDEE, Anomalie::STATUT_EXCLUE])],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $resolver->trancher($anomalie, $data['decision'], $data['note'] ?? null, $request->user());

        return back()->with('status', 'Anomalie tranchée.');
    }
}
