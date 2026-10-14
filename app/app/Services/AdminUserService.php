<?php

namespace App\Services;

use App\Models\User;
use App\Support\RoleName;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class AdminUserService
{
    /**
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function create(array $data, User $actor): User
    {
        return DB::transaction(function () use ($data, $actor): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'], // di-hash otomatis oleh cast model
            ]);

            $user->assignRole(RoleName::ADMIN);

            activity()
                ->causedBy($actor)
                ->performedOn($user)
                ->withProperties(['name' => $user->name, 'email' => $user->email])
                ->log('Menambah admin');

            return $user;
        });
    }

    /**
     * @throws DomainException kalau admin tidak boleh dihapus
     */
    public function delete(User $target, User $actor): void
    {
        if ($target->is($actor)) {
            throw new DomainException('Kamu tidak bisa menghapus akunmu sendiri.');
        }

        if ($target->createdWorkSessions()->exists()) {
            throw new DomainException('Admin ini pernah membuat sesi kerja, jadi tidak bisa dihapus.');
        }

        try {
            DB::transaction(function () use ($target, $actor): void {
                $adminIds = User::role(RoleName::ADMIN)->lockForUpdate()->pluck('id');

                if ($adminIds->contains($target->id) && $adminIds->count() <= 1) {
                    throw new DomainException('Admin terakhir tidak boleh dihapus.');
                }

                activity()
                    ->causedBy($actor)
                    ->performedOn($target)
                    ->withProperties(['name' => $target->name, 'email' => $target->email])
                    ->log('Menghapus admin');

                $target->delete();
            });
        } catch (QueryException) {
            throw new DomainException('Admin ini masih dipakai oleh data lain, jadi tidak bisa dihapus.');
        }
    }
}