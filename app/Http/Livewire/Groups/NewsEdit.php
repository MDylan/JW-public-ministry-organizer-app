<?php

namespace App\Http\Livewire\Groups;

use App\Http\Livewire\AppComponent;
use App\Http\Livewire\Concerns\AuthorizesGroupAccess;
use App\Models\Group;
use App\Models\GroupNews;
use App\Models\GroupNewsFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Livewire\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use App\Support\Html\SafeHtml;
use App\Http\Livewire\Concerns\LocksProperties;
use App\Support\Auth\ResumedAction;

/**
 * Create and edit a group's news.
 *
 * Roadmap TODO 70: the group, the news item and the file lists are
 * locked (LocksProperties), the editor role is re-checked on every request (hydrate()),
 * and what is updated or deleted is always the news item loaded through the
 * group - never an id from the form's state. Before, an admin of one group
 * could set groupId/newId/state.id to create, edit or delete another
 * group's news, and removed_files to delete any group's attachments.
 */
class NewsEdit extends AppComponent
{
    use LocksProperties;

    // Server-owned; the client may not write them (TODO 70.1).
    protected $lockedProperties = ['newId', 'groupId', 'file_types', 'file_beeingRemoved', 'removed_files', 'attached_files'];
    use AuthorizesGroupAccess;
    use WithFileUploads;

    private $group;
    public $newId = false;
    public $groupId;
    private $new_data;
    public $files = [];
    public $file_types = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];
    public $file_beeingRemoved = null;
    public $removed_files = [];
    public $attached_files = [];
    private $languages = [];
    protected $resumeDelete = false;

    public $listeners = [
        'deleteConfirmed',
        'deleteFileConfirmed'
    ];

    public function mount($group, $new = false) {
        $this->authorizeGroupEditor($group);
        $this->groupId = $group;
        $this->newId = $new;
        $this->getGroup(true);

        // Back from the password confirmation a deletion asked for (roadmap TODO 70.1).
        $resumed = ResumedAction::take('groups.news_delete');
        $this->resumeDelete = $resumed !== null && $this->newId
            && (string) ($resumed['new'] ?? '') === (string) $this->newId
            && (string) ($resumed['group'] ?? '') === (string) $this->groupId;
    }

    /**
     * Every request after the first: the route's groupAdmin does not run
     * on component actions.
     */
    public function hydrate() {
        $this->authorizeGroupEditor($this->groupId);
    }

    public function getGroup($loadState = false) {
        $this->group = Group::findOrFail($this->groupId);

        $all_languages = config('available_languages');
        if($this->group->languages !== null) {
            foreach($this->group->languages as $code => $value) {
                if($value) {
                    $this->languages[$code] = $all_languages[$code];
                }
            } 
            if(count($this->languages) == 0) {
                $this->languages = $all_languages;
            }
        } else {
            $this->languages = $all_languages;
        }

        if($this->newId) {
            $this->new_data = $this->group->news()->with('files')->whereId($this->newId)->firstOrFail();
            
            if($loadState) {
                $this->state = $this->new_data->toArray();
                $this->attached_files = $this->state['files']; // $files;
                if(count($this->state['translations'])) {
                    foreach($this->state['translations'] as $translation) {
                        $this->state['lang'][$translation['locale']] = [
                            'title' => $translation['title'],
                            // Into Summernote's live editor: cleaned first (TODO 70).
                            'content' => SafeHtml::clean($translation['content']),
                        ];                    
                    }
                }
            }
        }
    }

    public function editNews() {
        $this->getGroup();

        $validatedData = Validator::make($this->state, [
            'date' => 'required|date_format:Y-m-d',
            'status' => 'numeric|in:0,1',
        ])->validate();

        $validatedData['user_id'] = Auth::id();
        if(isset($this->state['lang']) && is_array($this->state['lang'])) {
            // Only the group's languages, only the two translated fields: a
            // key like `group_id` would otherwise reach fill() as a column.
            foreach(array_intersect_key($this->state['lang'], $this->languages) as $code => $fields) {
                if(! is_array($fields)) continue;
                $validatedData[$code] = [
                    'title' => is_string($fields['title'] ?? null) ? $fields['title'] : '',
                    'content' => SafeHtml::clean(is_string($fields['content'] ?? null) ? $fields['content'] : ''),
                ];
            }
        }

        if($this->new_data !== null) {
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
                if(isset($file['new']) && ($file['file'] ?? null) instanceof TemporaryUploadedFile) {
                    $file_data = [
                        'group_new_id' => $newId,
                        'name' => $file['name'],
                        'file' => $file['file']->store('/', 'news_files')
                    ];
                    GroupNewsFile::create($file_data);
                }
            }
        }
        //delete removed files - only this news item's, by their stored path
        if(count($this->removed_files) && $this->new_data !== null) {
            foreach($this->removed_files as $file) {
                $stored = $this->new_data->files()->whereKey($file['id'] ?? null)->first();
                if($stored !== null && $stored->delete()) {
                    if (Storage::disk('news_files')->exists($stored->file)) {
                        Storage::disk('news_files')->delete($stored->file);
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
        $this->getGroup();
        $group_id = $this->groupId;
        // The news item loaded through the group, not state.id.
        if($this->new_data !== null) {
            foreach($this->new_data->files()->get() as $file) {
                if($file->delete()) {
                    if (Storage::disk('news_files')->exists($file->file)) {
                        Storage::disk('news_files')->delete($file->file);
                    }
                }
            }
            $this->new_data->delete();
            Session::flash('message', __('news.confirmDelete.success')); 
        } else {
            Session::flash('message', __('news.confirmDelete.error')); 
        }
        //this is because when we delete current model, livewire get an error exception
        $this->new_data = GroupNews::make();
        return redirect()->route('groups.news', ['group' => $group_id]);
    }

    public function updatedFiles() {
        if( !empty( $this->files ) ) {

            $this->validate(
                ['files.*' => 'mimes:'.implode(',', $this->file_types).'|max:2048',],
                [
                    'files.*.mimes' => __('news.file.wrong_type'),
                    'files.*.max' => __('news.file.wrong_size'),
                ], 
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
        if(!$this->group) {
            $this->getGroup();
        }

        return view('livewire.groups.news-edit', [
            'group' => $this->group,
            'new_data' => $this->new_data,
            'resumeDelete' => $this->resumeDelete,
            'languages' => $this->languages
        ]);
    }
}
