<?php

use App\Domains\Settings\ManageUsers\Api\Controllers\UserController;
use App\Domains\Vault\ManageVault\Api\Controllers\VaultController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the bootstrap/app.php file and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->name('api.')->group(function () {
    // users
    Route::get('user', [UserController::class, 'user']);
    Route::apiResource('users', UserController::class)->only(['index', 'show']);

    // vaults
    Route::apiResource('vaults', VaultController::class);
});

// ==========================================
// 🤖 My first AI summarization API
// ==========================================
Route::get('/contacts/{contactId}/summary', function ($contactId) {
    // 1. Find contact information from DB
    $contact = \App\Models\Contact::find($contactId);

    if (! $contact) {
        return response()->json(['error' => 'Contact not found.'], 404);
    }

    // 2. Get the latest 5 life events for the contact
    $events = $contact->timelineEvents()->with('lifeEvents')->latest('started_at')->take(5)->get();

    // 3. Assemble a prompt to give to the AI
    $prompt = "This person's name is {$contact->first_name}. Recent records:\n";
    foreach ($events as $event) {
        foreach ($event->lifeEvents as $lifeEvent) {
            // It makes the data fetched from the DB into nicely formatted text.
            $label = $lifeEvent->label;
            $desc = $lifeEvent->summary;

            $prompt .= "- {$lifeEvent->happened_at}: [{$label}] {$desc}\n";
        }
    }

    // Log the prompt once to check if it was created properly (optional)
    \Illuminate\Support\Facades\Log::info("AI prompt: \n".$prompt);

    // 4. Call Groq API
    $response = \Illuminate\Support\Facades\Http::withToken(env('GROQ_API_KEY'))
        ->post('https://api.groq.com/openai/v1/chat/completions', [
            'model' => 'llama-3.1-8b-instant',
            'messages' => [
                ['role' => 'system', 'content' => 'You are an excellent networking assistant. Please summarize this person\'s recent status in a friendly manner in 3 lines based on the provided records.'],
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

    // 5. Return the result to the frontend (browser) in JSON API format!
    if ($response->successful()) {
        $aiSummary = $response->json('choices.0.message.content');

        return response()->json([
            'status' => 'success',
            'contact_name' => $contact->first_name,
            'ai_summary' => $aiSummary,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // When an error occurs
    return response()->json([
        'status' => 'error',
        'message' => 'Failed to communicate with AI API',
        'details' => $response->body(),
    ], 500);
});
