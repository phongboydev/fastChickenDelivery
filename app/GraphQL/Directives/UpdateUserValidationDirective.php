<?php

namespace App\GraphQL\Directives;

use Illuminate\Validation\Rule;
use Nuwave\Lighthouse\Schema\Directives\ValidationDirective;

class UpdateUserValidationDirective extends ValidationDirective
{
    /**
     * @return mixed[]
     */
    public function rules(): array
    {
        return [
            'id' => [
                'exists:users,id'
            ],
            'username' => [
                'required',
                'username_exists',
            ],
            'email' => [
                'required',
                'email',
            ]
        ];
    }
}