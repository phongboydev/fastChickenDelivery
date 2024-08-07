<?php

namespace App\GraphQL\Directives;

use Illuminate\Validation\Rule;
use Nuwave\Lighthouse\Schema\Directives\ValidationDirective;

class CreateUserValidationDirective extends ValidationDirective
{
    /**
     * @return mixed[]
     */
    public function rules(): array
    {

        $rule =  ['required', Rule::exists('clients', 'id')];

        if( $this->args['is_internal'] ) {
            $rule = ['sometimes'];
        }

        return [
            'client_id' => $rule,
            'username' => 'username_exists'
        ];
    }
}