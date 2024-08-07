<?php

namespace App\Console\Commands;

use App\Models\ApproveFlow;
use App\Models\ApproveFlowUser;
use App\Models\Client;
use App\User;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateKeyForAdvancedPermissionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'createKeyForAdvancedPermission:trigger {keyModule} {keyTab} {action}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create key for advanced permission';

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
        $keyModule = $this->argument("keyModule");
        $keyTabAdvancedPermission = $this->argument("keyTab");
        $action = $this->argument("action");
        if (!$keyTabAdvancedPermission || !$action) {
            return 'lack of param';
        }
        $listAction = explode(',', $action);
        if (empty($listAction)) {
            return 'action not type param';
        }
        $arrayInsertApproveFlow = [];
        $arrayInsertApproveFlowUser = [];
        $keyMain = $keyModule . '-' . $keyTabAdvancedPermission;
        // Action for level 1
        $listKeyInsert[$keyMain] = 1;

        // Action for level 2
        foreach ($listAction as $item) {
            $listKeyInsert[$keyMain . '-' . $item] = 2;
        }

        try {
            Client::query()
                ->whereHas('approveFlow', function ($subQuery) use ($keyModule) {
                    $subQuery->where('flow_name', $keyModule);
                })
                ->with(['approveFlow'])
                ->chunkById(100, function ($items) use (&$arrayInsertApproveFlow, &$arrayInsertApproveFlowUser, $listKeyInsert, $keyTabAdvancedPermission, $keyModule, $keyMain) {
                    foreach ($items as $item) {
                        $insertApproveFlow = [];
                        $groupIsAddedApproveFlow = array_unique(ApproveFlow::where(['flow_name' => $keyModule, 'client_id' => $item->id])->get()->pluck('group_id')->toArray());
                        foreach ($groupIsAddedApproveFlow as $itemGroup) {
                            foreach ($listKeyInsert as $key => $value) {
                                $insertApproveFlow['id'] = Str::uuid();
                                $insertApproveFlow['client_id'] = $item->id;
                                $insertApproveFlow['flow_name'] = $key;
                                $insertApproveFlow['level'] = $value;
                                $insertApproveFlow['group_id'] = $itemGroup;
                                $insertApproveFlow['created_at'] = now();
                                $insertApproveFlow['updated_at'] = now();
                                $arrayInsertApproveFlow[] = $insertApproveFlow;

                                $approveFlow = ApproveFlow::where(['flow_name' => $keyModule, 'group_id' => $itemGroup, 'client_id' => $item->id])
                                    ->whereHas('approveFlowUsers', function ($query) {
                                        $query->whereNull('parent_id');
                                    })
                                    ->with('approveFlowUsers')
                                    ->first();

                                if ($approveFlow) {
                                    $user = User::find($approveFlow->approveFlowUsers[0]->user_id);
                                    // Insert Approve flow user
                                    if ($key == $keyMain || str_contains($key, 'read')) {
                                        $insertApproveFlowUser['approve_flow_id'] = $insertApproveFlow['id'];
                                        $insertApproveFlowUser['id'] = Str::uuid();
                                        $insertApproveFlowUser['user_id'] = $approveFlow->approveFlowUsers[0]->user_id;
                                        $insertApproveFlowUser['group_id'] = $itemGroup;
                                        $insertApproveFlowUser['created_at'] = now();
                                        $insertApproveFlowUser['updated_at'] = now();
                                        $arrayInsertApproveFlowUser[] = $insertApproveFlowUser;
                                        $user->forceAdvanceGivePermissionTo(false, $key, $itemGroup);
                                    }
                                }
                            }
                        }
                    }
                });
            // Insert key
            ApproveFlow::insert($arrayInsertApproveFlow);
            // Insert key
            ApproveFlowUser::insert($arrayInsertApproveFlowUser);
            $this->line('create kay successful');
        } catch (Exception $e) {
            $this->line($e->getMessage() . 'at line ' . $e->getLine());
        }

        return false;
    }
}
