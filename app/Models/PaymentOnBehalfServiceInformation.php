<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $representative_on_behalf
 * @property string $presenter_name_on_behalf
 * @property string $street
 * @property string $ward
 * @property string $address
 * @property string $district
 * @property string $bank_name
 * @property string $account_number
 * @property string $province
 * @property string $branch_name
 */

class PaymentOnBehalfServiceInformation extends Model
{
    use HasFactory, UsesUuid, SoftDeletes;

    protected $table = 'payment_on_behalf_service_information';

    public $timestamps = true;

    /**
     * @var array
     */
    protected $fillable = [
        'id',
        'representative_on_behalf',
        'presenter_name_on_behalf',
        'street',
        'ward',
        'address',
        'district',
        'bank_name',
        'account_number',
        'province',
        'branch_name',
    ];

    /**
     * @return HasMany
     */
    public function clients()
    {
        return $this->hasMany(Client::class);
    }
}
