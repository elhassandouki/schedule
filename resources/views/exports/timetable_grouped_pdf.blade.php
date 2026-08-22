<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<style>
@page{margin:10mm 12mm;size:A4 landscape}*{box-sizing:border-box}body{font-family:DejaVu Sans,sans-serif;font-size:8px;color:#111}.header{width:100%;border-collapse:collapse;margin-bottom:3mm}.header td{border:0}.name{text-align:center;font-size:13px;font-weight:bold;color:#16365F}.contact{text-align:center;color:#555;font-size:7px}.title{text-align:center;font-size:14px;color:#16365F;font-weight:bold;margin:2mm 0 1mm}.subtitle{text-align:center;font-size:10px;color:#16365F;margin-bottom:3mm}.section-title{font-size:11px;font-weight:bold;color:#16365F;margin:4mm 0 1.5mm;border-bottom:1px solid #16365F;padding-bottom:1mm}.grid{width:100%;border-collapse:collapse;table-layout:fixed;page-break-inside:avoid}.grid th,.grid td{border:1px solid #000}.grid th.slot{background:#FFFF00;color:#16365F;font-weight:bold;text-align:center;padding:1.5mm .5mm;font-size:8px}.grid th.day{background:#FFFF66;color:#16365F;font-weight:bold;text-align:center;vertical-align:middle;width:18mm;padding:1mm}.grid th.corner{background:#fff;border:0}.session{text-align:center;vertical-align:middle;padding:1mm .5mm;font-size:7.5px}.session .mod{font-weight:bold}.session .prof,.session .room{color:#333}.inactive{background:#E3E8EF}.note{font-size:7px;margin-top:1.5mm;color:#333}body{page-break-after:auto}
</style>
</head>
<body>
@php
$estab=\App\Models\Setting::allValues();
$logo=$estab['logo_path']?public_path('storage/'.$estab['logo_path']):null;
$estabName=$estab['establishment_name']??'PlanifUni';
$contacts=[];
foreach(['establishment_address','establishment_phone','establishment_email'] as $key){if(!empty($estab[$key]))$contacts[]=$key==='establishment_phone'?'Tél : '.$estab[$key]:$estab[$key];}
function groupedFmtHm(string $hm):string{return substr($hm,0,5);}
@endphp
<table class="header"><tr><td style="text-align:center;padding-right:18mm"><div class="name">{{ $estabName }}</div>@if($contacts)<div class="contact">{{ implode(' · ',$contacts) }}</div>@endif</td>@if($logo&&file_exists($logo))<td style="text-align:right;width:24mm"><img src="{{ $logo }}" style="height:18mm"></td>@endif</tr></table>
<div class="title">État des emplois du temps</div>
<div class="subtitle">{{ $title }} — Année universitaire {{ $academicYearLabel ?? '—' }}</div>
@foreach($sections as $section)
    <div class="section-title">{{ $section['program'] }} — {{ $section['semester']->name }}</div>
    @foreach($section['entries']->groupBy('groupe') as $groupName=>$groupEntries)
        <div style="font-weight:bold;margin:1mm 0">Groupe : {{ $groupName }}</div>
        @php $byDay=$groupEntries->groupBy('day_id'); @endphp
        <table class="grid">
            <colgroup><col style="width:18mm">@foreach($allSlots as $slot)<col>@endforeach</colgroup>
            <tr><th class="corner"></th>@foreach($allSlots as $slot)<th class="slot">{{ groupedFmtHm($slot->starts_at) }} - {{ groupedFmtHm($slot->ends_at) }}</th>@endforeach</tr>
            @foreach($allDays as $day)
                <tr><th class="day">{{ $exportService->translateDay($day->name) }}</th>
                @foreach($allSlots as $slot)
                    @php $cell=($byDay->get($day->id,collect()))->first(fn($entry)=>$entry->timeslot_id==$slot->id); @endphp
                    @if($cell)<td class="session"><div class="mod">{{ $cell->module }}</div><div class="prof">Prof. {{ $cell->professeur }}</div><div class="room">{{ $cell->salle }}</div></td>@else<td class="inactive"></td>@endif
                @endforeach</tr>
            @endforeach
        </table>
    @endforeach
    @if($section['entries']->isEmpty())<div class="note">Aucune session planifiée pour cette filière.</div>@endif
@endforeach
<div class="note" style="margin-top:4mm">Document généré automatiquement le {{ now()->format('d/m/Y H:i') }}.</div>
</body></html>
