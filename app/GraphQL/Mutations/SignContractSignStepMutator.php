<?php

namespace App\GraphQL\Mutations;

use App\DTO\ContractSignResult;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractSignStep;
use App\Support\Constant;
use App\Support\SignApiHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class SignContractSignStepMutator
{

    const USB_SIGN = "USB_SIGN";
    const IMAGE_SIGN = "IMAGE_SIGN";

    /**
     * id: ID!
     * step: Int
     * signType: SignType
     * imageBase64: String
     *
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        $id = $args['id'];
        $step = $args['step'];
        $signType = $args['signType'];
        $imageBase64 = $args['imageBase64'] ?? null;

        /** @var Contract $contract */
        $contract = Contract::query()->authUserAccessible()->findOrFail($id);
        /** @var ContractSignStep $contractSignStep */
        $contractSignStep = $contract->contractSignSteps()->where('step', $step)->firstOrFail();

        $response = new ContractSignResult();
        $result = $this->createDataESOC($contract, $contractSignStep, $signType, $imageBase64);
        if ($result) {
            $response->is_done = true;
            $response->need_plugin_sign = $signType === self::USB_SIGN;
            if ($signType === self::IMAGE_SIGN) {
                $realData = json_decode($result['Data'], true);
                if (isset($realData['TaiLieus'][0]) && isset($realData['TaiLieus'][0]['Base64File'])) {
                    $response->base64 = $realData['TaiLieus'][0]['Base64File'];
                }
            } else {
                $response->base64 = $result['Data'];
            }
            return $response;
        }

        $response->is_done = false;
        $response->need_plugin_sign = false;
        $response->base64 = "";
        return $response;
    }

    private function createDataESOC(Contract $contract, ContractSignStep $contractSignStep, $signType, $imageBase64)
    {
        /** @var Client $client */
        $client = $contract->client;
        $mst = $client->company_license_no;
        $user = Auth::user();

        $token = SignApiHelper::loginSignApi();
        logger(__METHOD__ . ": token: " . $token);

        $pdf        = $contract->getLatestContractMedia();
        $pdfPath    = $pdf->getPath();
        $pdfContent = Storage::disk("minio")->get($pdfPath);
        $contentBase64 = base64_encode($pdfContent);

        $chuKys = [
            [
                "TrangKy" => $contractSignStep->page_no,
                "DoRong" => $contractSignStep->sign_w,
                "DoCao" => $contractSignStep->sign_h,
                "ToaDoX" => $contractSignStep->sign_x,
                "ToaDoY" => $contractSignStep->sign_y
            ]
        ];

        $taiLieus = [
            [
                "MaThamChieu"    => $contract->contract_no,
                "ThuTuKy"        => $contractSignStep->step == "company" ? 1 : 2,
                "HoVaTen"        => $user->name,
                "Email"          => $user->email,
                "IsKyTheoToaDo"  => true,
                "ViTriHienThiChuKy" => 1,
                "ViTriTrangKy"   => $contractSignStep->page_no,
                "ChuKys"         => $chuKys,
                "FileContent"    => $contentBase64,
            ]
        ];

        $data = [
            "IsUsbToken" => $signType == self::USB_SIGN ? "true" : "false",
            "ChuKyImg" => $signType == self::IMAGE_SIGN ? $imageBase64 : null,
            "MaSoThue" => $mst,
            "TaiLieus" => $taiLieus,
        ];

        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->withToken($token)
            ->withBody(json_encode($data), 'application/json')
            ->post(
                config("vpo.esoc.url") . Constant::API_CREATE_DATA,
            );
        logger(__METHOD__ . ": called API to create data");
        if ($response->successful()) {
            $body = $response->json();
            if (
                isset($body['Status']) &&
                $body['Status'] == 1 &&
                isset($body["Data"])
            ) {
                if (isset($body["Data"]["Lois"])) {
                    logger(__METHOD__ . ": error: ", $body["Data"]["Lois"]);
                }
                if (isset($body["Data"]["ThanhCongs"])) {
                    $thanhCong = $body["Data"]["ThanhCongs"];
                    return $thanhCong;
                }
            }
        } else {
            logger(__METHOD__ . ": failed", ["error" => $response->status()]);
        }
        return null;
    }
}
