<?php
 
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Game;
use App\Services\TriviaApiService;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log; 

class GamePlayController extends Controller
{
    protected $triviaApi;

    protected $casts = [
        'questions' => 'array',
    ];

    public function __construct(TriviaApiService $triviaApi)
    {
        $this->triviaApi = $triviaApi;
    }

    public function start()
    {
        try {    
            $questions = $this->triviaApi->fetchQuestions();

            // Check if we got results from the API
            if (empty($questions['results']) || !is_array($questions['results'])) {
                return back()->with('error', 'Failed to fetch questions. Please try again.');
            }

            Session::put('questions', $questions['results']);
 
            $game = new Game();
            $game->session_id = Session::getId();
            $game->current_question_index = 0;
            $game->score = 0; 
            $game->questions = $questions['results'];  
            try {
                $game->save();
            } catch (\Exception $e) {
                dd('Save failed:', $e->getMessage(), $e->getTraceAsString());
            } 
            
            return redirect()->route('game.question', $game->id);

        } catch (\Exception $e) {
            Log::error('Start game failed: ', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json(['error' => 'failed to start game.'], 500); 
        }
    }

    public function showQuestion(Game $game)
    {
        //dd($game); 
        $questions = $game['questions'];
        $question = $questions[$game->current_question_index];

        return view('game.question', compact('game', 'question'));
    }

    public function submitAnswer(Request $request, Game $game)
    {
        $questions = $game['questions'];
        $currentQuestion = $questions[$game->current_question_index];

        $isCorrect = $request->input('answer') === $currentQuestion['correct_answer'];
        if ($isCorrect) {
            $game->score += 1;
        }

        $game->current_question_index += 1;
        $game->save();

        if ($game->current_question_index >= count($questions)) {
            return redirect()->route('game.result', $game->id);
        }

        return redirect()->route('game.question', $game->id);
    }

    public function result(Game $game)
    {
        $questions = $game['questions'];
    
        return view('game.result', [
            'score' => $game->score,
            'totalQuestions' => count($questions),
            'game' => $game
        ]);
    }
}
