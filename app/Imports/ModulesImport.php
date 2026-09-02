<?php

namespace App\Imports;

use App\Models\Module;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ModulesImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;
    public int $updated = 0;
    public array $rowErrors = [];

    public function collection(Collection $rows): void
    {
        $seenCodes = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $data = $this->normaliseRow($row->toArray());
            $errors = $this->validateRow($data);

            $code = strtoupper((string) ($data['code'] ?? ''));
            if ($code !== '' && isset($seenCodes[$code])) {
                $errors[] = "Le code « {$code} » est répété dans le fichier (ligne {$seenCodes[$code]}).";
            } elseif ($code !== '') {
                $seenCodes[$code] = $line;
            }

            $program = $this->resolveProgram($data, $errors);
            $semester = $this->resolveSemester($data, $program, $errors);
            $professors = $this->resolveProfessors($data['professor_emails'] ?? null, $errors);

            if ($errors !== []) {
                $this->rowErrors[] = ['row' => $line, 'messages' => array_values(array_unique($errors))];
                continue;
            }

            $attributes = [
                'program_id' => $program->id,
                'semester_id' => $semester->id,
                'name' => $data['name'],
                'type' => $data['type'] ?: 'cours',
                'weekly_hours' => (int) $data['weekly_hours'],
            ];
            $module = Module::where('code', $code)->first();
            if ($module) {
                $module->update($attributes);
                $this->updated++;
            } else {
                $module = Module::create($attributes + ['code' => $code]);
                $this->created++;
            }

            // La colonne professor_emails est optionnelle : lorsqu'elle est renseignée,
            // elle devient la liste exacte des professeurs autorisés pour ce module.
            if ($data['professor_emails'] !== null) {
                $module->professors()->sync($professors->pluck('id')->all());
            }
        }

        if ($this->rowErrors !== []) {
            throw new ImportRowsException($this->rowErrors);
        }
    }

    private function validateRow(array $data): array
    {
        return Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
            'program_code' => ['nullable', 'string', 'max:100'],
            'program_name' => ['nullable', 'string', 'max:255'],
            'semester_id' => ['nullable', 'integer', 'min:1'],
            'semester_number' => ['nullable', 'integer', 'min:1', 'max:20'],
            'academic_year_name' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'in:cours,td,tp'],
            'weekly_hours' => ['required', 'integer', 'min:0', 'max:168'],
            'professor_emails' => ['nullable', 'string', 'max:4000'],
        ])->errors()->all();
    }

    private function resolveProgram(array $data, array &$errors): ?object
    {
        if (empty($data['program_code']) && empty($data['program_name'])) {
            $errors[] = 'La filière est obligatoire : renseignez program_code ou program_name.';
            return null;
        }
        $query = DB::table('programs');
        if (!empty($data['program_code'])) $query->where('code', $data['program_code']);
        else $query->where('name', $data['program_name']);
        $program = $query->first();
        if (!$program) $errors[] = 'La filière indiquée n’existe pas.';
        return $program;
    }

    private function resolveSemester(array $data, ?object $program, array &$errors): ?object
    {
        if (!$program) return null;
        if (!empty($data['semester_id'])) {
            $semester = DB::table('semesters')->where('id', (int) $data['semester_id'])->where('program_id', $program->id)->first();
            if (!$semester) $errors[] = 'Le semester_id ne correspond pas à la filière indiquée.';
            return $semester;
        }
        if (empty($data['semester_number']) || empty($data['academic_year_name'])) {
            $errors[] = 'Renseignez semester_number et academic_year_name, ou semester_id.';
            return null;
        }
        $semester = DB::table('semesters as s')
            ->join('academic_years as ay', 'ay.id', '=', 's.academic_year_id')
            ->where('s.program_id', $program->id)
            ->where('s.number', (int) $data['semester_number'])
            ->where('ay.name', $data['academic_year_name'])
            ->select('s.*')->first();
        if (!$semester) $errors[] = 'Aucun semestre ne correspond à la filière, au numéro et à l’année universitaire.';
        return $semester;
    }

    private function resolveProfessors(?string $value, array &$errors): Collection
    {
        if ($value === null || trim($value) === '') return collect();
        $emails = array_values(array_unique(array_filter(array_map('trim', preg_split('/[,;|\n]+/', $value)))));
        $professors = DB::table('users')->where('role', 'prof')->whereIn('email', $emails)->get();
        $found = $professors->pluck('email')->all();
        foreach (array_values(array_diff($emails, $found)) as $email) $errors[] = "Le professeur « {$email} » n’existe pas ou n’a pas le rôle prof.";
        return $professors;
    }

    private function normaliseRow(array $row): array
    {
        return [
            'name' => $this->value($row, ['name', 'nom']),
            'code' => strtoupper((string) $this->value($row, ['code', 'code_module'])),
            'program_code' => $this->value($row, ['program_code', 'code_filiere']),
            'program_name' => $this->value($row, ['program_name', 'filiere']),
            'semester_id' => $this->value($row, ['semester_id', 'id_semestre']),
            'semester_number' => $this->value($row, ['semester_number', 'semestre', 'numero_semestre']),
            'academic_year_name' => $this->value($row, ['academic_year_name', 'academic_year', 'annee_universitaire']),
            'type' => strtolower((string) $this->value($row, ['type', 'type_module'])),
            'weekly_hours' => $this->value($row, ['weekly_hours', 'heures_semaine']),
            'professor_emails' => $this->value($row, ['professor_emails', 'emails_professeurs', 'professeurs']),
        ];
    }

    private function value(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) return is_string($row[$key]) ? trim($row[$key]) : $row[$key];
        }
        return null;
    }
}
