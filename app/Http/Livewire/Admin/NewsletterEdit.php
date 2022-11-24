<?php

namespace App\Http\Livewire\Admin;

use App\Models\AdminNewsletter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;

class NewsletterEdit extends Component
{

    public $state = [];

    public $listeners = ['deleteConfirmed'];

    public function mount($id = false) {
        if($id) {
            $this->state = AdminNewsletter::findOrFail($id)->toArray();
            if(count($this->state['translations'])) {
                foreach($this->state['translations'] as $translation) {
                    $this->state['lang'][$translation['locale']] = [
                        'subject' => $translation['subject'],
                        'content' => $translation['content'],
                    ];                    
                }
            }
        } else {
            $this->state = [
                'status' => 0,
                'send_to' => 'groupServants',
                'send_newsletter' => 0
            ];
        }
    }

    public function editNewsletter() {
        $validatedData = Validator::make($this->state, [
            'date' => 'required|date_format:Y-m-d',
            'status' => 'required|numeric|in:0,1',
            'send_to' => 'required|string|in:groupCreators,groupAdmins,groupServants',
            'send_newsletter' => 'required|numeric|in:0,1',
        ])->validate();

        $validatedData['user_id'] = Auth::id();
        if(isset($this->state['lang'])) {
            foreach($this->state['lang'] as $code => $fields) {
                $validatedData[$code] = $fields;
            }
        }

        if(isset($this->state['id'])) {
            AdminNewsletter::findorFail($this->state['id'])->update($validatedData);
            Session::flash('message', __('news.edited')); 
        } else {
            $ret = AdminNewsletter::create($validatedData);
            Session::flash('message', __('news.created')); 
        } 

        redirect()->route('newsletters');
    }

    public function confirmNewDelete() {
        $this->dispatchBrowserEvent('show-newsDelete-confirmation');
    }

    public function deleteConfirmed() {
        if(isset($this->state['id'])) {
            AdminNewsletter::whereId($this->state['id'])->delete();
            Session::flash('message', __('news.confirmDelete.success')); 
        } else {
            Session::flash('message', __('news.confirmDelete.error')); 
        }
        return redirect()->route('newsletters');
    }

    public function render()
    {
        return view('livewire.admin.newsletter-edit', [
            'languages' => config('available_languages')
        ]);
    }
}
