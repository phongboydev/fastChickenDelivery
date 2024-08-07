<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\ClientDepartment;
use App\Models\ClientEmployee;
use App\Support\ConvertHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use JpnForPhp\Transliterator\Transliterator;
use JpnForPhp\Transliterator\System\Hepburn;
use JpnForPhp\Analyzer\Analyzer;

class DepartmentalSynchronization extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:departmentalSynchronization';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Departmental Synchronization';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if ($this->confirm('Are you sure you want to perform departmental sync again, after pressing [yes] the data may not be restored, please consider!!!')) {
            try {
                $client_employees = ClientEmployee::withTrashed()->select('id', 'full_name', 'department', 'client_id')
                    ->whereNotNull('department')
                    ->where('department', '!=', '')
                    ->orderBy('department')
                    ->get();

                DB::beginTransaction();

                foreach ($client_employees as $client_employee) {

                    // Check if the department already exists in the client
                    $client_department = ClientDepartment::where(['department' => $client_employee->department, 'client_id' => $client_employee->client_id])->first();

                    if ($client_department !== NULL) {
                        // Update to client employee
                        ClientEmployee::withTrashed()->where('id', $client_employee->id)->update(['client_department_id' => $client_department->id]);
                    } else {
                        // Convert to Latin letters
                        $name = ConvertHelper::charsetConversion($client_employee->department);

                        if (str_word_count($name) > 1) {
                            preg_match_all('/(?<=\s|^)\w/iu', $name, $matches);
                            $department_code = $client_employee->client_code . '_' . strtoupper(substr(implode('', $matches[0]), 0, 3));
                        } else {
                            $department_code = $client_employee->client_code . '_' . strtoupper(substr($name, 0, 3));
                        }

                        $this->line($department_code);
                        $this->newLine();

                        $client_department_new = ClientDepartment::create([
                            'department' => $client_employee->department,
                            'client_id' => $client_employee->client_id,
                            'code' => $department_code
                        ]);

                        // Update to client employee
                        ClientEmployee::withTrashed()->where('id', $client_employee->id)->update(['client_department_id' => $client_department_new->id]);
                    }
                }
                DB::commit();
                $this->info('The command was successful!');
            } catch (\Throwable $th) {
                $this->error($th->getMessage());
                DB::rollback();
            }
        } else {

            $this->error('Exit.');
        }
    }
}
