<?php

namespace App\Http\Controllers\Landing;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NewsletterController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('newsletters/index', [
            'newsletters' => TableQuery::paginate(NewsletterSubscriber::query(), $request, ['email'], ['email', 'created_at']),
            'filters' => TableQuery::filters($request),
        ]);
    }

    public function destroy(NewsletterSubscriber $newsletter): RedirectResponse
    {
        $newsletter->delete();

        return $this->done(__('Subscriber deleted successfully.'));
    }
}
