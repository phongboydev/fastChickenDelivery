<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use App\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LibraryQuestionAnswerHistory extends Model
{
    use HasFactory, UsesUuid;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;
    protected $table = 'library_question_answer_histories';

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * @var array
     */
    protected $fillable = [
        'library_question_answer_id',
        'new_value',
        'old_value',
        'updater_id'
    ];


    public function libraryQuestionAnswer()
    {
        return $this->belongsTo(LibraryQuestionAnswer::class, 'library_question_answer_id');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updater_id');
    }

     public function scopeAuthUserAccessible($query)
    {
        return $query;
    }
}
