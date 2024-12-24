<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
class ApiController extends Controller
{
    public function index(Request $request)
    {
        // Validate input data
        $request->validate([
            'text' => 'required|string',
            'top' => 'required|integer|min:1',
            'exclude' => 'nullable|array',
            'exclude.*' => 'nullable|string',
        ]);
 
        $text = $request->input('text');
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $text = file_get_contents($file->getRealPath());
        }
        $top = $request->input('top');
        $exclude = $request->input('exclude', []);

        // Cache the result for future requests
        $cacheKey = $this->generateCacheKey($text, $top, $exclude);
        $cachedResult = Cache::get($cacheKey);

        if ($cachedResult) {
            return response()->json(['data' => $cachedResult]);
        }

        $wordCounts = $this->getWordFrequencies($text, $exclude);
        
        // Sort words by frequency and pick top N
        $sortedWordCounts = collect($wordCounts)
            ->sortByDesc(function ($count) {
                return $count;
            })
            ->take($top)
            ->map(function ($count, $word) {
                return ['word' => $word, 'count' => $count];
            })
            ->values();

        // Cache the result
        Cache::put($cacheKey, $sortedWordCounts, now()->addMinutes(10));

        return response()->json(['data' => $sortedWordCounts]);
    }

    private function generateCacheKey($text, $top, $exclude)
    {
        // Generate a unique cache key based on text, top N, and exclude list
        return 'word_freq:' . md5($text . $top . implode(',', $exclude));
    }

    private function getWordFrequencies($text, $exclude)
    {
        // Normalize text (lowercase and remove non-alphabetic characters)
        $text = Str::lower($text);
        $words = preg_split('/\s+/', $text);
        $words = array_map(function ($word) {
            return preg_replace('/[^a-zA-Z]/', '', $word);
        }, $words);

        // Count word frequencies
        $wordCounts = [];
        foreach ($words as $word) {
            if (!empty($word) && !in_array($word, $exclude)) {
                $wordCounts[$word] = isset($wordCounts[$word]) ? $wordCounts[$word] + 1 : 1;
            }
        }

        return $wordCounts;
    }
}
