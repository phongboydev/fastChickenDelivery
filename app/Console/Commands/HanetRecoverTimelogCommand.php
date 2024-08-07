<?php

namespace App\Console\Commands;

use App\Models\Checking;
use App\Models\HanetPerson;
use App\Models\HanetSetting;
use App\Models\HanetLog;
use App\Models\Timesheet;
use App\Support\Constant;
use App\Support\PeriodHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Calculation\Logical\Boolean;
use App\Models\ClientLogDebug;
use App\Support\TimesheetsHelper;

class HanetRecoverTimelogCommand extends Command
{

    const HANET_PAGE_SIZE = 500;
    protected $signature = 'hanet:recover {--d|dry-run} {--yesterday} {date?} {clientCode?} {employeeCode?}';
    protected $description = 'Recover a specified date';

    public function handle()
    {
        $date = $this->argument("date") ?: Carbon::now(Constant::TIMESHEET_TIMEZONE)->toDateString();
        $dryRun = $this->option('dry-run');
        $yesterday = $this->option('yesterday');
        $clientCode = $this->argument('clientCode');
        $employeeCode = $this->argument('employeeCode');

        if ($yesterday) {
            $date = (Carbon::parse($date))->subDay(1)->toDateString();
        }

        $this->line("Recover date " . $date);

        $query = HanetSetting::query();
        if ($dryRun) {
            $this->line('This is DRY RUN: No data will be changed.');
        }
        if ($clientCode) {
            $query->whereHas('client', function ($subQuery) use ($clientCode) {
                $subQuery->where('code', $clientCode);
            });
        }

        $query
            ->with('client')
            ->chunk(100, function ($hanetSettings) use ($dryRun, $employeeCode, $date) {
                foreach ($hanetSettings as $hanetSetting) {
                    /** @var HanetSetting $hanetSetting */
                    if (!$hanetSetting->client) {
                        logger()->warning('HanetRecoverTimelogCommand: HanetSetting without Client found', [
                            'id' =>
                            $hanetSetting->id,
                        ]);
                        return;
                    }
                    $this->line('Processing ' . $hanetSetting->client->code);

                    // Adjust from to according to date begin mark
                    $workflowSetting = $hanetSetting->client->clientWorkflowSetting;
                    $dayBeginMark = $workflowSetting->getTimesheetDayBeginAttribute();
                    $dayBeginMarkCarbon = Carbon::parse($dayBeginMark, Constant::TIMESHEET_TIMEZONE);
                    $from = Carbon::parse($date, Constant::TIMESHEET_TIMEZONE)
                        ->setHour($dayBeginMarkCarbon->hour)
                        ->setMinute($dayBeginMarkCarbon->minute);
                    $to = $from->clone()->addDay();

                    // split $from and $to into 2 periods separated by 00:00
                    // Reason: Hanet API does not support querying across 00:00 (bug?)
                    $periods = [
                        [$from, $from->clone()->setTime(23, 59, 59)],
                    ];
                    if ($to->hour > 0 || $to->minute > 0) {
                        $periods[] = [$to->clone()->setTime(0, 0, 1), $to];
                    }

                    $places = [];
                    $endPoint = config('hanet.partner_url');
                    $accessToken = $hanetSetting->token;

                    $placesResponse = Http::post($endPoint . '/place/getPlaces', [
                        'token' => $accessToken,
                    ]);

                    $placesResponseBody = $placesResponse->json();
                    HanetLog::create([
                        'client_id' => $hanetSetting->client->id,
                        'date' => $date,
                        'is_success' => ($placesResponseBody['returnMessage'] == "SUCCESS") ? true : false,
                        'response_data' => $placesResponse
                    ]);

                    if (isset($placesResponseBody['data']) && is_array($placesResponseBody['data'])) {
                        foreach ($placesResponseBody['data'] as $place) {
                            if (isset($place['id'])) {
                                $places[] = ['id' => $place['id']];
                            }
                        }
                    }

                    $checkingList = [];
                    foreach ($places as $place) {
                        $this->info('Processing place ' . $place['id']);

                        $checkInRecords = [];
                        foreach ($periods as $period) {
                            $this->fetchAllCheckInRecords(
                                $checkInRecords,
                                $accessToken,
                                $place['id'],
                                $period[0],
                                $period[1]
                            );
                        }

                        $logs = collect($checkInRecords)
                            ->reject(function ($item) {
                                return $item['aliasID'] === ''; // User Unlinked Hanet
                            })
                            ->sortBy('checkinTime')->groupBy('aliasID');

                        $persons = HanetPerson::query()
                            ->with("clientEmployee")
                            ->where(
                                "client_id",
                                $hanetSetting->client_id
                            )
                            ->whereIn("alias_id", $logs->keys())
                            ->get()
                            ->keyBy('alias_id');

                        foreach ($logs as $aliasId => $checkinByAliasID) {

                            if ($employeeCode && $aliasId != $employeeCode) {
                                continue;
                            }

                            // Check if the person is synced
                            $person = $persons[$aliasId] ?? null;

                            if (!$person) {
                                $this->line('Person is not synced. Alias=' . $aliasId);
                                continue;
                            }

                            if (!$person->clientEmployee) {
                                $this->line('Employee is null. Alias=' . $aliasId);
                                logger("Employee is null, Alias =" . $aliasId . ", client_id = " . $hanetSetting->client_id);
                                continue;
                            }

                            $timesheet = (new Timesheet())->findTimeSheet($person->clientEmployee->id, $date);

                            if(!$timesheet) {
                                $timesheet = TimesheetsHelper::createTimeSheetPerDate( $person->clientEmployee->id, $date );
                            }

                            if ($timesheet && $timesheet->isUsingMultiShift($workflowSetting)) {
                                $tableContent = [];

                                foreach ($checkinByAliasID as $index => $item) {
                                    // check checkinTime between begin time to end time
                                    if($item['checkinTime'] >= $from->getTimestampMs() && $item['checkinTime'] <= $to->getTimestampMs()) {
                                        $intime = Carbon::createFromTimestampMs($item['checkinTime'], Constant::TIMESHEET_TIMEZONE);

                                        if (!$dryRun) {
                                            $timesheet->checkTimeWithMultiShift($intime, 'Hanet');
                                        }

                                        $tableContent[] = [
                                            '#' => $index + 1,
                                            'aliasId' => $aliasId,
                                            'clientEmployeeID' => $person->clientEmployee->id,
                                            'timeSheetID' => $timesheet->id,
                                            'personName' => $item['personName'],
                                            'checkinTime' => $intime
                                        ];
                                    }

                                    $checkingList[] = [
                                        'client_id' => $person->clientEmployee->client_id,
                                        'client_employee_id' => $person->clientEmployee->id,
                                        'checking_time' => Carbon::createFromTimestampMs($item['checkinTime'])->toDateTimeString(),
                                        'source' => 'SyncHanet'
                                    ];
                                }

                                if (!$dryRun) {
                                    $timesheet->recalculate();
                                    $timesheet->saveQuietly();
                                }

                                $this->table(
                                    ['#', 'aliasId', 'clientEmployeeID', 'timeSheetID', 'personName', 'checkinTime'],
                                    $tableContent
                                );
                            } else {
                                $in = null;
                                $out = null;

                                if(!empty($timesheet->check_in)) {
                                    $timeCheckin = Carbon::parse($timesheet->log_date . ' ' . $timesheet->check_in.':00', Constant::TIMESHEET_TIMEZONE);
                                    $timeCheckin->addDays((int)$timesheet->start_next_day);
                                    $in = $timeCheckin->timestamp * 1000;
                                }

                                if(!empty($timesheet->check_out)) {
                                    $timeCheckout = Carbon::parse($timesheet->log_date . 'T' . $timesheet->check_out.':00', Constant::TIMESHEET_TIMEZONE);
                                    $timeCheckout->addDays((int)$timesheet->next_day);
                                    $out = $timeCheckout->timestamp * 1000;
                                }

                                foreach ($checkinByAliasID as $item) {
                                    // check checkinTime between begin time to end time
                                    if($item['checkinTime'] >= $from->getTimestampMs() && $item['checkinTime'] <= $to->getTimestampMs()) {
                                        $checkInTime = $item['checkinTime'];

                                        $checkInTimeHuman = Carbon::createFromTimestampMs($checkInTime, Constant::TIMESHEET_TIMEZONE);
                                        $this->line('Data: AliasID=' . $aliasId . ',checkinTime=' . $checkInTimeHuman . ',deviceID=' . $item['deviceID']);

                                        if ($in === null || $checkInTime < $in) {
                                            $out = $in;
                                            $in = $checkInTime;
                                        } elseif ($out === null || $checkInTime > $out) {
                                            $out = $checkInTime;
                                        }
                                    }

                                    $checkingList[] = [
                                        'client_id' => $person->clientEmployee->client_id,
                                        'client_employee_id' => $person->clientEmployee->id,
                                        'checking_time' => Carbon::createFromTimestampMs($item['checkinTime'])->toDateTimeString(),
                                        'source' => 'SyncHanet'
                                    ];
                                }

                                $inTime = $in ? Carbon::createFromTimestampMs($in, Constant::TIMESHEET_TIMEZONE) : null;
                                $outTime = $out ? Carbon::createFromTimestampMs($out, Constant::TIMESHEET_TIMEZONE) : null;
                                $this->line('Data: AliasID=' . $aliasId . ',recordIn=' . $inTime . ',recondOut=' . $outTime);

                                // Add log before store
                                $data_log = ['employee_id' => $person->clientEmployee->id, 'code' => $aliasId, 'in' => $inTime, 'out' => $outTime];
                                $logDebug = new ClientLogDebug();
                                $logDebug->type = 'Hanet Employee ';
                                $logDebug->alias_id = $aliasId;
                                $logDebug->data_log = json_encode($data_log);
                                $logDebug->note = 'Store Hanet Employee code : ' . $aliasId . ' , log date ' . ' , log date ' . $timesheet->log_date;
                                $logDebug->save();

                                if (!$dryRun) {
                                    if ($inTime) {
                                        // reset plan flexible time
                                        // if(!$timesheet->skip_plan_flexible && ($timesheet->flexible_check_in || $timesheet->flexible_check_out)) {
                                        //     $timesheet->flexible_check_in = null;
                                        //     $timesheet->flexible_check_out = null;
                                        //     $timesheet->save();
                                        // }

                                        $person->clientEmployee->checkIn($date, PeriodHelper::getHourString($inTime), $date != $inTime->toDateString(), 'Hanet');
                                    }
                                    if ($outTime) {
                                        $person->clientEmployee->checkOut($date, PeriodHelper::getHourString($outTime), $date != $outTime->toDateString(), 'Hanet');
                                    }
                                }
                            }
                        }
                    }

                    if (!empty($checkingList)) {
                        Checking::upsert($checkingList, ['client_employee_id', 'checking_time']);
                    }
                }
            });
    }

