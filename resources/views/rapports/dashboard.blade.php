@php
    use Illuminate\Support\Carbon;
    $pct = fn ($v) => $v === null ? 'donnée indisponible' : number_format($v * 100, 1, ',', ' ') . ' %';
    $nb = fn ($v) => number_format((float) $v, 1, ',', ' ');
    $ent = fn ($v) => number_format((int) $v, 0, ',', ' ');
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Rapport — Tableau de bord</title>
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
        .note { background: #fffbeb; border: 1px solid #fde68a; padding: 4px 6px; font-size: 10px; margin-top: 4px; }
    </style>
</head>
<body>
    <h1>Pilotage de la satisfaction client — Rapport</h1>
    <div class="muted">
        Groupe Randa · données locales uniquement ·
        généré le {{ $genere_le->format('d/m/Y à H:i') }}@if($genere_par) par {{ $genere_par }}@endif
        @if($metrics['fraicheur'])
            · fraîcheur des données : {{ Carbon::parse($metrics['fraicheur'])->format('d/m/Y H:i') }}
        @endif
    </div>
    @php($f = $metrics['filtres'])
    <div class="muted">
        Filtres —
        société : {{ $f['societes'] ? implode(', ', $f['societes']) : 'toutes' }} ·
        produit : {{ $f['articles'] ? implode(', ', $f['articles']) : 'tous' }} ·
        familles : {{ $f['familles'] ? implode(', ', $f['familles']) : 'toutes' }} ·
        types client : {{ $f['client_categories'] ? implode(', ', $f['client_categories']) : 'tous' }} ·
        période : {{ $f['from'] ?? '—' }} → {{ $f['to'] ?? '—' }}
    </div>

    <h2>Taux de satisfaction par société (jamais moyenné entre sociétés)</h2>
    <div class="muted">Une commande est satisfaite si sa quantité livrée couvre sa quantité commandée. Taux = commandes satisfaites / commandes.</div>
    <table>
        <tr><th>Société</th><th class="n">Commandes</th><th class="n">Satisfaites</th><th class="n">Taux</th></tr>
        @foreach($metrics['satisfaction']['par_societe'] as $s)
            <tr>
                <td>{{ $s['nom'] }}</td>
                <td class="n">{{ $ent($s['commandes']) }}</td>
                <td class="n">{{ $ent($s['satisfaites']) }}</td>
                <td class="n">{{ $pct($s['taux']) }}</td>
            </tr>
        @endforeach
    </table>

    <h2>Indicateurs clés</h2>
    @php($k = $metrics['kpi'])
    <div class="kpi"><div class="v">{{ $ent($k['commandes']['total']) }}</div><div class="l">Commandes</div></div>
    <div class="kpi"><div class="v">{{ $ent($k['clients_actifs']) }}</div><div class="l">Clients actifs</div></div>
    <div class="kpi"><div class="v">{{ $nb($k['tonnes_distribuees']) }} t</div><div class="l">Tonnes distribuées</div></div>
    <div class="kpi"><div class="v">{{ $nb($k['qte_commandee_kg'] / 1000) }} t</div><div class="l">Commandé</div></div>
    <div class="kpi"><div class="v">{{ $nb($k['qte_facturee_kg'] / 1000) }} t</div><div class="l">Facturé</div></div>
    <div class="kpi"><div class="v">{{ $nb($k['qte_non_livree_kg'] / 1000) }} t</div><div class="l">Non livré</div></div>
    <div class="kpi"><div class="v">{{ $ent($k['villes']) }}</div><div class="l">Villes</div></div>
    <div class="kpi"><div class="v">{{ $ent($k['produits_en_rupture']) }}</div><div class="l">Produits en rupture</div></div>
    <div class="kpi"><div class="v">{{ $pct($k['taux_rupture']) }}</div><div class="l">Taux de rupture (méthode B)</div></div>
    <div class="kpi"><div class="v">{{ $ent($k['commandes_en_retard']) }}</div><div class="l">Commandes en retard (seuil {{ $k['seuil_retard_jours'] }} j)</div></div>

    <table>
        <tr><th>Commandes ouvertes</th><th>Livrées</th><th>Facturées</th><th>Clôturées</th></tr>
        <tr>
            <td class="n">{{ $ent($k['commandes']['ouvertes']) }}</td>
            <td class="n">{{ $ent($k['commandes']['livrees']) }}</td>
            <td class="n">{{ $ent($k['commandes']['facturees']) }}</td>
            <td class="n">{{ $ent($k['commandes']['cloturees']) }}</td>
        </tr>
    </table>
    <div class="note">
        {{ $k['lignes_hors_tonnage'] }} ligne(s) exclue(s) des tonnages (poids d'unité à confirmer).
    </div>

    <h2>Top produits commandés</h2>
    <table>
        <tr><th>Article</th><th>Désignation</th><th class="n">Tonnes</th></tr>
        @foreach($metrics['classements']['top_produits_commandes'] as $p)
            <tr><td>{{ $p['article'] }}</td><td>{{ $p['designation'] }}</td><td class="n">{{ $nb($p['tonnes']) }}</td></tr>
        @endforeach
    </table>

    <h2>Top produits en rupture (tonnes manquantes)</h2>
    <table>
        <tr><th>Article</th><th>Désignation</th><th class="n">Tonnes manquantes</th></tr>
        @forelse($metrics['classements']['top_produits_rupture'] as $p)
            <tr><td>{{ $p['article'] }}</td><td>{{ $p['designation'] }}</td><td class="n">{{ $nb($p['tonnes_manquantes']) }}</td></tr>
        @empty
            <tr><td colspan="3">Aucun</td></tr>
        @endforelse
    </table>

    <h2>Répartition par type de client</h2>
    <table>
        <tr><th>Type de client</th><th class="n">Commandes</th><th class="n">Tonnes</th></tr>
        @foreach($metrics['repartitions']['par_type_client'] as $r)
            <tr><td>{{ $r['label'] }}</td><td class="n">{{ $ent($r['commandes']) }}</td><td class="n">{{ $nb($r['tonnes']) }}</td></tr>
        @endforeach
    </table>

    <div class="muted" style="margin-top:18px">
        Ville = ville de la <b>commande</b> (pas d'une vraie ville de livraison). Retards : donnée d'expédition indisponible.
    </div>
</body>
</html>
