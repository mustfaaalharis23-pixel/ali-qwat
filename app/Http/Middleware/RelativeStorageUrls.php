<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class RelativeStorageUrls
{
    /**
     * The admin application is served from the same site as the files, so the links to the public disk it receives are
     * relative (/storage/...): they work on whatever host and port the site is opened with, whatever APP_URL says.
     * Print pages and PDFs keep their full links.
     */
    public function handle(Request $request, Closure $next): Response
    {
        config(['filesystems.disks.public.url' => '/storage']);
        Storage::forgetDisk('public');

        return $next($request);
    }
}
