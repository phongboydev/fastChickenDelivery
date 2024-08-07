<?php

namespace App\Console\Commands;

use App\Models\Client;
use DB;
use Illuminate\Console\Command;

class TidyCompanyNameCommand extends Command
{

    protected $signature = 'tidy:companyName';

    protected $description = 'Fix company name langauge';

    public function handle()
    {
        $clients = Client::all();
        foreach ($clients as $client) {
            $translations = $client->getTranslations('company_name');
            if (count($translations) == 0) {
                $companyName = DB::table('clients')->where('id', $client->id)->first()->company_name;
                $client
                    ->setTranslation('company_name', 'en', $companyName)
                    ->setTranslation('company_name', 'vi', $companyName)
                    ->setTranslation('company_name', 'ja', $companyName)
                    ->update();
            } else {
                $companyName = '';
                foreach ($translations as $trans) {
                    if ($trans && $trans != '') {
                        $companyName = $trans;
                    }
                }
                $locales = ['en', 'vi', 'ja'];
                foreach ($locales as $locale) {
                    if (!isset($translations[$locale])) {
                        $client->setTranslation('company_name', $locale, $companyName)->update();
                    }
                }
            }
        }
    }
}
