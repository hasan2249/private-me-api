<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\GeneralException;
use App\Http\Requests\Backend\Auth\User\ManageUserRequest;
use App\Http\Requests\Backend\Auth\User\StoreUserRequest;
use App\Http\Requests\Backend\Auth\User\UpdateUserRequest;
use App\Http\Requests\Backend\Api\ApiRequest;
use App\Http\Resources\UserResource;
use App\Models\Auth\User;
use App\Models\Auth\Package;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Repositories\Backend\Auth\UserRepository;
use Validator;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Exists;

define('_500MB', 500000000);

/**
 * @group Authentication
 *
 * Class AuthController
 *
 * Fullfills all aspects related to authenticate a user.
 */
class UserController extends APIController
{
    protected $repository;
    /**
     * __construct.
     *
     * @param $repository
     */
    public function __construct(UserRepository $repository)
    {
        $this->repository = $repository;
    }

    public function index(ApiRequest $request)
    {
        $collection = $this->repository->retrieveList($request->all());

        return UserResource::collection($collection);
    }

    public function show(ApiRequest $request, User $user)
    {
        return new UserResource($user);
    }

    public function addFriend(ApiRequest $request, $friend_id)
    {
        $is_demand = $this->repository->addFriend($friend_id);

        if ($is_demand == 1) {
            return response()->json(["msg" => "you are friends", "success" => true]);
        } else if ($is_demand == 0) {
            return response()->json(["msg" => "wait for acceptance", "success" => false]);
        } else {
            return response()->json(["msg" => "you asked before, wait for acceptance", "success" => false]);
        }
    }

    public function acceptFriend(ApiRequest $request, $friend_id)
    {
        $res = $this->repository->acceptFriend($friend_id);
        if ($res == 1) {
            return response()->json(["msg" => "you become friends", "success" => true]);
        } else {
            return response()->json(["msg" => "wait for acceptance", "success" => false]);
        }
    }

    public function cancelFriend(ApiRequest $request, $friend_id)
    {
        $this->repository->cancelFriend($friend_id);
        return response()->json(["msg" => "you canceled friendship", "success" => true]);
    }

    public function checkFriendRequest(ApiRequest $request)
    {
        $friend_request = $this->repository->checkFriendRequest();
        return $friend_request;
    }

