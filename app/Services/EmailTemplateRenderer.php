<?php

namespace App\Services;

use App\Models\EmailSetting;

class EmailTemplateRenderer
{
    /**
     * Renderiza subject y body sustituyendo variables {{clave}} por su valor.
     *
     * @param  EmailSetting  $tpl   Instancia con subject_template y body_template
     * @param  array<string,string> $vars  e.g. ['week_name'=>'Semana 1', 'parent_name'=>'Juan']
     * @return array{subject:string, body:string}
     */
    public static function render(EmailSetting $tpl, array $vars): array
    {
        $subject = (string) ($tpl->subject_template ?? '');
        $body    = (string) ($tpl->body_template ?? '');

        foreach ($vars as $k => $v) {
            $needle = '{{' . $k . '}}';
            $subject = str_replace($needle, (string) $v, $subject);
            $body    = str_replace($needle, (string) $v, $body);
        }

        return [
            'subject' => $subject,
            'body'    => $body,
        ];
    }
}
