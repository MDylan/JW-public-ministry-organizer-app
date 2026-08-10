<?php

namespace App\Http\Controllers;

use App\Models\GroupNewsFile;
use Illuminate\Support\Facades\Storage;

class GroupNewsFileDownloadController extends Controller
{
    /**
     * Downloading a group news attachment.
     *
     * The `groupMember` middleware (App\Http\Middleware\GroupMember) looks
     * EXCLUSIVELY at the route's `{group}` parameter: it verifies that the requester is a
     * member of THAT group. The `{file}`, however, binds to the model by a plain
     * identifier, and the controller previously never checked whether the file
     * belonged to that group.
     *
     * Consequence: any accepted member of any group could download
     * ANY other group's private news attachment - all it took was supplying their own
     * group ID together with someone else's file ID. The files sit on the
     * `news_files` private disk, outside the docroot, so this was the
     * only path to them - and it was left open.
     *
     * The response is 404, not 403: a 403 would confirm that a file with
     * that ID exists.
     */
    public function download($group, GroupNewsFile $file)
    {
        $new = $file->new()->first();

        if ($new === null || (string) $new->group_id !== (string) $group) {
            abort(404);
        }

        if (Storage::disk('news_files')->exists($file->file)) {
            return Storage::disk('news_files')->download($file->file, $file->name);
        } else {
            return abort('404');
        }
    }
}
