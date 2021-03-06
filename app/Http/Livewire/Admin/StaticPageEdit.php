<?php

namespace App\Http\Livewire\Admin;

use App\Http\Livewire\AppComponent;
use App\Models\StaticPage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class StaticPageEdit extends AppComponent
{
    public $listeners = ['deleteConfirmed'];

    private $positions = ['left', 'bottom', 'hidden'];

    public function mount($staticPage = false) {
        $this->state['position'] = 'left';
        $this->state['status'] = 0;

        if($staticPage) {
            $page = StaticPage::whereId($staticPage)->firstOrFail();
            if($page) {
                $this->state = $page->toArray();
                if(count($this->state['translations'])) {
                    foreach($this->state['translations'] as $translation) {
                        $this->state['lang'][$translation['locale']] = [
                            'title' => $translation['title'],
                            'content' => $translation['content'],
                        ];
                        
                    }
                }
            } 
        }
    }

    public function editPage() {
        $validatedData = Validator::make($this->state, [
            'status' => 'required|numeric|in:0,1,2,3',
            'slug' => 'required|alpha|unique:static_pages,slug'.(isset($this->state['id']) ? ','.$this->state['id'] : '' ),
            'icon' => 'sometimes',
            'position' => 'required|string|in:'.implode(",", $this->positions),
        ])->validate();

        $validatedData['user_id'] = Auth::id();
        if(isset($this->state['lang'])) {
            foreach($this->state['lang'] as $code => $fields) {
                $validatedData[$code] = $fields;
            }
        }

        if(isset($this->state['id'])) {
            $page = StaticPage::findOrFail($this->state['id']);
            $page->update($validatedData);
            Session::flash('message', __('staticpage.edited')); 
        } else {
            StaticPage::create($validatedData);
            Session::flash('message', __('staticpage.created')); 
        }
        $this->clearCache();
        redirect()->route('admin.staticpages');
    }

    public function checkSlug() {
        if(isset($this->state['slug'])) {
            $this->state['slug'] = Str::slug($this->state['slug'], '-');
        }
    }

    public function confirmNewDelete() {
        $this->dispatchBrowserEvent('show-pageDelete-confirmation');
    }

    public function deleteConfirmed() {
        if(isset($this->state['id'])) {
            StaticPage::findOrFail($this->state['id'])->delete();
            Session::flash('message', __('staticpage.confirmDelete.success')); 
        } else {
            Session::flash('message', __('staticpage.confirmDelete.error')); 
        }
        $this->clearCache();
        redirect()->route('admin.staticpages');
    }

    /**
     * Clear sidemenu cache
     */
    private function clearCache() {
        cache()->forget('sidemenu_auth');
        cache()->forget('sidemenu_guest');
    }

    public function render()
    {

        $statuses = range(0,3,1);

        return view('livewire.admin.static-page-edit', [
            'statuses' => $statuses,
            'positions' => $this->positions,
            'languages' => config('available_languages')
        ]);
    }
}
