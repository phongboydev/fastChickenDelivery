<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class GenerateInternalRole extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'setup:role';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        $roles = [
            'director' => [
                'manage_iglocal_user',
                'manage_assignement',
                'manage_clients',
                'manage_calculation',
                'manage_export_template'
            ],
            'leader' => [
                'manage_iglocal_user',
                'manage_assignement',
                'manage_clients'
            ]
        ];

        foreach ($roles as $roleName => $permissions) {

            $role = Role::where('name', $roleName)->first();

            if(empty($role))
                $role = Role::create(['name' => $roleName]);

            foreach($permissions as $permission) {
                /** @noinspection Annotator */
                $hasPermission = Permission::getPermissions(['name' => $permission, 'guard_name' => 'api'])->first();

                    if( !$hasPermission )
                        Permission::create(['name' => $permission]);
            }

            $role->syncPermissions($permissions);
        }
    }
}