    /**
     * Fetch all check-in records by place ID and date range.
     *
     * @param array $checkInRecords
     * @param string $accessToken
     * @param string $placeId
     * @param Carbon $from
     * @param Carbon $to
     * @return array
     */
    private function fetchAllCheckInRecords(
        array &$checkInRecords,
        string $accessToken,
        string $placeId,
        Carbon $from,
        Carbon $to
    ): array {
        $page = 1;
        $endpoint = config('hanet.partner_url');

        do {
            $this->info($from->toDateTimeString() . " -> " . $to->toDateTimeString() . " - Page: " . $page);

            $response = Http::asForm()->post("$endpoint/person/getCheckinByPlaceIdInTimestamp", [
                'token' => $accessToken,
                'placeID' => $placeId,
                'type' => 0, // Employee only
                'from' => $from->getTimestampMs(),
                'to' => $to->getTimestampMs(),
                'page' => $page,
                'size' => self::HANET_PAGE_SIZE,
            ]);

            if (!$response->ok()) {
                break;
            }

            // Store logs response from Hanet when sync Hanet
            $logDebug = new ClientLogDebug();
            $logDebug->place_id = $placeId;
            $logDebug->type = 'Hanet';
            $logDebug->data_log = $response;
            $logDebug->note = 'Sync Hanet accessToken: ' . $accessToken . ' - from: (' . $from . ')' . $from->getTimestampMs() . ' ' . ' - to: (' . $to . ')' . $to->getTimestampMs() . ' ' . " - Page: " . $page;
            $logDebug->save();
            $responseBody = $response->json();
            if (empty($responseBody['data'])) {
                $this->warn("Empty data");
                break;
            }

            // Merge data
            $checkInRecords = array_merge($checkInRecords, $responseBody['data']);

            if (count($responseBody['data']) < self::HANET_PAGE_SIZE) {
                break;
            }

            $page++;
        } while (true);

        return $checkInRecords;
    }
}
