<?php

namespace App\Http\Controllers\Api;

use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends ApiController
{
    public function index(Request $request, Report $report): JsonResponse
    {
        $comments = $report->comments()
            ->with('user:id,name,role')
            ->latest()
            ->get();

        return $this->success($comments);
    }

    public function store(Request $request, Report $report): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:1000'],
        ]);

        $comment = $report->comments()->create([
            'user_id'     => $request->user()->id,
            'message'     => $validated['message'],
            'is_official' => $request->user()->isAuthority(),
        ]);

        $comment->load('user:id,name,role');

        return $this->success($comment, 'Comment posted', 201);
    }
}
