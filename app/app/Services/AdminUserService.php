<?php

namespace App\Services;

use App\Models\User;
use App\Support\RoleName;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminUserService
{
    /**
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function create(array $data, User $actor): User
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($data, $actor): User {
            $user = User::query()->create([
                'name' => trim($data['name']),
                'email' => mb_strtolower(trim($data['email'])),
                'password' => $data['password'],
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
     * Update data admin. Password hanya diubah kalau diisi.
     *
     * @param  array{name: string, email: string, password?: ?string}  $data
     *
     * @throws DomainException kalau target bukan admin
     */
    public function update(User $target, array $data, User $actor): User
    {
        $this->authorize($actor);
        $this->ensureAdmin($target);

        return DB::transaction(function () use ($target, $data, $actor): User {
            $target->name = trim($data['name']);
            $target->email = mb_strtolower(trim($data['email']));

            $passwordChanged = ! empty($data['password']);

            if ($passwordChanged) {
                $target->password = $data['password'];
                $target->setRememberToken(Str::random(60));
            }

            $changed = array_keys($target->getDirty());

            if ($changed === []) {
                return $target;
            }

            $target->save();

            // Paksa logout sesi lama target kalau passwordnya direset orang lain.
            if ($passwordChanged && ! $target->is($actor) && config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $target->id)->delete();
            }

            activity()
                ->causedBy($actor)
                ->performedOn($target)
                ->withProperties([
                    'changed' => array_values(array_diff($changed, ['password', 'remember_token', 'updated_at'])),
                    'password_changed' => $passwordChanged,
                ])
                ->log('Mengubah admin');

            return $target;
        });
    }

    /**
     * @throws DomainException kalau admin tidak boleh dihapus
     */
    public function delete(User $target, User $actor): void
    {
        $this->authorize($actor);
        $this->ensureAdmin($target);

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
        } catch (QueryException $e) {
            // Hanya pelanggaran foreign key (SQLSTATE 23000); error lain (deadlock, koneksi) dilempar ulang.
            if ((string) $e->getCode() !== '23000') {
                throw $e;
            }

            throw new DomainException('Admin ini masih dipakai oleh data lain, jadi tidak bisa dihapus.');
        }
    }

    private function authorize(User $actor): void
    {
        abort_unless($actor->hasRole(RoleName::ADMIN), 403);
    }

    private function ensureAdmin(User $target): void
    {
        if (! $target->hasRole(RoleName::ADMIN)) {
            throw new DomainException('Pengguna ini bukan admin.');
        }
    }
}