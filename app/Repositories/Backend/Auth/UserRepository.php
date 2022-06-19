<?php

namespace App\Repositories\Backend\Auth;

use App\Events\Backend\Auth\User\UserConfirmed;
use App\Events\Backend\Auth\User\UserCreated;
use App\Events\Backend\Auth\User\UserDeactivated;
use App\Events\Backend\Auth\User\UserDeleted;
use App\Events\Backend\Auth\User\UserPasswordChanged;
use App\Events\Backend\Auth\User\UserPermanentlyDeleted;
use App\Events\Backend\Auth\User\UserReactivated;
use App\Events\Backend\Auth\User\UserRestored;
use App\Events\Backend\Auth\User\UserUnconfirmed;
use App\Events\Backend\Auth\User\UserUpdated;
use App\Exceptions\GeneralException;
use App\Models\Auth\User;
use App\Models\Auth\Frindship;
use App\Models\Auth\Package;
use App\Notifications\Backend\Auth\UserAccountActive;
use App\Notifications\Frontend\Auth\UserNeedsConfirmation;
use App\Repositories\BaseRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

/**
 * Class UserRepository.
 */
class UserRepository extends BaseRepository
{
    /**
     * Associated Repository Model.
     */
    const MODEL = User::class;

    public function addFriend($friend_id)
    {
        // fisrt_user_id is the person who ask for friend relation
        // second_user_id is the person who has been asked for friend relation
        $user = auth()->user();
        $asked_for_frined = Frindship::where(["fisrt_user_id" => $user->id, "second_user_id" => $friend_id])->first();
        if ($asked_for_frined === null) {
            Frindship::create(["fisrt_user_id" => $user->id, "second_user_id" => $friend_id]);
            return 0;
        }

        if ($asked_for_frined->accept == 1) {
            return 1;
        }

        if ($asked_for_frined->accept == 0) {
            return 2;
        }
    }

    public function acceptFriend($friend_id)
    {
        // fisrt_user_id is the person who ask for friend relation
        // second_user_id is the person who has been asked for friend relation
        $user = auth()->user();
        $relation = Frindship::where(["fisrt_user_id" => $friend_id, "second_user_id" => $user->id])->first();
        if ($relation) {
            $relation->accept = 1;
            $relation->save();
            return 1;
        }
        return 0;
    }

    public function cancelFriend($friend_id)
    {
        $user = auth()->user();
        $relation = Frindship::whereIn('fisrt_user_id', [auth()->user()->id, $friend_id])
            ->whereIn('second_user_id', [auth()->user()->id, $friend_id])->first();
        $relation->accept = 0;
        $relation->save();
    }

    public function checkFriendRequest()
    {
        $user = auth()->user();
        $freiends_requeset = Frindship::where(['fisrt_user_id' => auth()->user()->id, 'accept' => false])->get(['second_user_id AS id']);
        $friends = [];
        foreach ($freiends_requeset as $friend) {
            array_push($friends, $friend->id);
        }
        $all_friends_request = User::find($friends);
        return $all_friends_request;
    }

    function getInternalFilesCount($dir)
    {
        $files_count = 0;
        foreach (glob("${dir}/*") as $fn) {
            if (is_dir($fn)) {
                $files_count  = $this->getInternalFilesCount($fn);
            } else {
                $files_count += 1;
            }
        }

        return $files_count;
    }


    /**
     * @param int  $status
     * @param bool $trashed
     *
     * @return mixed
     */
    public function getForDataTable($status = 1, $trashed = false)
    {
        /**
         * Note: You must return deleted_at or the User getActionButtonsAttribute won't
         * be able to differentiate what buttons to show for each row.
         */
        $dataTableQuery = $this->query()
            ->select([
                'users.id',
                'users.first_name',
                'users.last_name',
                'users.email',
                'users.question_id',
                'users.answer',
                'users.status',
                'users.confirmed',
                'users.created_at',
                'users.updated_at',
                'users.deleted_at',
            ]);

        if ($trashed == 'true') {
            return $dataTableQuery->onlyTrashed();
        }

        // active() is a scope on the UserScope trait
        return $dataTableQuery->active($status);
    }

