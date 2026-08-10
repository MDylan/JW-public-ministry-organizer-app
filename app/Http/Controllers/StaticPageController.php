<?php

namespace App\Http\Controllers;

use App\Models\StaticPage;
use Illuminate\Support\Facades\Auth;

class StaticPageController extends Controller
{
    static function render($slug) {

        $page = StaticPage::where('slug', '=', $slug)->first();
        if(isset($page->id)) {
            //draft, only admin can see this
            //Without Auth::check() can() would be called on null: the route is public
            //(web.php:62), and '/' runs with the guest middleware, so here the
            //guest is the normal case, not the exception. As a guest, the elseif chain
            //runs through to the else, which gives 403 - and home-404 for the 'home' slug.
            if($page->status === 0 && Auth::check() && Auth::user()->can('is-admin')) {
                return view('layouts.staticpage', ['page' => $page]);
            } 
            //public, anyone can see
            elseif($page->status === 1) {
                if(Auth::id()) {
                    return view('layouts.staticpage', ['page' => $page]);
                } else {
                    return view('main', ['page' => $page]);
                }
            } 
            //only public users see this
            elseif($page->status === 2) {
                if(Auth::id()) {
                    abort(403);
                } else {
                    return view('main', ['page' => $page]);
                }
            }
            //only logged in users can see
            elseif($page->status === 3 && Auth::id()) {
                return view('layouts.staticpage', ['page' => $page]);
            } else {
                //there are some mistake
                if($slug === 'home') {
                    return view('home-404');
                }
                abort(403);
            }
        } else {
            if($slug === 'home') {
                return view('home-404');
            }
            abort(404);
        }
    }
}
