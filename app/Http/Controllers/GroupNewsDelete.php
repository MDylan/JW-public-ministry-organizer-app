<?php

namespace App\Http\Controllers;

use App\Models\GroupNews;
use App\Models\GroupNewsFile;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;

class GroupNewsDelete extends Controller
{
    public function delete($group, $new) {

        // Through the group (roadmap TODO 70): groupAdmin vouches for
        // {group} only, and {new} used to be looked up in every group.
        $news = GroupNews::where('group_id', $group)->findOrFail($new);

        foreach($news->files()->get() as $file) {
            if($file->delete()) {
                if (Storage::disk('news_files')->exists($file->file)) {
                    Storage::disk('news_files')->delete($file->file);
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
