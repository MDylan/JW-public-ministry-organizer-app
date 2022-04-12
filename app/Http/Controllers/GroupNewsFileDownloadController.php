<?php

namespace App\Http\Controllers;

use App\Models\GroupNewsFile;
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
