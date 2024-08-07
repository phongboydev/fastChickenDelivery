<?php

namespace App\GraphQL\Mutations;

use App\Models\ClientEmployeeDependentRequest;
use App\Models\DependentRequestApplicationLink;
use App\Support\DependentHelper;
use Spatie\ArrayToXml\ArrayToXml;
use App\Exceptions\HumanErrorException;
use App\Jobs\DeleteFileJob;
use App\Models\ClientEmployeeDependentApplication;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClientEmployeeDependentMutator
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        // TODO implement the resolver
    }

    public function clientCreateClientEmployeeDependentRequest($root, array $args): ClientEmployeeDependentRequest
    {

        $request = ClientEmployeeDependentRequest::create($args);

        foreach ($args['links'] as $linkData) {
            DependentRequestApplicationLink::create([
                'client_employee_dependent_request_id' => $request->id,
                'client_employee_dependent_application_id' => $linkData['client_employee_dependent_application_id']
            ]);
        }

        return $request;
    }

    public function createMultiClientEmployeeDependentApplication($root, array $args): array
    {
        $createdRecords = [];

        foreach ($args['input'] as $key => $record) {
            try {
                $createdRecord = ClientEmployeeDependentApplication::create($record);
                $createdRecords[] = $createdRecord;
            } catch (HumanErrorException $e) {
                throw new HumanErrorException(__('model.notifications.creating_failed'));
            }
        }

        return $createdRecords;
    }

    public function exportClientEmployeeDependent($root, array $args)
    {
        $request = ClientEmployeeDependentRequest::authUserAccessible()->find($args['id']);

        if ($request) {
            $emptyFields = array_fill_keys(DependentHelper::EMPTY_FIELD_KEYS, null);

            $kyKKhai = $request->applications[0]->tax_period;

            $client = $request->client;

            $defaultValue = ['_attributes' => ['xsi:nil' => 'true']];

            $emptyFields['ct08'] = $defaultValue;
            $emptyFields['ct10'] = $defaultValue;

            $mainArray = DependentHelper::XML_Schema($kyKKhai, $client);

            // Initialize counters for ID incrementing
            $capMSTCounter = 1;
            $thayDoiCounter = 1;

            foreach ($request->applications as $item) {

                $sectionType = ($item['reg_type'] === 0) ? 'CapMSTChoNPT' : 'ThayDoiTTinNPT';
                $sectionChildName = 'BKe' . $sectionType;
                $sectionId = 'ID_' . (($item['reg_type'] === 0) ? $capMSTCounter++ : $thayDoiCounter++);

                $sectionData = array_merge(['_attributes' => ['id' => $sectionId]], $emptyFields);

                $sectionData['ct07'] = $item->clientEmployee->full_name;
                $sectionData['ct08'] = optional($item->clientEmployee)->mst_code ?? $defaultValue;
                $sectionData['ct09'] = $item['name_dependents'];
                $sectionData['ct10'] = optional($item)['date_of_birth'] ?? $defaultValue;
                $sectionData['ct11'] = $item['tax_code'];
                $sectionData['ct12_ma'] = $item['nationality'];
                $sectionData['ct12'] = $item['nationality_name'];
                $sectionData['ct13'] = $item['identification_number'];
                $sectionData['ct14_ma'] = $item['relationship_code'];
                $sectionData['ct14'] = $item['relationship_name'];
                $sectionData['ct15'] = $item['dob_info_num'];
                $sectionData['ct16'] = $item['dob_info_book_num'];
                $sectionData['ct17_ma'] = $item['country_code'];
                $sectionData['ct17'] = $item['country_name'];
                $sectionData['ct18_ma'] = $item['province_code'];
                $sectionData['ct18'] = $item['province_name'];
                $sectionData['ct19_ma'] = $item['district_code'];
                $sectionData['ct19'] = $item['district_name'];
                $sectionData['ct20_ma'] = $item['ward_code'];
                $sectionData['ct20'] = $item['ward_name'];
                $sectionData['ct21'] = optional($item)['from_date'] ? Carbon::parse($item['from_date'])->format('m/Y') : null;
                $sectionData['ct22'] = optional($item)['to_date'] ? Carbon::parse($item['to_date'])->format('m/Y') : null;
                $sectionData['ct23'] = null; // This is intentionally set to null

                $mainArray['HSoKhaiThue']['CTieuTKhaiChinh'][$sectionType][$sectionChildName][] = $sectionData;
            }

            $emptyFields = array_merge(['_attributes' => ['id' => 'ID_1']], $emptyFields);

            // Check if CapMSTChoNPT or ThayDoiTTinNPT sections are empty
            if (empty($mainArray['HSoKhaiThue']['CTieuTKhaiChinh']['CapMSTChoNPT']['BKeCapMSTChoNPT'])) {
                $mainArray['HSoKhaiThue']['CTieuTKhaiChinh']['CapMSTChoNPT']['BKeCapMSTChoNPT'] = $emptyFields;
            }
            if (empty($mainArray['HSoKhaiThue']['CTieuTKhaiChinh']['ThayDoiTTinNPT']['BKeThayDoiTTinNPT'])) {
                $mainArray['HSoKhaiThue']['CTieuTKhaiChinh']['ThayDoiTTinNPT']['BKeThayDoiTTinNPT'] = $emptyFields;
            }

            $xmlContent = ArrayToXml::convert($mainArray, DependentHelper::ROOT_ELEMENT_NAME, true, 'UTF-8');
            $fileName = "{$client->code}-MST{$client->pit_declaration_company_tax_code}-N" . date('Ymd') . "-L001-" . Str::uuid() . ".xml";
            $filePath = 'ClientDependentExport/' . $fileName;

            Storage::disk('minio')->put($filePath, DependentHelper::xmlWithComment($xmlContent));
            $expirationTime = Carbon::now()->addMinutes(config('app.media_temporary_time', 2));
            $temporaryUrl = Storage::temporaryUrl($filePath, $expirationTime);

            DeleteFileJob::dispatch($filePath)->delay(now()->addMinutes(3));

            return json_encode([
                'status' => 200,
                'name' => $fileName,
                'url' => $temporaryUrl,
                'path' => $filePath,
                'expired' => $expirationTime
            ], JSON_UNESCAPED_UNICODE);
        } else {
            throw new HumanErrorException(__('no_data'));
        }
    }
}
