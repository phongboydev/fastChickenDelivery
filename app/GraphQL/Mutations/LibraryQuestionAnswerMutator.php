<?php

namespace App\GraphQL\Mutations;

use App\Models\LibraryQuestionAnswer;
use App\Support\Constant;

class LibraryQuestionAnswerMutator
{
    public function getListMenuTab($root, array $args)
    {
       return Constant::MENU_TAB;
    }
}
