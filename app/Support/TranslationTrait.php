<?php

namespace App\Support;

use App\Models\Translation;

trait TranslationTrait
{
    protected function trans($languageId, $default, $prefered_language = 'en') {

        $trans = Translation::select('*')
                            ->where('translatable_id', $languageId)
                            ->where('language_id', $prefered_language)->first();

        if($trans && $trans->translation) {

            return $trans->translation;
        }

        return $default;
    }
}
