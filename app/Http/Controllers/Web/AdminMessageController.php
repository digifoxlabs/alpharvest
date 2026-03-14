<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Message;
use Illuminate\Contracts\View\View;

class AdminMessageController extends Controller
{
    public function index(): View
    {
        return view('admin.messages.index', [
            'messages' => Message::query()
                ->with(['conversation.customer', 'conversation.store.tenant'])
                ->latest('id')
                ->limit(150)
                ->get(),
        ]);
    }
}
