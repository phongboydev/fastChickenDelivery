<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\FromView;
use Illuminate\Contracts\View\View;

class ClientEmployeeCustomVariablesExport implements WithTitle, FromView
{
    protected $client;
    protected $employees;
    protected $variables;
    
    function __construct($client, $employees, $variables) {
        $this->client = $client;
        $this->employees = $employees;
        $this->variables = $variables;
    }
    public function view() : View
    {
        $variablesEmployee = [];
        $variablesKeys = array_keys( $this->variables );
        $variablesCols = $this->settingEmpty( array_flip( $variablesKeys ) );
        foreach ($this->employees as $key =>  $employee) {
            $parseVariablesEmployee = [];
            foreach ( $employee->customVariables as $variable) {
                if( ! in_array( $variable->variable_name, $variablesKeys ) || ($variable->scope == 'client') || (substr($variable->variable_name, 0, 2) == 'S_') ) continue;
                $parseVariablesEmployee[ $variable->variable_name ] = $variable->variable_value;
            }

            $variablesEmployee[ $key ]['variables'] = array_merge( $variablesCols , $parseVariablesEmployee);
            $variablesEmployee[ $key ]['code'] = $employee->code;
            $variablesEmployee[ $key ]['full_name'] = $employee->full_name;

        }

        $data = array(
            'companyName' => $this->client->company_name,
            'variables' => $this->variables,
            'variablesEmployee' => $variablesEmployee,
        );
        return view('exports.variable-export-excel')->with($data);
    }
    protected function settingEmpty( $array ) {
        array_walk( $array, function( &$val, $key ) {
            $val = null;
        });
        return $array;
    }

    public function title(): string
    {
        return $this->client->company_name;
    }
}