    public function store(StoreUserRequest $request)
    {
        $request->last_name = ' ';
        $user = $this->repository->create($request->validated(), $request->file('avatar_location'));

        $images_folder = __DIR__ . '/../../../../../storage/app/public/' . $user->id . '/' . $request->type . '/Images/Default';
        $files_folder = __DIR__ . '/../../../../../storage/app/public/' . $user->id . '/' . $request->type . '/Files/Default';
        $videos_folder = __DIR__ . '/../../../../../storage/app/public/' . $user->id . '/' . $request->type . '/Videos/Default';

        if (!file_exists($images_folder)) {
            File::makeDirectory($images_folder, 0777, true, true);
        }

        if (!file_exists($files_folder)) {
            File::makeDirectory($files_folder, 0777, true, true);
        }

        if (!file_exists($videos_folder)) {
            File::makeDirectory($videos_folder, 0777, true, true);
        }

        return (new UserResource($user))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function readNotification(ApiRequest $request)
    {
        $this->repository->readNotification($request->id);
    }

    public function uploadFile(ApiRequest $request)
    {
        $user_folder = storage_path('app/public/' . auth()->user()->id);
        $limit_size = _500MB;
        $user = Package::where('user_id', auth()->user()->id)->first();
        if ($user) {
            if ($user->size) {
                $limit_size = (int) $user->size;
            }
        }

        // Folder should be not more than 500MB
        if ($this->repository->getfolderSize($user_folder) >= $limit_size) {
            return '{"message": "Your free space has been excceded, please subscribe for more space." , "success":"false"}';
        }
        clearstatcache();
        $files = $request->file('files');
        $paths = "";
        if (isset($files)) {
            foreach ($files as $file) {
                $fileName = $file->getClientOriginalName();
                $paths .= \Storage::putFileAs('public/' . auth()->user()->id . '/Files/' . $request->folder_name, $file, $fileName);
                $paths .= ",";
            }

            return '{"message": "uploaded successfully"}';
        } else {
            return '{"message": "Error, there is no file to upload"}';
        }
    }

    public function getAllFilesInDir(ApiRequest $request, $type)
    {
        $file = '';
        $link = '';
        if (isset($type)) {
            $file = __DIR__ . '/../../../../../storage/app/public/' . auth()->user()->id . '/' . $type;
            $link = 'storage/' . auth()->user()->id . '/' . $type . "/";
        } else {
            $file = __DIR__ . '/../../../../../storage/app/public/' . auth()->user()->id;
            $link = 'storage/' . auth()->user()->id . "/";
        }
        if (file_exists($file)) {
            // $files = [];
            $filess = [];
            $count = 0;

            $all_files = array();
            foreach (scandir($file) as $fil) {
                if ($fil != '.' && $fil != '..') {

                    // Files count in Folder
                    $directory = $file . "/" . $fil;
                    $files2 = glob($directory . "/*");
                    if ($files2) {
                        $count += count($files2);

                        // return 4 files from directory
                        $internal_files = scandir($directory);
                        foreach ($internal_files as $internal_file) {
                            if ($internal_file == '.' || $internal_file == '..') {
                                continue;
                            }
                            $file_ = asset($link . $fil . '/' . ($internal_file ?? null)); // because [0] = "." [1] = ".." 
                            array_push($all_files, array('file' => $file_, 'date' => date("F d Y H:i:s.", filemtime($file . '/' . $fil))));
                        }

                        // array_push($files, array('file' => $fil, 'link' => asset($link . $fil), 'count' => $filecount, 'files' => $all_files, 'date' => date("F d Y H:i:s.", filemtime($file . '/' . $fil))));
                    }
                }
            }
            $filess +=  ["total" => $count];
            $filess += ["data" => $all_files];
            return response()->json($filess);
        }
        return response()->json(["message" => "Folder Not Found!"]);
    }

    public function getFiles(ApiRequest $request)
    {
        $file = '';
        $link = '';
        if (isset($request->path)) {
            $file = __DIR__ . '/../../../../../storage/app/public/' . auth()->user()->id . '/' . $request->path;
            $link = 'storage/' . auth()->user()->id . '/' . $request->path . "/";
        } else {
            $file = __DIR__ . '/../../../../../storage/app/public/' . auth()->user()->id;
            $link = 'storage/' . auth()->user()->id . "/";
        }
        if (file_exists($file)) {
            $files = [];
            $filess = [];
            $count = 0;
            foreach (scandir($file) as $fil) {
                if ($fil != '.' && $fil != '..') {

                    // Files count in Folder
                    $directory = $file . "/" . $fil;
                    $files2 = glob($directory . "/*");
                    if ($files2) {
                        $filecount = count($files2);

                        // return 4 files from directory
                        $internal_files = scandir($directory);
                        $file_1 = asset($link . $fil . '/' . ($internal_files[2] ?? null)); // because [0] = "." [1] = ".." 
                        $file_2 = asset($link . $fil . '/' . ($internal_files[3] ?? null));
                        $file_3 = asset($link . $fil . '/' . ($internal_files[4] ?? null));
                        $file_4 = asset($link . $fil . '/' . ($internal_files[5] ?? null));

                        $four_files = [$file_1, $file_2, $file_3, $file_4];
                        array_push($files, array('file' => $fil, 'link' => asset($link . $fil), 'count' => $filecount, 'internal_files' => $four_files, 'date' => date("F d Y H:i:s.", filemtime($file . '/' . $fil))));
                    } else {
                        $filecount = 0;
                        array_push($files, array('file' => $fil, 'link' => asset($link . $fil), 'count' => 0, 'internal_files' => [],  'date' => date("F d Y H:i:s.", filemtime($file . '/' . $fil))));
                    }

                    $count++;
                }
            }
            $filess +=  ["total" => $count];
            $filess += ["data" => $files];
            return response()->json($filess);
        }
        return response()->json(["message" => "Folder Not Found!"]);
    }

    public function DeleteFiles(ApiRequest $request)
    {
        if (\Hash::check($request->password, auth()->user()->password)) {
            foreach ($request->paths as $path) {
                $path_ = __DIR__ . '/../../../../../storage/app/public/' . auth()->user()->id . '/' . $path;
                if (!is_dir($path_)) {
                    // file
                    file_exists($path_) ? unlink($path_) : null;
                    return response()->json(["message" =>  "removed successfully"]);
                } else {
                    // Directory
                    Storage::deleteDirectory('public/' . auth()->user()->id . '/' . $path);
                    return response()->json(["message" => "removed successfully"]);
                }
            }
        } else {
            return response()->json(["message" => "Password mismatch"], 422);
        }
    }

    public function getstatistic(ApiRequest $request)
    {
        $images_count = 0;
        $images_path = __DIR__ . '/../../../../../storage/app/public/' . auth()->user()->id . '/Images';
        // if (file_exists($images_path)) {
        //     $images = new \FilesystemIterator($images_path, \FilesystemIterator::SKIP_DOTS);
        //     $images_count = iterator_count($images);
        // }

        $files_count = 0;
        $files_path = __DIR__ . '/../../../../../storage/app/public/' . auth()->user()->id . '/Files';
        // if (file_exists($files_path)) {
        //     $files = new \FilesystemIterator($files_path, \FilesystemIterator::SKIP_DOTS);
        //     $files_count = iterator_count($files);
        // }

        $videos_count = 0;
        $videos_path = __DIR__ . '/../../../../../storage/app/public/' . auth()->user()->id . '/Videos';
        // if (file_exists($videos_path)) {
        //     $videos = new \FilesystemIterator($videos_path, \FilesystemIterator::SKIP_DOTS);
        //     $videos_count = iterator_count($videos);
        // }

        $images_count = $this->repository->getInternalFilesCount($images_path);
        $files_count = $this->repository->getInternalFilesCount($files_path);
        $videos_count = $this->repository->getInternalFilesCount($videos_path);

        return response()->json([
            "images" => $images_count,
            "files" => $files_count,
            "videos" => $videos_count,
            "friends" => count(auth()->user()->getMyFriends())
        ]);
    }

    public function uploadImage(ApiRequest $request)
    {
        $user_folder = storage_path('app/public/' . auth()->user()->id);
        $limit_size = _500MB;
        $user = Package::where('user_id', auth()->user()->id)->first();
        if ($user) {
            $limit_size = (int) $user->size;
        }
        // Folder should be not more than 500MB
        if ($this->repository->getfolderSize($user_folder) >= $limit_size) {
            return '{"message": "Your free space has been excceded, please subscribe for more space.", "success":"false"}';
        }
        clearstatcache();

        $images = $request->file('images');
        $paths = "";
        if (isset($images)) {
            foreach ($images as $image) {
                $fileName = $image->getClientOriginalName();
                $paths .= \Storage::putFileAs('public/' . auth()->user()->id . '/Images/' . $request->folder_name, $image, $fileName);
                $paths .= ",";
            }
            return '{"message": "uploaded successfully"}';
        } else {
            return '{"message": "Error, there is no file to upload"}';
        }
    }

    public function uploadVedio(ApiRequest $request)
    {
        $user_folder = storage_path('app/public/' . auth()->user()->id);
        $limit_size = _500MB;
        $user = Package::where('user_id', auth()->user()->id)->first();
        if ($user) {
            $limit_size = (int) $user->size;
        }
        // Folder should be not more than 500MB
        if ($this->repository->getfolderSize($user_folder) >= $limit_size) {
            return '{"message": "Your free space has been excceded, please subscribe for more space.", "success":"false"}';
        }
        clearstatcache();

        $videos = $request->file('videos');
        $paths = "";
        if (isset($videos)) {
            foreach ($videos as $vedio) {
                $fileName = $vedio->getClientOriginalName();
                $paths .= \Storage::putFileAs('public/' . auth()->user()->id . '/Videos/' . $request->folder_name, $vedio, $fileName);
                $paths .= ",";
            }

            return '{"message": "uploaded successfully"}';
        } else {
            return '{"message": "Error, there is no file to upload"}';
        }
    }

    public function createFolder(ApiRequest $request)
    {
        $folder = __DIR__ . '/../../../../../storage/app/public/' . auth()->user()->id . '/' . $request->type . '/' . $request->folder_name;
        if (!file_exists($folder)) {
            File::makeDirectory($folder, 0777, true, true);

            return '{"message": "Folder created successfully"}';
        } else {
            return '{"message": "Folder already exist"}';
        }
    }

    public function renameFolder(ApiRequest $request)
    {
        $gg = __DIR__ . '/../../../../../storage/app/public/' . auth()->user()->id . '/' .  $request->path;
        $folder = 'public/' . auth()->user()->id . '/' .  $request->path;
        $old_name = $folder . '/' . $request->old_name;
        if (file_exists($gg)) {
            \Storage::move($old_name, $folder . '/' . $request->new_name);

            return '{"message": "Folder renamed successfully", "success" : true}';
        } else {
            return '{"message": ' . storage_path() . ', "success" : false}';
        }
    }

    public function searchUser(ApiRequest $request, $user_name)
    {
        $collection = $this->repository->RetrieveSearchList([], $user_name);

        return $collection;
    }

    public function updateMyInfo(Request $request)
    {
        $user = User::find(auth()->user()->id);
        $user_ = $this->repository->update($user, $request->toArray(), $request->file('avatar_location'));

        return new UserResource($user_);
    }

    public function destroyAccount(ApiRequest $request)
    {
        $user = auth()->user();

        $this->repository->delete($user, $request->password);

        $dir = __DIR__ . '/../../../../../storage/app/public/' . auth()->user()->id;
        $it = new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator(
            $it,
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        rmdir($dir);
        return response()->json(["msg" => "Your Account removed", "success" => true]);
    }

    // --------------------
    public function createOrUpdatePackage(ApiRequest $request)
    {
        $this->repository->createOrUpdatePackage($request->all());

        return response()->json(["msg" => "created successfully", "success" => true]);
    }

    public function deletePackage(ApiRequest $request)
    {
        $this->repository->deletePackage($request->all());

        return response()->json(["msg" => "user package deleted successfully", "success" => true]);
    }
}
