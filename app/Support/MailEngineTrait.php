<?php

namespace App\Support;

use App\Models\EmailTemplate;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\HtmlString;
trait MailEngineTrait
{
    protected function getMailMessage($subject, $template, $predefinedConfig, $defaultTemplate = '')
    {
        $mailMessage = new MailMessage();

        $emailTemplate = EmailTemplate::where('template_name', $template)->first();
      
        if(!empty($emailTemplate)) {

            $emailTemplate = $emailTemplate->toArray();

            $subjectTrans = $this->getSubject( $emailTemplate, $predefinedConfig );

            if($subjectTrans) {
                $subject = $subjectTrans;
            }

            $compiledTemplate = $this->getTemplate( $emailTemplate, $predefinedConfig );

            if($compiledTemplate){
                return ($mailMessage)
                ->subject($subject)
                ->line(new HtmlString($compiledTemplate));
            }else{
                return ($mailMessage)
                ->subject($subject)
                ->markdown($defaultTemplate, $predefinedConfig);
            }
            
        }elseif ($defaultTemplate) {

            return ($mailMessage)
            ->subject($subject)
            ->markdown($defaultTemplate, $predefinedConfig);
        }else{
            return;
        }

    }

    protected function getSubject( $emailTemplate, $predefinedConfig ) {

        if($emailTemplate && isset($emailTemplate['subject_' . $predefinedConfig["LANGUAGE"]])) {
            $html = $emailTemplate['subject_' . $predefinedConfig["LANGUAGE"]];

            if (empty(trim($html))) return false;

            $m = new \Mustache_Engine(array('entity_flags' => ENT_QUOTES));

            return $m->render($html, $predefinedConfig);
        }

        return false;
    }

    protected function getTemplate( $emailTemplate, $predefinedConfig ) {

        if($emailTemplate && isset($emailTemplate['content_' . $predefinedConfig["LANGUAGE"]])) 
        {
            $html = $emailTemplate['content_' . $predefinedConfig["LANGUAGE"]];

            if (empty(trim($html))) return false;

            $m = new \Mustache_Engine(array('entity_flags' => ENT_QUOTES));

            return $m->render($html, $predefinedConfig);
        }

        return false;
    }
}
