<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Blog\ArticleOutline;
use App\Support\Blog\BlogClient;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * De blog (besluit Raph, 2 november 2026): berichten uit het CMS van
 * Eazyonline, getoond in de huisstijl van Aucvy.
 */
final class BlogController extends Controller
{
    public function index(Request $request, BlogClient $blog): View
    {
        $page = max(1, min(500, (int) $request->query('pagina', '1')));

        return view('blog.index', [
            ...$blog->posts($page),
            'configured' => $blog->configured(),
        ]);
    }

    public function show(string $slug, BlogClient $blog): View
    {
        $post = $blog->find($slug);

        abort_if($post === null, 404);

        return view('blog.show', ['post' => $post, 'outline' => ArticleOutline::of($post['content'])]);
    }
}