    public function retrieveList(array $options = [])
    {
        $perPage = isset($options['per_page']) ? (int) $options['per_page'] : 20;
        $orderBy = isset($options['order_by']) && in_array($options['order_by'], $this->sortable) ? $options['order_by'] : 'created_at';
        $order = isset($options['order']) && in_array($options['order'], ['asc', 'desc']) ? $options['order'] : 'desc';
        $query = $this->query()
            ->orderBy($orderBy, $order);

        if ($perPage == -1) {
            return $query->get();
        }

        return $query->paginate($perPage);
    }

    public function RetrieveSearchList(array $options = [], String $user_name)
    {
        $perProduct = isset($options['per_page']) ? (int) $options['per_page'] : 20;
        $orderBy = isset($options['order_by']) && in_array($options['order_by'], $this->sortable) ? $options['order_by'] : 'created_at';
        $order = isset($options['order']) && in_array($options['order'], ['asc', 'desc']) ? $options['order'] : 'desc';
        $query = $this->query()
            ->select([
                'users.id',
                'users.first_name',
                'users.avatar_location',
                'users.email',
            ])
            //  ->where('status',0) // avilable products => 0 , disabled products => 1
            ->where('first_name', 'like', "%{$user_name}%")
            // ->orderBy('sort')
            ->orderBy('updated_at', $order)
            ->orderBy($orderBy, $order);

        return  Datatables::of($query)
            ->addColumn('is_friend', function ($user) {
                return Frindship::whereIn('fisrt_user_id', [auth()->user()->id, $user->id])
                    ->whereIn('second_user_id', [auth()->user()->id, $user->id])
                    ->where('accept', true)->exists();
            })->make(true);

        // if ($perProduct == -1) {
        //     return $query->get();
        // }

        // return $query->paginate($perProduct);
    }

    /**
     * @param array $data
     *
     * @throws \Exception
     * @throws \Throwable
     * @return User
     */
    public function create(array $data, $img = null)
    {
        //// $roles = $data['assignees_roles'];
        $roles = 1;
        ////$permissions = $data['permissions'];
        $permissions = 1;
        ////unset($data['assignees_roles']);
        ////unset($data['permissions']);

        $user = $this->createUserStub($data);

        if (isset($img)) {
            $path = $img->storePublicly('public/profile_photo');
            $user['avatar_location'] = str_replace("public", "storage", $path);
        }

        return DB::transaction(function () use ($user, $data, $roles, $permissions) {
            if ($user->save()) {
                //Attach new roles
                ////$user->attachRoles($roles);

                // Attach New Permissions
                ////$user->attachPermissions($permissions);

                //Send confirmation email if requested and account approval is off
                if (isset($data['confirmation_email']) && $user->confirmed == 0) {
                    $user->notify(new UserNeedsConfirmation($user->confirmation_code));
                }

                event(new UserCreated($user));

                return $user;
            }

            throw new GeneralException(__('exceptions.backend.access.users.create_error'));
        });
    }

    /**
     * @param \App\Models\Auth\User  $user
     * @param array $data
     *
     * @throws GeneralException
     * @throws \Exception
     * @throws \Throwable
     * @return \App\Models\Auth\User
     */
    public function update(User $user, array $data, $img = null)
    {
        //// $roles = $data['assignees_roles'];
        $roles = 1;
        ////$permissions = $data['permissions'];
        $permissions = 1;
        ////unset($data['assignees_roles']);
        ////unset($data['permissions']);

        if (isset($img)) {
            $path = $img->storePublicly('public/profile_photo');
            $data['avatar_location'] = str_replace("public", "storage", $path);
        }

        return DB::transaction(function () use ($user, $data, $roles, $permissions) {
            if (isset($data['question_id'])) {
                $user->question_id = $data['question_id'];
            }

            if (isset($data['answer'])) {
                $user->answer = $data['answer'];
            }
            $user->save();
            $user->status = isset($data['status']) && $data['status'] == '1' ? 1 : 0;
            $user->confirmed = isset($data['confirmed']) && $data['confirmed'] == '1' ? 1 : 0;

            if ($user->update($data)) {
                $user->roles()->sync($roles);
                $user->permissions()->sync($permissions);

                event(new UserUpdated($user));

                return $user;
            }

            throw new GeneralException(__('exceptions.backend.access.users.update_error'));
        });
    }

