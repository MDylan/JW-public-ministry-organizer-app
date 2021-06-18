<?php

namespace App\Http\Livewire\Groups;

use App\Http\Livewire\AppComponent;
use App\Models\Group;
use App\Models\GroupNewsFile;
// use App\Models\GroupNews;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Livewire\WithFileUploads;

class NewsEdit extends AppComponent
{
    use WithFileUploads;

    public $group;
    public $new_data;
    public $files = [];
    public $file_types = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];
    public $file_beeingRemoved = null;
    public $removed_files = [];
    public $attached_files = [];

    public $listeners = [
        'deleteConfirmed',
        'deleteFileConfirmed'
    ];

    public function mount(Group $group, $new = false) {
        $this->group = $group;

        if($new) {
            $this->new_data = $this->group->news()->with('files')->whereId($new)->firstOrFail();
            $this->state = $this->new_data->toArray();
            $this->attached_files = $this->state['files']; // $files;
        }
    }

    public function editNews() {
        // dd($this->state);
        
        if(isset($this->state['title']))
            $this->state['title'] = strip_tags($this->state['title']);

        $validatedData = Validator::make($this->state, [
            'title' => 'required|string|max:50|min:2',
            'date' => 'required|date_format:Y-m-d',
            'status' => 'numeric|in:0,1',
            'content' => 'required',
        ])->validate();

        $validatedData['user_id'] = Auth::id();

        if(isset($this->state['id'])) {
            $this->new_data->update($validatedData);
            $newId = $this->new_data->id;
            Session::flash('message', __('news.edited')); 
        } else {
            $ret = $this->group->news()->create($validatedData);
            $newId = $ret->id;
            Session::flash('message', __('news.created')); 
        }        

        //store new files
        if($this->attached_files) {
            $file_data = [];
            foreach($this->attached_files as $file) {
                if(isset($file['new'])) {
                    $file_data = [
                        'group_new_id' => $newId,
                        'name' => $file['name'],
                        'file' => $file['file']->store('/', 'news_files')
                    ];
                    GroupNewsFile::create($file_data);
                }
            }
        }
        //delete removed files
        if(count($this->removed_files)) {
            foreach($this->removed_files as $file) {
                if(GroupNewsFile::find($file['id'])->delete()) {
                    if (Storage::disk('news_files')->exists($file['file'])) {
                        Storage::disk('news_files')->delete($file['file']);
                    }
                }
            }
        }

        redirect()->route('groups.news', ['group' => $this->group->id]);
    }

    public function confirmNewDelete() {
        $this->dispatchBrowserEvent('show-newsDelete-confirmation');
    }

    public function deleteConfirmed() {
        if(isset($this->state['id'])) {
            $this->new_data->delete();
            Session::flash('message', __('news.confirmDelete.success')); 
        } else {
            Session::flash('message', __('news.confirmDelete.error')); 
        }
        redirect()->route('groups.news', ['group' => $this->group->id]);
    }

    public function updatedFiles() {
        if( !empty( $this->files ) ) {

            $this->validate(
                ['files.*' => 'mimes:'.implode(',', $this->file_types).'|max:2048',],
                [
                    'files.*.mimes' => __('news.file.wrong_type'),
                    'files.*.max' => __('news.file.wrong_size'),
                ], 
                // [ 'files.*' => 'Fajlnev' ]
            );

            foreach( $this->files as $file ) {
                $file_data = [
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'url'  => $file->temporaryUrl(),
                    'new'  => true,
                    'file' => $file
                ];
                $this->attached_files[] = $file_data;
            }
        }
    }

    public function confirmFileDelete($fileId, $fileName) {
        $this->file_beeingRemoved = $fileId;
        $this->dispatchBrowserEvent('show-fileDelete-confirmation', ['fileName' => $fileName]);
    }

    public function deleteFileConfirmed() {
        if(isset($this->attached_files[$this->file_beeingRemoved]['id'])) {
            $this->removed_files[] = $this->attached_files[$this->file_beeingRemoved];
        }
        unset($this->attached_files[$this->file_beeingRemoved]);
        $this->file_beeingRemoved = null;
        
    }

    public function render()
    {
        return view('livewire.groups.news-edit', [
            // 'group' => $this->group
        ]);
    }
}
