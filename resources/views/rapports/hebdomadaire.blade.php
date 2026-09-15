@php
    use Illuminate\Support\Carbon;
    $pct = fn ($v) => $v === null ? 'donnée indisponible' : number_format($v * 100, 1, ',', ' ') . ' %';
    $nb = fn ($v) => number_format((float) $v, 1, ',', ' ');
    $ent = fn ($v) => number_format((int) $v, 0, ',', ' ');
    $delta = function ($actuel, $precedent) {
        if ($actuel === null || $precedent === null) return '—';
        $d = ($actuel - $precedent) * 100;
        $signe = $d > 0 ? '+' : '';
        return $signe . number_format($d, 1, ',', ' ') . ' pt';
    };
    $jf = fn ($d) => Carbon::parse($d)->format('d/m/Y');
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Rapport hebdomadaire</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #1f2937; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        h2 { font-size: 13px; margin: 18px 0 6px; border-bottom: 1px solid #d1d5db; padding-bottom: 2px; }
        .muted { color: #6b7280; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        th, td { border: 1px solid #d1d5db; padding: 3px 6px; text-align: left; }
        th { background: #f3f4f6; font-weight: bold; }
        td.n, th.n { text-align: right; }
        .kpi { display: inline-block; width: 23%; margin: 0 1% 8px 0; border: 1px solid #d1d5db; padding: 6px; vertical-align: top; }
        .kpi .v { font-size: 15px; font-weight: bold; }
        .kpi .l { font-size: 9px; color: #6b7280; text-transform: uppercase; }
        .kpi .d { font-size: 9px; color: #6b7280; }
        .note { background: #fffbeb; border: 1px solid #fde68a; padding: 4px 6px; font-size: 10px; margin-top: 4px; }
    </style>
</head>
<body>
    <h1>Rapport hebdomadaire — pilotage de la satisfaction client</h1>
    <div class="muted">
        Groupe Randa · données locales uniquement ·
        semaine du <b>{{ $jf($r['periode']['du']) }}</b> au <b>{{ $jf($r['periode']['au']) }}</b>
        (comparée au {{ $jf($r['periode_precedente']['du']) }} → {{ $jf($r['periode_precedente']['au']) }})
        · généré le {{ $r['genere_le']->format('d/m/Y à H:i') }}@if($r['genere_par']) par {{ $r['genere_par'] }}@endif
        @if($r['fraicheur']) · fraîcheur des données : {{ Carbon::parse($r['fraicheur'])->format('d/m/Y H:i') }} @endif
    </div>

    <h2>Taux de satisfaction par société (jamais moyenné entre sociétés)</h2>
    <div class="muted">Une commande est satisfaite si sa quantité livrée couvre sa quantité commandée.</div>
    <table>
        <tr><th>Société</th><th class="n">Commandes</th><th class="n">Satisfaites</th><th class="n">Taux</th><th class="n">vs sem. préc.</th></tr>
        @foreach($r['satisfaction'] as $s)
            <tr>
                <td>{{ $s['nom'] }}</td>
                <td class="n">{{ $ent($s['commandes']) }}</td>
                <td class="n">{{ $ent($s['satisfaites']) }}</td>
                <td class="n">{{ $pct($s['taux']) }}</td>
                <td class="n">{{ $delta($s['taux'], $s['taux_precedent']) }}</td>
            </tr>
        @endforeach
    </table>

    <h2>Indicateurs clés de la semaine</h2>
    @php($k = $r['kpi'])
    @php($kp = $r['kpi_precedent'])
    <div class="kpi"><div class="v">{{ $pct($k['taux_satisfaction_volume']) }}</div><div class="l">Satisfaction (volume)</div><div class="d">préc. {{ $pct($kp['taux_satisfaction_volume']) }}</div></div>
    <div class="kpi"><div class="v">{{ $ent($k['commandes']['total']) }}</div><div class="l">Commandes</div><div class="d">préc. {{ $ent($kp['commandes']['total']) }}</div></div>
    <div class="kpi"><div class="v">{{ $ent($k['clients_actifs']) }}</div><div class="l">Clients actifs</div><div class="d">préc. {{ $ent($kp['clients_actifs']) }}</div></div>
    <div class="kpi"><div class="v">{{ $nb($k['tonnes_distribuees']) }} t</div><div class="l">Tonnes distribuées</div><div class="d">préc. {{ $nb($kp['tonnes_distribuees']) }} t</div></div>
    <div class="kpi"><div class="v">{{ $nb($k['qte_non_livree_kg'] / 1000) }} t</div><div class="l">Non livré</div><div class="d">préc. {{ $nb($kp['qte_non_livree_kg'] / 1000) }} t</div></div>
    <div class="kpi"><div class="v">{{ $ent($k['produits_en_rupture']) }}</div><div class="l">Produits en rupture</div><div class="d">préc. {{ $ent($kp['produits_en_rupture']) }}</div></div>

    <h2>Produits en rupture — les 5 plus critiques</h2>
    <table>
        <tr><th>Article</th><th>Désignation</th><th class="n">Couverture</th></tr>
        @forelse($r['ruptures'] as $p)
            <tr><td>{{ $p['article'] }}</td><td>{{ $p['designation'] ?? '' }}</td><td class="n">{{ $pct($p['taux_couverture'] ?? null) }}</td></tr>
        @empty
            <tr><td colspan="3">Aucune rupture sur la période.</td></tr>
        @endforelse
    </table>

    <h2>Principaux clients de la semaine</h2>
    <table>
        <tr><th>Client</th><th>N°</th><th class="n">Tonnes</th></tr>
        @forelse($r['top_clients'] as $c)
            <tr><td>{{ $c['nom'] }}</td><td>{{ $c['numero'] }}</td><td class="n">{{ $nb($c['tonnes']) }}</td></tr>
        @empty
            <tr><td colspan="3">Aucune donnée.</td></tr>
        @endforelse
    </table>

    <h2>Lignes non satisfaites — causes</h2>
    <table>
        <tr><th>Cause</th><th class="n">Lignes</th></tr>
        @forelse($r['causes'] as $c)
            <tr><td>{{ $c['cause'] }}</td><td class="n">{{ $ent($c['lignes']) }}</td></tr>
        @empty
            <tr><td colspan="2">Aucune.</td></tr>
        @endforelse
    </table>

    <div class="note">
        {{ $ent($r['anomalies_a_verifier']) }} anomalie(s) en attente d'arbitrage administrateur.
        Retards de livraison : donnée d'expédition indisponible tant qu'une vraie date n'est pas fournie par la source.
    </div>
</body>
</html>
