<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 10mm 12mm; size: A4 portrait; }
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; }

    .NAVY { color: #16365F; }
    b.navy { color: #16365F; }

    /* ---------- En-tête institutionnel ---------- */
    table.estab-header { width: 100%; border-collapse: collapse; margin-bottom: 2mm; }
    table.estab-header td { border: none !important; }
    td.estab-center { text-align: center; }
    .estab-logo img { height: 22mm; }
    .estab-name { font-size: 13px; font-weight: bold; color: #16365F; }
    .estab-contact { color: #555; font-size: 8px; }

    /* ---------- Titres ---------- */
    h1.doc-title { text-align: center; font-size: 13.5px; color: #16365F; margin: 1mm 0 0.5mm; }
    h1.doc-title .red { color: #c0392b; }
    .doc-year { text-align: center; font-size: 12.5px; color: #16365F; font-weight: bold; margin-bottom: 1.5mm; }
    .doc-filiere { text-align: center; font-size: 12px; color: #16365F; font-weight: bold; margin-bottom: 2mm; }

    .semester-line { width: 100%; margin-bottom: 1.5mm; }
    .semester-line td { border: none !important; }
    .semester-line .sem { font-weight: bold; font-size: 10px; color: #111; }
    .semester-line .grp { font-weight: bold; font-size: 10px; color: #111; text-align: right; }

    /* ---------- Tableau emploi du temps ---------- */
    table.grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.grid th, table.grid td { border: 1px solid #000; }

    th.slot { background: #FFFF00; color: #16365F; font-weight: bold; font-size: 9px; text-align: center; padding: 1.8mm 1mm; }
    th.day { background: #FFFF66; color: #16365F; font-weight: bold; font-size: 10.5px; text-align: center; vertical-align: middle; width: 16mm; padding: 1.5mm; }
    th.corner { background: #ffffff; border: none !important; }

    td.session { background: #ffffff; text-align: center; vertical-align: middle; padding: 1.5mm 1mm; font-size: 8.5px; }
    td.session .mod { font-weight: bold; color: #111; }
    td.session .prof { color: #333; }
    td.session .room { color: #333; }
    td.inactive { background: #E3E8EF; border: 1px solid #000; }
    td.merged { background: #ffffff; text-align: center; vertical-align: middle; font-style: italic; font-size: 9px; padding: 1.5mm 1mm; }

    /* ---------- Bas de page ---------- */
    .nb-line { margin-top: 2mm; font-size: 8.5px; }
    .nb-line b { color: #16365F; }
    .date-line { margin-top: 3mm; font-size: 9px; }
</style>
</head>
<body>
@php
    $estab = \App\Models\Setting::allValues();
    $estabLogo = $estab['logo_path'] ? public_path('storage/' . $estab['logo_path']) : null;
    $estabName = $estab['establishment_name'] ?? 'PlanifUni';
    $hasContacts = $estab['establishment_address'] || $estab['establishment_phone'] || $estab['establishment_email'];
    $contacts = [];
    if ($estab['establishment_address']) $contacts[] = $estab['establishment_address'];
    if ($estab['establishment_phone']) $contacts[] = 'Tél : ' . $estab['establishment_phone'];
    if ($estab['establishment_email']) $contacts[] = $estab['establishment_email'];

    function slotMinutes(string $starts, string $ends): int
    {
        return (int) round((strtotime($ends) - strtotime($starts)) / 60);
    }
    function fmtHm(string $hm): string
    {
        return str_replace(':30', 'h30', str_replace(':45', 'h45', str_replace(':15', 'h15', substr($hm, 0, 5))));
    }
@endphp

{{-- En-tête : logo à droite (style référence) + nom établissement centré --}}
<table class="estab-header">
    <tr>
        <td class="estab-center" style="padding-right: 22mm;">
            <div class="estab-name">{{ $estabName }}</div>
            @if($hasContacts)
            <div class="estab-contact">{{ implode(' &nbsp;·&nbsp; ', $contacts) }}</div>
            @endif
        </td>
        @if($estabLogo && file_exists($estabLogo))
        <td style="text-align: right; width: 24mm;"><img src="{{ $estabLogo }}" style="height: 20mm;"></td>
        @endif
    </tr>
</table>

<h1 class="doc-title">Emploi du temps <span class="red">provisoire</span> session de {{ strtolower($semester->name) }}</h1>
<div class="doc-year">Année universitaire {{ $academicYear }}</div>
<div class="doc-filiere">{{ $program }}</div>

@foreach ($entries->groupBy('groupe') as $groupName => $groupEntries)
    <table class="semester-line">
        <tr>
            <td class="sem" style="width: 50%;">{{ $semester->name }}</td>
            <td class="grp">{{ $groupName }}</td>
        </tr>
    </table>

    @php
        $gSlotList = $groupEntries->unique('timeslot_id')->sortBy(function ($e) { return strtotime($e->starts_at); })->values();
        $gDays = $groupEntries->groupBy('day_id')->sortKeys();
        $slotsByHour = $gSlotList->keyBy('timeslot_id');
    @endphp

    <table class="grid">
        <colgroup>
            <col style="width: 16mm;">
            @foreach($gSlotList as $s)<col>@endforeach
        </colgroup>
        <tr>
            <th class="corner"></th>
            @foreach($gSlotList as $s)
                <th class="slot">{{ fmtHm($s->starts_at) }} - {{ fmtHm($s->ends_at) }}</th>
            @endforeach
        </tr>
        @foreach($gDays as $dayId => $dayEntries)
            @php $dayFr = $exportService->translateDay($dayEntries->first()->day_name); @endphp
            <tr>
                <th class="day">{{ $dayFr }}</th>
                @foreach($gSlotList as $s)
                    @php $cell = $dayEntries->first(fn($e) => $e->timeslot_id == $s->timeslot_id); @endphp
                    @if($cell)
                        <td class="session">
                            <div class="mod">{{ $cell->module }}</div>
                            <div class="prof">Prof. {{ $cell->professeur }}</div>
                            <div class="room">{{ $cell->salle }}</div>
                        </td>
                    @else
                        <td class="inactive"></td>
                    @endif
                @endforeach
            </tr>
        @endforeach
    </table>

    <div class="nb-line"><b>NB</b> : Emploi du temps généré automatiquement par le système — les séances marquées en gris correspondent aux créneaux inactifs.</div>
@endforeach

<div class="date-line">Date : {{ now()->format('d/m/Y') }}</div>

</body>
</html>
