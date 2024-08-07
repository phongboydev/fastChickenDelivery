<?php

namespace App\Observers;

use App\Models\Approve;
use App\Models\AssignmentProject;
use App\Models\Client;
use App\Models\ClientEmployee;
use App\Models\ClientWorkflowSetting;
use App\Models\IglocalAssignment;
use App\Models\IglocalEmployee;
use App\Models\WorkScheduleGroupTemplate;

use App\Models\Province;
use App\Models\ProvinceDistrict;
use App\Models\ProvinceWard;

use App\Support\Constant;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ClientObserver
{

    public function creating(Client $client)
    {
        // Get User from token
        /** @var User $user */
        $user = Auth::user();

        // If user is director, set is_active = true
        // no request approve is needed
        if ($user) {
            $role = $user->getRole();

            if($role == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                $client->is_active = true;
            }else{
                $client->is_active = false;
            }
        }
    }

    /**
     * Handle the client "created" event.
     *
     * @param  \App\Models\Client  $client
     *
     * @return void
     */
    public function created(Client $client)
    {
        logger("ClientObserver::created BEGIN");
        // TODO Document this behavior
        ClientWorkflowSetting::create([
            'client_id' => $client->id,
        ]);
        WorkScheduleGroupTemplate::create([
            'client_id' => $client->id,
            'is_default' => true,
            'name' => 'Default',
            'work_days' => '1,2,3,4,5',
            'check_in' => '08:00',
            'check_out' => '17:00',
            'timesheet_deadline_days' => 10 // 10 days
        ]);

        //
        // only call bellow code when has Auth (web)
        //
        $user = Auth::user();
        if ($user && $user->isInternalUser()) {
            $role = $user->getRole();
            if (!($role == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients'))) {
                // Auto assign if this user create client by himself
                IglocalAssignment::create([
                    "iglocal_employee_id" => $user->iGlocalEmployee->id,
                    "client_id" => $client->id,
                ]);
            }

            if (!$client->is_active) {
                $director = IglocalEmployee::query()
                                           ->with('user')
                                           ->where('role', Constant::ROLE_INTERNAL_DIRECTOR)
                                           ->first();
                if (!$director) {
                    logger()->error("Internal system doesn't have Director");
                } elseif (!$director->user) {
                    logger()->error("Internal system's Director doesn't have login account");
                } else {
                    logger('client created', [$client]);
                    Approve::create([
                        "client_id" => $client->id,
                        "type" => "INTERNAL_ACTIVATE_CLIENT", // TODO CONSTANT
                        "client_id" => $client->id,
                        "content" => json_encode([
                            "id" => $client->id,
                            "approve" => true,
                            "code" => $client->code,
                            "company_name" => $client->company_name,
                        ]),
                        "creator_id" => $user->id,
                        "assignee_id" => $director->user->id,
                    ]);
                }
            }
        }

        // Create new assignment project
        AssignmentProject::createProjectForClient($client);

        //Fix bug save company name in only language
        $companyName = $client->company_name;
        $companyAbbreviation = $client->company_abbreviation;
        $client
            ->setTranslation('company_name', 'en', $companyName)
            ->setTranslation('company_name', 'vi', $companyName)
            ->setTranslation('company_name', 'ja', $companyName)
            ->setTranslation('company_abbreviation', 'en', $companyAbbreviation)
            ->setTranslation('company_abbreviation', 'vi', $companyAbbreviation)
            ->setTranslation('company_abbreviation', 'ja', $companyAbbreviation)
            ->update();

    }

    public function updating(Client $client)
    {
        if($client->address_city) {
            $address = Province::where('province_name', $client->address_city)->first();
            if($address) $client->address_province_id = $address->id;
        }

        if($client->address_province) {
            $address = ProvinceDistrict::where('district_name', $client->address_province)->first();
            if($address) $client->address_province_district_id = $address->id;
        }

        if($client->address_province_ward) {
            $address = ProvinceWard::where('ward_name', $client->address_province_ward)->first();
            if($address) $client->address_province_ward_id = $address->id;
        }
    }

    public function deleting(Client $client)
    {
        $allEmployees = ClientEmployee::where('client_id', $client->id)->get();
        if ($allEmployees->isNotEmpty()) {
            foreach ($allEmployees as $employee) {
                DB::table('oauth_access_tokens')->where('user_id', $employee->user_id)->delete(); 
                User::where('id', $employee->user_id)->update(['is_active' => 0]);
            }
        }
        $project = $client->assignmentProject;
        if ($project) {
            $project->delete();
        }

        // Remove approve pending
        Approve::where('client_id', $client->id)
            ->where('type', 'INTERNAL_MANAGE_CALCULATION')
            ->whereNull('approved_at')
            ->whereNull('declined_at')
            ->delete();
        // Remove calculation sheet
        $client->calculationSheet()->whereNotIn('status', ['approved', 'paid'])->delete();
    }

    /**
     * Handle the client "deleted" event.
     *
     * @param \App\Client $client
     *
     * @return void
     */
    public function deleted(Client $client)
    {
    }

    public function restored(Client $client)
    {
        //
    }

    public function forceDeleted(Client $client)
    {
        //
    }
}