    /**
     * Delete User.
     *
     * @param App\Models\Auth\User $user
     *
     * @throws GeneralException
     *
     * @return bool
     */
    public function delete(User $user, $password)
    {
        if (!\Hash::check($password, auth()->user()->password)) {
            throw new GeneralException(__('exceptions.backend.access.users.delete_error'));
        }

        // if (access()->id() == $user->id) {
        //     throw new GeneralException(__('exceptions.backend.access.users.cant_delete_self'));
        // }

        if ($user->delete()) {
            event(new UserDeleted($user));

            return true;
        }

        throw new GeneralException(__('exceptions.backend.access.users.delete_error'));
    }

    /**
     * @param \App\Models\Auth\User $user
     * @param      $input
     *
     * @throws GeneralException
     * @return \App\Models\Auth\User
     */
    public function updatePassword($input): User
    {
        $user = User::find(auth()->user()->id);
        if (\Hash::check($input['old_password'], auth()->user()->password)) {
            if ($user->update(['password' => bcrypt($input['password'])])) {
                event(new UserPasswordChanged($user));

                return $user;
            }

            throw new GeneralException(__('exceptions.backend.access.users.update_password_error'));
        } else {
            throw new GeneralException('password mismatch');
        }
    }

    /**
     * @param \App\Models\Auth\User $user
     * @param int $status
     *
     * @throws GeneralException
     * @return \App\Models\Auth\User
     */
    public function mark(User $user, $status): User
    {
        if (access()->id() == $user->id && $status == 0) {
            throw new GeneralException(__('exceptions.backend.access.users.cant_deactivate_self'));
        }

        $user->status = $status;

        switch ($status) {
            case 0:
                event(new UserDeactivated($user));
                break;
            case 1:
                event(new UserReactivated($user));
                break;
        }

        if ($user->save()) {
            return $user;
        }

        throw new GeneralException(__('exceptions.backend.access.users.mark_error'));
    }

    /**
     * @param \App\Models\Auth\User $user
     *
     * @throws GeneralException
     * @return \App\Models\Auth\User
     */
    public function confirm(User $user): User
    {
        if ($user->confirmed) {
            throw new GeneralException(__('exceptions.backend.access.users.already_confirmed'));
        }

        $user->confirmed = true;
        $confirmed = $user->save();

        if ($confirmed) {
            event(new UserConfirmed($user));

            // Let user know their account was approved
            if (config('access.users.requires_approval')) {
                $user->notify(new UserAccountActive);
            }

            return $user;
        }

        throw new GeneralException(__('exceptions.backend.access.users.cant_confirm'));
    }

    /**
     * @param \App\Models\Auth\User $user
     *
     * @throws GeneralException
     * @return \App\Models\Auth\User
     */
    public function unconfirm(User $user): User
    {
        if (!$user->confirmed) {
            throw new GeneralException(__('exceptions.backend.access.users.not_confirmed'));
        }

        if ($user->id === 1) {
            // Cant un-confirm admin
            throw new GeneralException(__('exceptions.backend.access.users.cant_unconfirm_admin'));
        }

        if ($user->id === auth()->id()) {
            // Cant un-confirm self
            throw new GeneralException(__('exceptions.backend.access.users.cant_unconfirm_self'));
        }

        $user->confirmed = false;
        $unconfirmed = $user->save();

        if ($unconfirmed) {
            event(new UserUnconfirmed($user));

            return $user;
        }

        throw new GeneralException(__('exceptions.backend.access.users.cant_unconfirm'));
    }

