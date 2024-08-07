<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Support\FormatHelper;
use App\Models\ClientEmployee;

class FixGenderClientEmployeeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:client_employee_gender {client_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix ClientEmployee gender';

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
     * @return mixed
     */
    public function handle()
    {

      $clientId = $this->argument("client_id") ? $this->argument("client_id") : false;

      $query = ClientEmployee::query();

      if ($clientId) {
        $query->where('client_id', $clientId);
      }

      $query->chunkById(100, function($clientEmployees) {

        foreach($clientEmployees as $clientEmployee) {

          $gender = FormatHelper::gender($clientEmployee->sex);

          if($gender != $clientEmployee->sex) {

            $this->line("Correcting ... $clientEmployee->sex -> $gender " . '[' . $clientEmployee->code . '] ' . $clientEmployee->full_name . ' - ' . $clientEmployee->id);

            $clientEmployee->sex = $gender;
            $clientEmployee->saveQuietly();
          }
        }

      }, 'id');
    }
}
