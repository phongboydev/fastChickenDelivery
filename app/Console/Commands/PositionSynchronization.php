<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\ClientPosition;
use App\Models\ClientEmployee;
use App\Support\ConvertHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use JpnForPhp\Transliterator\Transliterator;
use JpnForPhp\Transliterator\System\Hepburn;
use JpnForPhp\Analyzer\Analyzer;

class PositionSynchronization extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:positionSynchronization';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Position Synchronization';

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
        if ($this->confirm('Are you sure you want to perform position sync again, after pressing [yes] the data may not be restored, please consider!!!')) {
            try {
                $client_employees = ClientEmployee::withTrashed()->select('id', 'full_name', 'position', 'client_id')
                    ->whereNotNull('position')
                    ->where('position', '!=', '')
                    ->orderBy('position')
                    ->get();

                DB::beginTransaction();

                foreach ($client_employees as $client_employee) {

                    // Check if the position already exists in the client
                    $client_position = ClientPosition::where(['name' => $client_employee->position, 'client_id' => $client_employee->client_id])->first();

                    if ($client_position !== NULL) {
                        // Update to client employee
                        ClientEmployee::withTrashed()->where('id', $client_employee->id)->update(['client_position_id' => $client_position->id]);
                    } else {
                        // Convert to Latin letters
                        $name = ConvertHelper::charsetConversion($client_employee->position);

                        if (str_word_count($name) > 1) {
                            preg_match_all('/(?<=\s|^)\w/iu', $name, $matches);
                            $position_code = $client_employee->client_code . '_' . strtoupper(substr(implode('', $matches[0]), 0, 3));
                        } else {
                            $position_code = $client_employee->client_code . '_' . strtoupper(substr($name, 0, 3));
                        }
                        $client_position_new = ClientPosition::where(['code' => $position_code, 'client_id' => $client_employee->client_id])->first();
                        
                        $this->line($position_code);
                        $this->newLine();
                        if(!$client_position_new){							
                            $client_position_new = ClientPosition::create([
                                'name' => $client_employee->position,
                                'client_id' => $client_employee->client_id,
                                'code' => $position_code
                            ]);
                        }                        

                        // Update to client employee
                        ClientEmployee::withTrashed()->where('id', $client_employee->id)->update(['client_position_id' => $client_position_new->id]);
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
