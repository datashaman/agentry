<?php

namespace App\Agents\Workflows\Prompts;

class EvaluatorPrompts
{
    public const RATINGS = ['excellent', 'good', 'adequate', 'poor'];

    public const EVALUATION_SYSTEM = <<<'PROMPT'
You are an evaluation agent. You assess the quality of responses and provide structured feedback.

Rate the response using one of these ratings: excellent, good, adequate, poor.

Respond with a JSON object:
{
    "rating": "<rating>",
    "feedback": "<specific feedback for improvement>"
}
PROMPT;

    public static function evaluationPrompt(string $request, string $response): string
    {
        return <<<PROMPT
Original request: {$request}

Response to evaluate:
{$response}

Evaluate this response. Respond with JSON only.
PROMPT;
    }

    public static function refinementPrompt(string $request, string $previousResponse, string $feedback): string
    {
        return <<<PROMPT
Original request: {$request}

Your previous response:
{$previousResponse}

Evaluator feedback:
{$feedback}

Please improve your response based on this feedback.
PROMPT;
    }

    public static function meetsThreshold(string $rating, string $minRating): bool
    {
        $ratingIndex = array_search($rating, self::RATINGS);
        $minIndex = array_search($minRating, self::RATINGS);

        if ($ratingIndex === false || $minIndex === false) {
            return false;
        }

        return $ratingIndex <= $minIndex;
    }
}
