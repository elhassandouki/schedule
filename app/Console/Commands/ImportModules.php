<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportModules extends Command
{
    protected $signature = 'timetable:import-modules {file : TSV file: programme code, S1..S6, module code, module name} {--hours=2 : Default weekly hours}';
    protected $description = 'Import programme modules and link them to their active-year semesters.';

    public function handle(): int
    {
        $path = $this->argument('file');
        if (!is_file($path) || !is_readable($path)) {
            $this->error("Cannot read file: {$path}");
            return self::FAILURE;
        }

        $rows = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $lineNumber => $line) {
            $columns = array_map('trim', explode("\t", $line, 4));
            if (count($columns) !== 4 || !preg_match('/^S([1-6])$/', $columns[1], $match)) {
                $this->error('Invalid row at line '.($lineNumber + 1).'. Expected: PROGRAM<TAB>S1<TAB>CODE<TAB>NAME');
                return self::FAILURE;
            }
            [$programCode, , $code, $name] = $columns;
            if ($programCode === '' || $code === '' || $name === '') {
                $this->error('Empty required value at line '.($lineNumber + 1).'.');
                return self::FAILURE;
            }
            $rows[] = compact('programCode', 'code', 'name') + ['semesterNumber' => (int) $match[1]];
        }

        $academicYearId = DB::table('academic_years')->where('is_active', true)->value('id');
        if (!$academicYearId) {
            $this->error('No active academic year found. Create or activate one before importing modules.');
            return self::FAILURE;
        }
        $programs = DB::table('programs')->pluck('id', 'code');
        $semesters = DB::table('semesters')->where('academic_year_id', $academicYearId)->get()
            ->keyBy(fn ($semester) => $semester->program_id.'-'.$semester->number);

        foreach ($rows as $row) {
            if (!$programs->has($row['programCode']) || !$semesters->has($programs[$row['programCode']].'-'.$row['semesterNumber'])) {
                $this->error("Missing programme or S{$row['semesterNumber']} for {$row['programCode']}; no rows were imported.");
                return self::FAILURE;
            }
        }

        DB::transaction(function () use ($rows, $programs, $semesters) {
            foreach ($rows as $row) {
                $programId = $programs[$row['programCode']];
                $semesterId = $semesters[$programId.'-'.$row['semesterNumber']]->id;
                DB::table('modules')->updateOrInsert(['code' => $row['code']], [
                    'program_id' => $programId,
                    'semester_id' => $semesterId,
                    'name' => $row['name'],
                    'weekly_hours' => (int) $this->option('hours'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $this->info(count($rows).' module(s) imported or updated.');
        return self::SUCCESS;
    }
}
