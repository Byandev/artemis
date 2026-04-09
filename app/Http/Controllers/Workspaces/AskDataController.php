<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;
use OpenAI\Laravel\Facades\OpenAI;

class AskDataController extends Controller
{
    public function __invoke(Request $request, Workspace $workspace)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403);
        }

        $request->validate([
            'section' => ['required', 'string'],
            'question' => ['required', 'string', 'max:500'],
            'data' => ['required', 'array'],
        ]);

        $section = $request->input('section');
        $question = $request->input('question');
        $data = json_encode($request->input('data'), JSON_PRETTY_PRINT);

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a straight-talking business analyst for a Filipino eCommerce seller. You are given real data currently displayed on their dashboard. Answer questions and give insights based strictly on the provided data. Be concise — 3 to 5 sentences unless a detailed breakdown is specifically asked. Use ₱ for peso amounts. Do not make up numbers not in the data.',
                ],
                [
                    'role' => 'user',
                    'content' => "Section: {$section}\n\nData:\n{$data}\n\nQuestion: {$question}",
                ],
            ],
            'max_tokens' => 500,
            'temperature' => 0.5,
        ]);

        $answer = trim($response->choices[0]->message->content ?? '');

        return response()->json(['answer' => $answer]);
    }
}
