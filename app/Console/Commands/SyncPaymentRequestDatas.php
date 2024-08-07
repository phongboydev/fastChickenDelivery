<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\PaymentRequest;
use App\Models\PaymentRequestAmount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncPaymentRequestDatas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment-request-data:sync {fromDate?} {toDate?} {--clientCode= : Code of client}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Syncing data from payment request table to payment request amount table';

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
        $clientCode = $this->option("clientCode");
        $from = $this->argument("fromDate");
        $to = $this->argument("toDate");
        $start = !empty($from) ? $from . ' 00:00:00' : '';
        $end = !empty($to) ? $to . ' 23:59:59' : '';

        $client = null;
        if ($clientCode) {
            $client = Client::where('code', $clientCode)->first('id');
            if (empty($client)) {
                $this->error("Not found client code!");
                return 0;
            }
        }
        try {
            DB::beginTransaction();
            $paymentRequests = PaymentRequest::select(['id as payment_request_id', 'amount', 'note', 'created_at', 'updated_at']);
            if ($client) {
                $paymentRequests = $paymentRequests->where('client_id', $client->id);
            }
            if ($start) {
                $paymentRequests = $paymentRequests->where('created_at', '>=', $start);
            }
            if ($end) {
                $paymentRequests = $paymentRequests->where('created_at', '<=', $end);
            }
            $paymentRequests = $paymentRequests->get();
            foreach($paymentRequests as $paymentRequest) {
                if (!is_null($paymentRequest->amount)) {
                    $paymentAmounts = PaymentRequestAmount::where('payment_request_id', $paymentRequest->payment_request_id)->get();
                    foreach ($paymentAmounts as $paymentAmount) {
                        $paymentAmount->delete();
                    }

                    $data = [
                        'id' => Str::uuid(),
                        'payment_request_id' => $paymentRequest->payment_request_id,
                        'amount' => $paymentRequest->amount,
                        'note' => $paymentRequest->note,
                        'created_at' => $paymentRequest->created_at,
                        'updated_at' => $paymentRequest->updated_at
                    ];
                    PaymentRequestAmount::insert($data);
                }
            }
            DB::commit();
            //send output to the console
            $this->info('Success!');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error($e->getMessage());
        }

        return 1;
    }
}
