<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 12mm 8mm; size: A4 landscape; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; }
    h1 { font-size: 15px; margin: 0 0 2px; }
    .meta { color: #555; font-size: 9.5px; margin-bottom: 10px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    th { background: #2c3e80; color: #fff; padding: 4px 6px; text-align: left; font-size: 9.5px; }
    td { padding: 3px 6px; border-bottom: 1px solid #ddd; font-size: 9.5px; }
    tr:nth-child(even) td { background: #f4f6fb; }
    .group-title { font-size: 11px; font-weight: bold; color: #2c3e80; margin: 8px 0 3px; border-bottom: 1px solid #ccc; padding-bottom: 2px; }
    .footer { margin-top: 6px; color: #777; font-size: 8.5px; }
    .estab-header { display: table; width: 100%; border-bottom: 2px solid #2c3e80; margin-bottom: 6px; padding-bottom: 4px; }
    .estab-logo { display: table-cell; width: 60px; vertical-align: middle; }
    .estab-info { display: table-cell; vertical-align: middle; padding-left: 10px; }
    .estab-name { font-size: 12px; font-weight: bold; color: #2c3e80; }
    .estab-contact { color: #666; font-size: 9px; }
</style>
</head>
<body>
    @php
        $estab = \App\Models\Setting::allValues();
        $estabLogo = $estab['logo_path'] ? public_path('storage/' . $estab['logo_path']) : null;
    @endphp
    <div class="estab-header">
        @if($estabLogo && file_exists($estabLogo))
            <img src="{{ $estabLogo }}" class="estab-logo" height="55">
        @endif
        <div class="estab-info">
            <div class="estab-name">{{ $estab['establishment_name'] }}</div>
            <div class="estab-contact">
                @if($estab['establishment_address']){{ $estab['establishment_address'] }}@endif
                @if($estab['establishment_phone']) &nbsp;·&nbsp; Tél : {{ $estab['establishment_phone'] }}@endif
                @if($estab['establishment_email']) &nbsp;·&nbsp; {{ $estab['establishment_email'] }}@endif
            </div>
        </div>
    </div>
    <h1>Emploi du temps — {{ $program }}</h1>
    <div class="meta">
        {{ $semester->name }} &nbsp;·&nbsp; Année universitaire {{ $academicYear }} &nbsp;·&nbsp;
        {{ count($entries) }} séance(s) planifiée(s) · Exporté le {{ $generatedAt }}
    </div>

    @foreach ($entries->groupBy('groupe') as $groupName => $groupEntries)
        <div class="group-title">{{ $groupName }} ({{ count($groupEntries) }} séances)</div>
        <table>
            <thead>
                <tr>
                    <th>Jour</th>
                    <th>Créneau</th>
                    <th>Module</th>
                    <th>Enseignant</th>
                    <th>Salle</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($groupEntries as $entry)
                    <tr>
                        <td>{{ $exportService->translateDay($entry->day_name) }}</td>
                        <td>{{ $entry->timeslot_name }}</td>
                        <td>{{ $entry->module }}</td>
                        <td>{{ $entry->professeur }}</td>
                        <td>{{ $entry->salle }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    <div class="footer">
        Généré par PlanifUni — {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
