<?php

namespace App\Http\Controllers;

use App\Models\Page;

class LegalController extends Controller
{
    public function terms()
    {
        $content = Page::where('type', 'terms')->first()?->content ?? [];
        $siteName = config('app.name');
        return view('frontend.legal.terms', compact('content', 'siteName'));
    }

    public function privacy()
    {
        $content = Page::where('type', 'privacy')->first()?->content ?? [];
        $siteName = config('app.name');
        return view('frontend.legal.privacy', compact('content', 'siteName'));
    }

    public function refund()
    {
        $content = Page::where('type', 'refund')->first()?->content ?? [];
        $siteName = config('app.name');
        return view('frontend.legal.refund', compact('content', 'siteName'));
    }

    public function contact()
    {
        $content = Page::where('type', 'contact')->first()?->content ?? [];
        return view('frontend.legal.contact-page', compact('content'));
    }

    public function about()
    {
        $content = Page::where('type', 'about')->first()?->content ?? [];
        return view('frontend.legal.about', compact('content'));
    }

    public function whyUs()
    {
        $content = Page::where('type', 'why_us')->first()?->content ?? [];
        return view('frontend.legal.why-us', compact('content'));
    }
}
