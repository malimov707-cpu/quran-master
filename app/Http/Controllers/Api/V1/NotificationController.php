<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    /**
     * Mening bildirishnomalarim.
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->appNotifications()
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'unread_count' => $request->user()->appNotifications()->whereNull('read_at')->count(),
            'data' => collect($notifications->items())->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'body' => $n->body,
                'data' => $n->data,
                'read_at' => $n->read_at,
                'created_at' => $n->created_at,
            ]),
            'current_page' => $notifications->currentPage(),
            'last_page' => $notifications->lastPage(),
        ]);
    }

    /**
     * O'qilgan deb belgilash.
     */
    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->appNotifications()->findOrFail($id);
        $notification->update(['read_at' => now()]);

        return response()->json(['message' => 'O\'qilgan deb belgilandi.']);
    }

    /**
     * Hammasini o'qilgan deb belgilash.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->appNotifications()->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['message' => 'Hammasi o\'qilgan.']);
    }
}
