<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;
use Illuminate\Http\Request;

/**
 * The bell: a person's own notifications only.
 */
class NotificationController extends Controller
{
    public function index(Content $content)
    {
        $notes = Admin::user()->notifications()->latest()->paginate(30);

        return $content->title('Notifications')
            ->description('Leave requests, decisions and other messages for you')
            ->body(view('ehrms.notifications', compact('notes')));
    }

    /** Unread count, for the header to refresh itself. */
    public function summary()
    {
        return response()->json(['unread' => Admin::user()->unreadNotifications()->count()]);
    }

    /** Open a notification: mark it read and go to what it is about. */
    public function open(string $id)
    {
        $note = Admin::user()->notifications()->whereKey($id)->firstOrFail();
        $note->markAsRead();
        $url = $note->data['url'] ?? null;

        // Only follow links into this system.
        if (!$url || strpos($url, admin_url('/')) !== 0) {
            $url = admin_url('notifications');
        }

        return redirect($url);
    }

    public function readAll(Request $request)
    {
        Admin::user()->unreadNotifications->markAsRead();

        return $request->expectsJson() ? response()->json(['ok' => true]) : redirect(admin_url('notifications'));
    }
}
