<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 10mm 8mm; size: A4 landscape; }
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #222; }

    /* ---------- En-tête établissement ---------- */
    .estab-header { width: 100%; border-bottom: 2.5px solid #1a2a5e; padding-bottom: 4px; margin-bottom: 2px; }
    .estab-header td { border: none !important; }
    .estab-logo img { height: 46px; }
    .estab-name { font-size: 13px; font-weight: bold; color: #1a2a5e; }
    .estab-contact { color: #666; font-size: 8px; }

    /* ---------- Titre ---------- */
    .doc-title { text-align: center; margin: 6px 0 0; }
    .doc-title h1 { font-size: 16px; color: #111; margin: 0; letter-spacing: 0.5px; }
    .doc-subtitle { text-align: center; font-size: 10.5px; font-style: italic; color: #444; margin: 2px 0 6px; }
    .doc-subtitle b { color: #1a2a5e; font-style: normal; }

    /* ---------- Grille horaire ---------- */
    table.grid { width: 100%; border-collapse: collapse; border: 2px solid #111; table-layout: fixed; }
    table.grid th, table.grid td { border: 1px solid #111; }

    /* Bande "La matinée / L'après-midi" */
    th.period { background: #fff; font-size: 9px; color: #111; text-align: center; padding: 3px 0; border-bottom: none; }
    th.period-left { text-align: left !important; padding-left: 6px !important; }

    /* Ligne des créneaux */
    th.slot { background: #fff; font-size: 7.5px; color: #333; text-align: center; padding: 2px 0; font-weight: normal; border-top: 1px solid #111; }

    /* Colonne jours */
    th.day-col { background: #e8e8e8; width: 9mm; font-size: 8.5px; color: #111; text-align: center; vertical-align: middle; padding: 3px 2px; writing-mode: horizontal-tb; }
    th.day-col .arrow { color: #1a2a5e; font-weight: bold; }

    /* Cellules de séance */
    td.session { padding: 1px 1px 2px; vertical-align: middle; text-align: center; }
    td.session .mod { font-size: 8px; font-weight: bold; }
    td.session .detail { font-size: 6.5px; color: #333; line-height: 1.15; }
    td.session .duration { font-size: 6.5px; font-weight: bold; }

    /* Vide */
    td.empty { background: #f7f7f7; }

    /* Colonne pause (entre matinée et après-midi) */
    th.pause { background: #eaf7ee; width: 4mm; font-size: 6.5px; color: #2e7d32; text-align: center; vertical-align: middle; }

    /* Légende */
    .legend { margin-top: 6px; font-size: 7.5px; color: #555; }
    .legend table { border-collapse: collapse; margin-top: 2px; }
    .legend td { border: 1px solid #bbb; padding: 1px 6px; font-size: 7.5px; }
    .legend .swatch { display: inline-block; width: 8px; height: 8px; border: 1px solid #333; margin-right: 3px; vertical-align: middle; }

    /* Pied de page */
    .footer { margin-top: 8px; border-top: 1px solid #ccc; padding-top: 4px; width: 100%; }
    .footer td { border: none !important; font-size: 7.5px; color: #666; }
    .signature { width: 33%; text-align: center; }
</style>
</head>
<body>
@php
    $estab = \App\Models\Setting::allValues();
    $estabLogo = $estab['logo_path'] ? public_path('storage/' . $estab['logo_path']) : null;
    $estabName = $estab['establishment_name'] ?? 'PlanifUni';
    $hasContacts = $estab['establishment_address'] || $estab['establishment_phone'] || $estab['establishment_email'];
    $estabPhone = $estab['establishment_phone'] ? " · Tél : {$estab['establishment_phone']}" : '';
    $estabEmail = $estab['establishment_email'] ? " · {$estab['establishment_email']}" : '';

    // Regrouper les entrées : (jour, créneau) => [sessions]
    $days = $entries->groupBy('day_id')->sortKeys();
    $timeslots = $entries->groupBy('timeslot_id')->sortKeys();
    $slotsList = $entries->unique('timeslot_id')->sortBy(function ($e) {
        return strtotime($e->starts_at);
    })->values();
    $daysList = $entries->unique('day_id')->sortBy('day_id')->values();

    // Séparer matinée / après-midi : premier créneau à partir de 12h00 = après-midi
    $amSlots = $slotsList->filter(fn($s) => strtotime($s->starts_at) < strtotime('12:00'))->values();
    $pmSlots = $slotsList->filter(fn($s) => strtotime($s->starts_at) >= strtotime('12:00'))->values();

    // Palette de couleurs pastel par module (cohérente dans toute la semaine)
    $modulePalette = [
        '#fde68a', '#fed7aa', '#bbf7d0', '#bae6fd', '#ddd6fe',
        '#fecaca', '#a7f3d0', '#fbcfe8', '#c7d2fe', '#fde9b8',
        '#d8b4fe', '#86efac', '#7dd3fc', '#fca5a5', '#fdba74',
    ];
    $moduleColors = [];
    $i = 0;
    foreach ($entries->pluck('module') as $mod) {
        if (!isset($moduleColors[$mod])) {
            $moduleColors[$mod] = $modulePalette[$i % count($modulePalette)];
            $i++;
        }
    }
    function durationMinutes(string $starts, string $ends): int
    {
        return (int) round((strtotime($ends) - strtotime($starts)) / 60);
    }
@endphp

{{-- En-tête établissement --}}
<table class="estab-header">
    <tr>
        @if($estabLogo && file_exists($estabLogo))
        <td class="estab-logo" rowspan="2"><img src="{{ $estabLogo }}"></td>
        @endif
        <td style="padding-left: 8px;">
            <div class="estab-name">{{ $estabName }}</div>
            @if($hasContacts)
            <div class="estab-contact">
                @if($estab['establishment_address']){{ $estab['establishment_address'] }}@endif
                @if($estabPhone){{ $estabPhone }}@endif
                @if($estabEmail){{ $estabEmail }}@endif
            </div>
            @endif
        </td>
    </tr>
</table>

<div class="doc-title">
    <h1>Emploi du temps {{ $academicYear }}</h1>
</div>
<div class="doc-subtitle">
    <b>{{ $program }}</b> &nbsp;·&nbsp; {{ $semester->name }} &nbsp;·&nbsp; Généré le {{ $generatedAt }}
</div>

@foreach ($entries->groupBy('groupe') as $groupName => $groupEntries)
    @php
        $gDays = $groupEntries->groupBy('day_id')->sortKeys();
        $gSlotList = $groupEntries->unique('timeslot_id')->sortBy(function ($e) { return strtotime($e->starts_at); })->values();
        $gAm = $gSlotList->filter(fn($s) => strtotime($s->starts_at) < strtotime('12:00'))->values();
        $gPm = $gSlotList->filter(fn($s) => strtotime($s->starts_at) >= strtotime('12:00'))->values();
        $hasPause = $gAm->isNotEmpty() && $gPm->isNotEmpty();
    @endphp
    @if($hasPause)
        {{-- ===== Grille avec séparation matinée / après-midi ===== --}}
        <table class="grid">
            <colgroup>
                <col style="width: 9mm;">
                @foreach($gAm as $s)<col>@endforeach
                @if($hasPause)<col style="width: 4mm;">@endif
                @foreach($gPm as $s)<col>@endforeach
            </colgroup>
            <tr>
                <th class="day-col" rowspan="2" style="border-bottom: none;"></th>
                @php $amColspan = $gAm->count() + ($hasPause ? 1 : 0); @endphp
                <th class="period period-left" colspan="{{ $amColspan }}">
                    ▶ La matinée
                </th>
                @if($hasPause)
                <th class="pause" rowspan="2">Pause</th>
                @endif
                <th class="period" colspan="{{ $gPm->count() }}">
                    L'après-midi
                </th>
            </tr>
            <tr>
                @foreach($gAm as $s)<th class="slot">{{ $s->timeslot_name }}</th>@endforeach
                @foreach($gPm as $s)<th class="slot">{{ $s->timeslot_name }}</th>@endforeach
            </tr>
            @foreach($gDays as $dayId => $dayEntries)
                @php $dayFr = $exportService->translateDay($dayEntries->first()->day_name); @endphp
                <tr>
                    <th class="day-col">{{ $dayFr }} <span class="arrow">▶</span></th>
                    @foreach($gAm as $s)
                        @php $cell = $dayEntries->first(fn($e) => $e->timeslot_id == $s->timeslot_id); @endphp
                        <td class="{{ $cell ? 'session' : 'empty' }}" @if($cell) style="background: {{ $moduleColors[$cell->module] }};" @endif>
                            @if($cell)
                                <div class="mod">{{ $cell->module }}</div>
                                <div class="detail">{{ $cell->professeur }} · {{ $cell->salle }}</div>
                                <div class="duration">{{ durationMinutes($cell->starts_at, $cell->ends_at) }} min</div>
                            @endif
                        </td>
                    @endforeach
                    @foreach($gPm as $s)
                        @php $cell = $dayEntries->first(fn($e) => $e->timeslot_id == $s->timeslot_id); @endphp
                        <td class="{{ $cell ? 'session' : 'empty' }}" @if($cell) style="background: {{ $moduleColors[$cell->module] }};" @endif>
                            @if($cell)
                                <div class="mod">{{ $cell->module }}</div>
                                <div class="detail">{{ $cell->professeur }} · {{ $cell->salle }}</div>
                                <div class="duration">{{ durationMinutes($cell->starts_at, $cell->ends_at) }} min</div>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </table>
    @else
        {{-- ===== Grille simple (un seul bloc horaire) ===== --}}
        <table class="grid">
            <colgroup>
                <col style="width: 9mm;">
                @foreach($gSlotList as $s)<col>@endforeach
            </colgroup>
            <tr>
                <th class="day-col" rowspan="2" style="border-bottom: none;"></th>
                <th class="slot" colspan="{{ $gSlotList->count() }}" style="border-bottom: none; font-size: 8px;">Emploi du temps</th>
            </tr>
            <tr>
                @foreach($gSlotList as $s)<th class="slot">{{ $s->timeslot_name }}</th>@endforeach
            </tr>
            @foreach($gDays as $dayId => $dayEntries)
                @php $dayFr = $exportService->translateDay($dayEntries->first()->day_name); @endphp
                <tr>
                    <th class="day-col">{{ $dayFr }} <span class="arrow">▶</span></th>
                    @foreach($gSlotList as $s)
                        @php $cell = $dayEntries->first(fn($e) => $e->timeslot_id == $s->timeslot_id); @endphp
                        <td class="{{ $cell ? 'session' : 'empty' }}" @if($cell) style="background: {{ $moduleColors[$cell->module] }};" @endif>
                            @if($cell)
                                <div class="mod">{{ $cell->module }}</div>
                                <div class="detail">{{ $cell->professeur }} · {{ $cell->salle }}</div>
                                <div class="duration">{{ durationMinutes($cell->starts_at, $cell->ends_at) }} min</div>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </table>
    @endif

    {{-- Légende des modules --}}
    <div class="legend">
        Modules :
        <table>
            <tr>
                @foreach($groupEntries->unique('module') as $e)
                <td><span class="swatch" style="background: {{ $moduleColors[$e->module] }};"></span>{{ $e->module }}</td>
                @endforeach
            </tr>
        </table>
    </div>

    {{-- Signatures --}}
    <table class="footer">
        <tr>
            <td class="signature" style="padding-top: 6mm; border-top: 1px solid #333;">Chef de filière</td>
            <td class="signature" style="padding-top: 6mm; border-top: 1px solid #333;">Directeur des études</td>
            <td class="signature" style="padding-top: 6mm; border-top: 1px solid #333;">Vice-doyen chargé de la pédagogie</td>
        </tr>
    </table>
@endforeach

</body>
</html>
