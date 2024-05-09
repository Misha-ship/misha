<?php

namespace App\Jobs\Moderation;

use App\Http\Service\GoogleML\ContentModerationService;
use App\Http\Service\GoogleML\GoogleLanguageService;
use Bazar\Models\Models\Comment;
use Bazar\Models\Models\CommentAiRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ModerationCommentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Comment $comment;

    public function __construct(Comment $comment)
    {
        $this->comment = $comment;
        $this->onQueue('moderation-ai');
    }

    public function handle(GoogleLanguageService $googleLanguageService, ContentModerationService $contentModerationService): void
    {
        $contentModerationResult = $this->moderateTextContent($this->comment, $googleLanguageService, $contentModerationService);

        if ($contentModerationResult['result'] !== ContentModerationService::POSITIVE) {
            $this->logModerationResult($contentModerationResult);
            return;
        }
        $this->logModerationResult($contentModerationResult);
        $this->success($this->comment);
    }

    private function moderateTextContent(Comment $comment, GoogleLanguageService $googleLanguageService, ContentModerationService $contentModerationService): array
    {
        $contentModerationResult = $this->processModerationText($comment->content, $googleLanguageService, $contentModerationService);
        return $contentModerationResult;
    }

    private function processModerationText(string $text, GoogleLanguageService $googleLanguageService, ContentModerationService $contentModerationService): array
    {
        $moderateText = $googleLanguageService->moderateText($text);
        $analyzeEntities = $googleLanguageService->analyzeEntities($text);
        $analyzeSentiment = $googleLanguageService->analyzeSentiment($text);
        $classifyText = $googleLanguageService->classifyText($text);

        return $contentModerationService->moderateContent(
            $analyzeEntities['entities'],
            $analyzeSentiment['documentSentiment'],
            $moderateText['moderationCategories'],
            $analyzeSentiment['sentences'],
            $classifyText['categories']
        );
    }

    private function logModerationResult(array $result): void
    {
        CommentAiRequest::create([
            'comment_id' => $this->comment->id,
            'status_ai' => $result['result'],
            'message_ai' => $result['message'],
        ]);
    }

    private function success(Comment $comment): void
    {
        $comment->status = Comment::STATUS_PUBLISH;
        $comment->save();
    }
}
