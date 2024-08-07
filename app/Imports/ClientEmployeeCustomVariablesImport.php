<?php

namespace App\Imports;

use App\Models\Client;
use App\Models\ClientCustomVariable;
use App\Models\ClientEmployee;
use App\Models\ClientEmployeeCustomVariable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Maatwebsite\Excel\Concerns\ToCollection;
use App\Exceptions\HumanErrorException;

class ClientEmployeeCustomVariablesImport implements ToCollection
{

    /** @var Client */
    protected $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function collection(Collection $rows)
    {
        $rowMap = [];
        $variableRow = $rows->get(2);
        for ($i = 2; $variableRow->has($i); $i++) {
            $rowMap[$i] = $variableRow->get($i);
        }

        $clientVariables = ClientCustomVariable::where('client_id', $this->client->id)
            ->get()
            ->keyBy('variable_name');

        logger('clientVariables', [$clientVariables]);

        DB::beginTransaction();

        try {
            $rowIndex = 4;
            while ($rows->has($rowIndex)) {
                if (!$rows->get($rowIndex)->get(0)) {
                    break;
                }

                $row  = $rows->get($rowIndex);

                // increase rowIndex after get
                $rowIndex++;

                $code = $row->get(0);
                $employee = ClientEmployee::query()
                    ->where('client_id', $this->client->id)
                    ->where('code', $code)
                    ->first();

                if (!$employee) {
                    logger("ClientEmployeeCustomVariablesImport@collection Employee $code not found");
                    throw new InvalidArgumentException("Employee $code is not existed");
                }
                logger("ClientEmployeeCustomVariablesImport@collection employee", ['employee' => $employee->id]);

                for ($i = 1; $row->has($i); $i++) {
                    if (!isset($rowMap[$i])) {
                        continue;
                    }
                    $name  = $rowMap[$i];
                    $value = $row->get($i);

                    if (!is_numeric($value)) {
                        throw new InvalidArgumentException("$name is not a numeric value");
                    }

                    if ($clientVariables->has($name)) {
                        $clientVariable = $clientVariables->get($name);

                        if ( $clientVariable->scope == 'employee' ) 
                        {
                            $variable = ClientEmployeeCustomVariable::query()
                                ->where('client_employee_id', $employee->id)
                                ->where('variable_name', $clientVariable->variable_name)
                                ->first();
                            if (!$variable) {
                                $variable = new ClientEmployeeCustomVariable([
                                    'client_employee_id' => $employee->id,
                                    'variable_name' => $clientVariable->variable_name
                                ]);
                            }

                            $variable->readable_name  = $clientVariable->readable_name;
                            $variable->variable_value = $value;
                            $variable->save();
                        } else {
                            throw new HumanErrorException(__("Không thể cập nhật biến hệ thống: " . $name));
                        }
                    } else {
                        throw new HumanErrorException(__("Biến này chưa được định nghĩa " . $name));
                    }
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            logger("LOOOOOOOOOOOOOOOOOOI ROI");
            DB::rollBack();
            throw $e;
        }
    }
}
