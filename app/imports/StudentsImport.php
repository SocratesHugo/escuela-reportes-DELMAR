<?php

namespace App\Imports;

use App\Models\Student;
use App\Models\Group;
use App\Models\SchoolYear;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\Importable;

class StudentsImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure, WithBatchInserts, WithChunkReading
{
    use Importable, SkipsFailures;

    protected $user;
    public function __construct($user = null) { $this->user = $user; }

    public function model(array $row)
    {
        $groupName = strtoupper(trim($row['group'] ?? ''));
        $grade     = $row['grade'] ?? null;
        $email     = $row['email'] ?? null;
        $syLabel   = trim($row['school_year'] ?? '');

        if (!$grade && preg_match('/^([7-9])/', $groupName, $m)) {
            $grade = (int) $m[1];
        }

        $schoolYearId = $this->resolveSchoolYearId($syLabel);

        $groupId = null;
        if ($groupName !== '') {
            $group = Group::firstOrCreate(
                ['name' => $groupName],
                ['grade' => $grade, 'school_year_id' => $schoolYearId, 'active' => true]
            );
            if ($group && !$group->grade && $grade) {
                $group->update(['grade' => $grade]);
            }
            $groupId = $group->id;
        }

        $existing = Student::query()
            ->when(!empty($email), fn($q) => $q->where('email', mb_strtolower(trim($email))))
            ->when(empty($email), function ($q) use ($row, $groupId, $schoolYearId) {
                $q->where('names', trim($row['names'] ?? ''))
                  ->where('paternal_lastname', trim($row['paternal_lastname'] ?? ''))
                  ->where('maternal_lastname', trim($row['maternal_lastname'] ?? ''))
                  ->when($groupId, fn($q2) => $q2->where('group_id', $groupId))
                  ->when($schoolYearId, fn($q3) => $q3->where('school_year_id', $schoolYearId));
            })
            ->first();

        if ($existing) {
            $existing->update([
                'email'          => $email ? mb_strtolower(trim($email)) : $existing->email,
                'group_id'       => $groupId ?: $existing->group_id,
                'school_year_id' => $schoolYearId ?: $existing->school_year_id,
                'active'         => true,
            ]);
            return null;
        }

        return new Student([
            'paternal_lastname' => trim($row['paternal_lastname'] ?? ''),
            'maternal_lastname' => trim($row['maternal_lastname'] ?? ''),
            'names'             => trim($row['names'] ?? ''),
            'email'             => $email ? mb_strtolower(trim($email)) : null,
            'school_year_id'    => $schoolYearId,
            'group_id'          => $groupId,
            'active'            => true,
        ]);
    }

    public function rules(): array
    {
        return [
            '*.names'             => ['required','string','max:120'],
            '*.paternal_lastname' => ['required','string','max:120'],
            '*.maternal_lastname' => ['nullable','string','max:120'],
            '*.email'             => ['nullable','email','max:190'],
            '*.group'             => ['required','regex:/^(7|8|9)[AB]$/i'],
            '*.grade'             => ['nullable','integer','in:7,8,9'],
            '*.school_year'       => ['required','string','max:30'],
        ];
    }

    public function batchSize(): int { return 500; }
    public function chunkSize(): int { return 500; }

    protected function resolveSchoolYearId(?string $label): ?int
    {
        if (!$label) return null;
        $sy = SchoolYear::where('name', $label)->first();
        return $sy?->id;
    }
}
