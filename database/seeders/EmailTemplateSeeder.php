<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        EmailTemplate::updateOrCreate(
            ['key' => 'weekly_report'],
            [
                'name' => 'Reporte semanal para padres',
                'subject_template' => 'Reporte Semana {{week_name}} - {{student_name}}',
                'body_template' =>
                    '<p>Hola,</p>
                     <p>Te compartimos el reporte de {{student_name}} correspondiente a <strong>{{week_name}}</strong> ({{week_range}}).</p>
                     <p><a href="{{link}}" target="_blank">Ver reporte</a></p>
                     <p>Saludos,<br>{{school_name}}</p>',
                'is_html' => true,
                'is_active' => true,
            ]
        );
    }
}
