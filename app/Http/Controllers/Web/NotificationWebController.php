<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationWebController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = $request->user()->appNotifications()->latest()->orderByDesc('id')->paginate(20);
        $unreadCount = $request->user()->appNotifications()->whereNull('read_at')->count();

        return view('notifications', compact('notifications', 'unreadCount'));
    }

    public function markRead(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->appNotifications()->findOrFail($id);
        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return redirect()->route('notifications.index')->with('status', 'Xabar o‘qilgan deb belgilandi.');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->appNotifications()->whereNull('read_at')->update(['read_at' => now()]);

        return redirect()->route('notifications.index')->with('status', 'Barcha xabarlar o‘qilgan deb belgilandi.');
    }
}
