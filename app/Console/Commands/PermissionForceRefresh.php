<?php

namespace App\Console\Commands;

use App\Support\Constant;
use App\User;
use Illuminate\Console\Command;

class PermissionForceRefresh extends Command
{
    protected $signature = 'permission:force-refresh {--id=* : The ID of the user}';
    protected $description = 'Forced to refresh all permission the user';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        try {
            if (!empty($this->option("id"))) {
                $userIds = $this->option("id");
                $users = User::whereIn('id', $userIds)->get();
            } else {
                $users = User::whereHas('clientEmployee', function ($subQuery) {
                    $subQuery->where('client_employees.status', Constant::CLIENT_EMPLOYEE_STATUS_WORKING)
                        ->whereNull('client_employees.deleted_at');
                    $subQuery->orWhere(function ($subQueryLevelTwo) {
                        $subQueryLevelTwo->where('client_employees.status', Constant::CLIENT_EMPLOYEE_STATUS_QUIT)
                            ->where('client_employees.quitted_at', '>', now()->format('Y-m-d H:i:s'))
                            ->whereNull('client_employees.deleted_at');
                    });
                })->get();
            }

            $users->each(function ($user) {
                $user->refreshPermissions();
                $this->info("Refresh permission: " . $user->name . " - " . $user->client_id . " succeed");
            });
        } catch (\Throwable $th) {
            $this->error($th->getMessage());
        }
    }
}
