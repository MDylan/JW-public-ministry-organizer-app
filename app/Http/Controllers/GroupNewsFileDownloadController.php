<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupNewsFile;
// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class GroupNewsFileDownloadController extends Controller
{
    public function download($group, GroupNewsFile $file)
    {
        if (Storage::disk('news_files')->exists($file->file)) {
            return Storage::disk('news_files')->download($file->file, $file->name);
        } else {
            return abort('404');
        }
    }
}
