<?php

namespace App\Repositories\Admin\Core\User;

interface UserRepositoryInterface
{
    public function storeUser($request): string;
    public function updateUser($request, $id): void;
    public function destroyUser($id): void;
}