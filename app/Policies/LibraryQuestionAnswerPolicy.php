<?php

namespace App\Policies;

use App\Models\LibraryQuestionAnswer;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Auth;

class LibraryQuestionAnswerPolicy
{
    use HandlesAuthorization;

    public function view(User $user, LibraryQuestionAnswer $libraryQuestionAnswer)
    {
        return true;
    }

    public function create(User $user, array $injected)
    {
        return $this->checkPermission();
    }

    public function update(User $user, LibraryQuestionAnswer $libraryQuestionAnswer)
    {
        return $this->checkPermission();
    }

    public function delete(User $user, LibraryQuestionAnswer $libraryQuestionAnswer)
    {
        return $this->checkPermission();
    }

    public function upload(User $user, LibraryQuestionAnswer $libraryQuestionAnswer): bool
    {
        return $this->checkPermission();
    }

    public function checkPermission() {
        $auth = Auth::user();
        if (!$auth->isInternalUser()) {
            return false;
        } else {
            return true;
        }
    }
}
