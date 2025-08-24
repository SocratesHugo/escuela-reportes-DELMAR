<?php

namespace App\Jobs;

use App\Mail\WeeklyReportsConsolidatedMail;
use App\Models\Student;
use App\Models\User;
use App\Models\Week;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection as ECollection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class SendWeeklyReportsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $weekId,
        public bool $consolidateByParent = true,
        public int $expiresInDays = 7,
        public bool $alsoSendToStudents = false,
    ) {}

    public function handle(): void
    {
        $week = Week::findOrFail($this->weekId);

        // Alumnos con padres (con email)
        $students = Student::with(['parents' => fn($q) => $q->whereNotNull('email')])
            ->whereHas('parents', fn($q) => $q->whereNotNull('email'))
            ->get();

        if ($this->consolidateByParent) {
            // Agrupar por padre => 1 correo con todos sus hijos
            $byParent = [];
            foreach ($students as $student) {
                foreach ($student->parents as $parent) {
                    $byParent[$parent->id]['parent']     = $parent;
                    $byParent[$parent->id]['children'][] = $student;
                }
            }

            foreach ($byParent as $bucket) {
                /** @var User $parent */
                $parent   = $bucket['parent'];
                $children = collect($bucket['children']);

                $links = $children->map(function (Student $s) use ($parent, $week) {
                    $signed = URL::signedRoute('public.report.show', [
                        'parent'  => $parent->id,
                        'student' => $s->id,
                        'week'    => $week->id,
                    ], now()->addDays($this->expiresInDays));

                    return ['student' => $s, 'url' => $signed];
                })->values()->all();

                if ($parent->email) {
                    Mail::to($parent->email)->send(
                        new WeeklyReportsConsolidatedMail($parent, $week, $links)
                    );
                }

                if ($this->alsoSendToStudents) {
                    foreach ($children as $child) {
                        if ($child->email) {
                            $url = URL::signedRoute('public.report.show', [
                                'parent'  => 0, // sin botón firma para alumnos
                                'student' => $child->id,
                                'week'    => $week->id,
                            ], now()->addDays($this->expiresInDays));

                            Mail::to($child->email)->send(
                                new WeeklyReportsConsolidatedMail(null, $week, [
                                    ['student' => $child, 'url' => $url],
                                ], forStudent:true)
                            );
                        }
                    }
                }
            }
        } else {
            // 1 correo por hijo
            foreach ($students as $student) {
                foreach ($student->parents as $parent) {
                    $url = URL::signedRoute('public.report.show', [
                        'parent'  => $parent->id,
                        'student' => $student->id,
                        'week'    => $week->id,
                    ], now()->addDays($this->expiresInDays));

                    if ($parent->email) {
                        Mail::to($parent->email)->send(
                            new WeeklyReportsConsolidatedMail($parent, $week, [
                                ['student' => $student, 'url' => $url],
                            ])
                        );
                    }
                }

                if ($this->alsoSendToStudents && $student->email) {
                    $url = URL::signedRoute('public.report.show', [
                        'parent'  => 0,
                        'student' => $student->id,
                        'week'    => $week->id,
                    ], now()->addDays($this->expiresInDays));

                    Mail::to($student->email)->send(
                        new WeeklyReportsConsolidatedMail(null, $week, [
                            ['student' => $student, 'url' => $url],
                        ], forStudent:true)
                    );
                }
            }
        }
    }
}
