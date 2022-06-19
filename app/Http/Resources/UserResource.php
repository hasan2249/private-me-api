<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\Resource;
use Yajra\DataTables\Facades\DataTables;
use App\Models\Auth\Frindship;
use App\Models\Auth\User;

class UserResource extends Resource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     *
     * @return array
     */
    public function toArray($request)
    {
        $freiends_1 = Frindship::where('fisrt_user_id', $this->id)->get(['second_user_id AS id']);
        $freiends_2 = Frindship::where('second_user_id', $this->id)->get(['fisrt_user_id AS id']);
        $friends = [];
        foreach ($freiends_1 as $friend) {
            array_push($friends, $friend->id);
        }

        foreach ($freiends_2 as $friend) {
            array_push($friends, $friend->id);
        }

        $all_friends = User::find($friends);


        //who asked my for frien relation
        $friend_requests_1 = $this->friends2->where('accept', 0)->toArray();
        $friend_requests = Datatables::of($friend_requests_1)
            ->addColumn('image', function ($friend) {
                return $friend->user2->first()->avatar_location;
            })
            ->addColumn('username', function ($friend) {
                return $friend->user2->first()->first_name;
            })->make();

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            // 'last_name' => $this->last_name,
            'email' => $this->email,
            'picture' => $this->avatar_location,
            'question_id' => $this->question_id,
            'answer' => $this->answer,
            'confirmed' => $this->confirmed,
            'role' => optional($this->roles()->first())->name,
            //'permissions' => $this->permissions()->get(),
            'friends' => $all_friends,
            'friend-requests' => $friend_requests,
            'status' => $this->status,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
