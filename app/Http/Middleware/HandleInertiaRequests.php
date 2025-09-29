<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => Auth::user() ? [
                'user' => [
                    'id' => Auth::user()->id,
                    'name' => Auth::user()->name,
                    'role' => optional(Auth::user()->role)->name,
                    'role_id' => Auth::user()->role_id,
                ],
                'flash' => function () use ($request) {
                    $flash = $request->session()->get('flash');
                    $message = $request->session()->get('message');
                    // Nếu có flash là array thì luôn trả về type và message
                    if (is_array($flash)) {
                        return [
                            'type' => $flash['type'] ?? null,
                            'message' => $flash['message'] ?? $message,
                        ];
                    }
                    // Nếu chỉ có message thì mặc định type là null
                    if ($message) {
                        return [
                            'type' => null,
                            'message' => $message,
                        ];
                    }
                    return [
                        'type' => null,
                        'message' => null,
                    ];
                },
            ] : null
        ]);
    }
}