    /**
     * @param \App\Models\Auth\User $user
     *
     * @throws GeneralException
     * @throws \Exception
     * @throws \Throwable
     * @return \App\Models\Auth\User
     */
    public function forceDelete(User $user)
    {
        if ($user->deleted_at === null) {
            throw new GeneralException(__('exceptions.backend.access.users.delete_first'));
        }

        return DB::transaction(function () use ($user) {
            // Delete associated relationships
            $user->passwordHistories()->delete();
            $user->providers()->delete();

            if ($user->forceDelete()) {
                event(new UserPermanentlyDeleted($user));

                return true;
            }

            throw new GeneralException(__('exceptions.backend.access.users.delete_error'));
        });
    }

    /**
     * @param \App\Models\Auth\User $user
     *
     * @throws GeneralException
     * @return \App\Models\Auth\User
     */
    public function restore(User $user): User
    {
        if ($user->deleted_at === null) {
            throw new GeneralException(__('exceptions.backend.access.users.cant_restore'));
        }

        if ($user->restore()) {
            event(new UserRestored($user));

            return $user;
        }

        throw new GeneralException(__('exceptions.backend.access.users.restore_error'));
    }

    /**
     * @param  $input
     *
     * @return mixed
     */
    protected function createUserStub($input)
    {
        $user = self::MODEL;
        $user = new $user();
        $user->first_name = $input['first_name'];
        // $user->last_name = $input['last_name'];
        $user->question_id = $input['question_id'];
        $user->answer = $input['answer'];
        $user->email = $input['email'];
        $user->password = bcrypt($input['password']);
        $user->status = isset($input['status']) ? 1 : 0;
        $user->confirmation_code = md5(uniqid(mt_rand(), true));
        $user->confirmed = isset($input['confirmed']) ? 1 : 0;
        $user->created_by = null;

        return $user;
    }

    /**
     * @param  $roles
     *
     * @throws GeneralException
     */
    protected function checkUserRolesCount($roles)
    {
        //User Updated, Update Roles
        //Validate that there's at least one role chosen
        if (count($roles) == 0) {
            throw new GeneralException(__('exceptions.backend.access.users.role_needed'));
        }
    }

    /**
     * @return mixed
     */
    public function getUnconfirmedCount(): int
    {
        return $this->query()
            ->where('confirmed', false)
            ->count();
    }

    /**
     * @param int    $paged
     * @param string $orderBy
     * @param string $sort
     *
     * @return mixed
     */
    public function getActivePaginated($paged = 25, $orderBy = 'created_at', $sort = 'desc'): LengthAwarePaginator
    {
        return $this->query()
            ->with('roles', 'permissions', 'providers')
            ->active()
            ->orderBy($orderBy, $sort)
            ->paginate($paged);
    }

    /**
     * @param int    $paged
     * @param string $orderBy
     * @param string $sort
     *
     * @return LengthAwarePaginator
     */
    public function getInactivePaginated($paged = 25, $orderBy = 'created_at', $sort = 'desc'): LengthAwarePaginator
    {
        return $this->query()
            ->with('roles', 'permissions', 'providers')
            ->active(false)
            ->orderBy($orderBy, $sort)
            ->paginate($paged);
    }

    /**
     * @param int    $paged
     * @param string $orderBy
     * @param string $sort
     *
     * @return LengthAwarePaginator
     */
    public function getDeletedPaginated($paged = 25, $orderBy = 'created_at', $sort = 'desc'): LengthAwarePaginator
    {
        return $this->query()
            ->with('roles', 'permissions', 'providers')
            ->onlyTrashed()
            ->orderBy($orderBy, $sort)
            ->paginate($paged);
    }

    // -------------
    public function createOrUpdatePackage($data)
    {
        Package::updateOrCreate(["user_id" => $data['user_id']], $data);
    }

    public function deletePackage($data)
    {
        $package = Package::where(["user_id" => $data['user_id']])->first();
        $package->delete();
    }
}
