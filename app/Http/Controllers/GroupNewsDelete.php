<?php

namespace App\Http\Controllers;

use App\Models\GroupNews;
use App\Models\GroupNewsFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;

class GroupNewsDelete extends Controller
{
    public function delete($group, $new) {

        $news = GroupNews::firstWhere('id', $new);

        $files = $news->files()->get()->toArray();
        if(count($files)) {
            foreach($files as $file) {
                if(GroupNewsFile::find($file['id'])->delete()) {
                    if (Storage::disk('news_files')->exists($file['file'])) {
                        Storage::disk('news_files')->delete($file['file']);
                    }
                }
            }
        }
        $res = $news->delete();
        if($res) {
            Session::flash('message', __('news.confirmDelete.success'));
        } else {
            Session::flash('message', __('news.confirmDelete.error')); 
        }

        return redirect()->route('groups.news', ['group' => $group]);
    }
}
