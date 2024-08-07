<?php

namespace App\Jobs;

use App\DTO\HanetCheckinEvent;
use App\Models\HanetDevice;
use App\Models\HanetPerson;
use App\Models\HanetPlace;
use App\Models\HanetSetting;
use App\Support\PeriodHelper;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\TimesheetHanetTmp;

class HanetCheckinEventHandler implements ShouldQueue, ShouldBeUnique
{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds after which the job's unique lock will be released.
     *
     * @var int
     */
    public int $uniqueFor = 600;

    protected HanetCheckinEvent $event;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($event)
    {
        $this->event = $event;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $event = $this->event;
        $clientEmployees = [];
        // directly matched
        $client_id = $this->get_client($event->keycode);
        if ($client_id) {
            $clientEmployee = $this->getClientEmployee($client_id, $event->alias_id);
            if ($clientEmployee) {
                $clientEmployees[$clientEmployee->id] = $clientEmployee;
            }
        }

        // matched by place_id (share camera)
        $matchedPlaces = HanetPlace::query()->where('hanet_place_id', $event->place_id)->get();
        foreach ($matchedPlaces as $matchedPlace) {
            $clientEmployee = $this->getClientEmployee($matchedPlace->client_id, $event->alias_id);
            if ($clientEmployee && !isset($clientEmployees[$clientEmployee->id])) {
                // skip if already directly matched
                $clientEmployees[$clientEmployee->id] = $clientEmployee;
            }
        }

        // print log if there is no matched client employee
        if (count($clientEmployees) == 0) {
            logger("Hanet checkin webhook called but no matched employees. ", ["event" => $event]);
            return;
        } else {
            logger("Hanet checkin matched. ", ["client_employee_id" => array_keys($clientEmployees)]);
        }

        foreach ($clientEmployees as $clientEmployee) {
            try {
                logger("Hanet checkin", [
                    "place_id" => $event->place_id,
                    "aliasId" => $event->alias_id,
                    "date" => $event->date
                ]);
                $date = Carbon::parse($event->date);
                $clientEmployee->checkTimeAuto($date->toDateString(), PeriodHelper::getHourString($date), 'Hanet');
                // update status process
                $timesheetHanettmp =  TimesheetHanetTmp::find($event->timesheet_hanet_tmp_id);
                $timesheetHanettmp->status = 2;
                $timesheetHanettmp->save();
                
            } catch (Exception $e) {
                logger()->error('update checkin from event error: ' . $e->getMessage());
                $timesheetHanettmp =  TimesheetHanetTmp::find($event->timesheet_hanet_tmp_id);
                $timesheetHanettmp->message_error = $e->getMessage();
                $timesheetHanettmp->save();
            }
        }
    }


    private function get_client($keycode)
    {
        $setting = HanetSetting::select('client_id')
            ->where('partner_token', $keycode)
            ->first();


        if ($setting == null) {
            return null;
        }

        return $setting->client_id;
    }

    private function getClientEmployee($clientId, $aliasId)
    {
        $person = HanetPerson::select('client_employee_id')
            ->where('client_id', $clientId)
            ->where('alias_id', $aliasId)
            ->first();
        if ($person == null) {
            return null;
        }
        return $person->clientEmployee;
    }

    /**
     * The unique ID of the job.
     *
     * @return string
     */
    public function uniqueId(): string
    {
        // prevent hanet call same even multiple time
        return $this->event->hash;
    }
}
