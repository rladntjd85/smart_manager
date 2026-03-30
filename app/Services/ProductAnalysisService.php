<?php

namespace App\Services;

use Gemini;
use App\Models\Category;
use Gemini\Data\Blob;
use Gemini\Enums\MimeType;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Google_Client;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Request;

class ProductAnalysisService
{
    protected $client;

    public function __construct()
    {
        $projectId = config('services.gcp.project_id');
        $location = config('services.gcp.location');

        $this->baseUrl = "https://{$location}-aiplatform.googleapis.com/v1/projects/{$projectId}/locations/{$location}/publishers/google/models";
        $this->apiKey = config('services.gemini.key');
    }

    /**
     * 텍스트와 이미지를 동시에 분석하여 정형 데이터를 반환합니다.
     * @param string $rawText 상품 원문
     * @param string|null $imagePath 상세 이미지 절대 경로
     */
    public function analyzeAndSave(string $rawText, ?string $imagePath = null)
    {
        $ipAddress = request()->ip();
        $mode = $imagePath ? 'image' : 'text';
        $throttleKey = "vertex_ai_{$mode}_limit_" . $ipAddress;

        if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts = 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw new \Exception("보안 및 비용 절감을 위해 동일 IP에서의 요청을 제한합니다. {$seconds}초 후 다시 시도해 주세요.");
        }

        RateLimiter::hit($throttleKey, 60); // 60초간 기록 유지

        // 1. 캐시 체크 (이미지 유무에 따라 키 분리)
        $contentHash = md5(trim($rawText) . ($imagePath ? md5_file($imagePath) : ''));
        $cacheKey = "product_analysis_v3_{$contentHash}";

        if (Cache::has($cacheKey)) {
            Log::info("Cache Hit: 기존 분석 데이터를 반환합니다.");
            return Cache::get($cacheKey);
        }

        $maxRetries = 3;
        $retryDelay = 2;

        for ($i = 0; $i < $maxRetries; $i++) {
            try {
                $modelName = 'gemini-2.5-flash-lite';
                $fullUrl = "{$this->baseUrl}/{$modelName}:generateContent?key={$this->apiKey}";

                // 2. 프롬프트 구성 (요약, 태그, 이미지 매칭 로직 포함)
                $instruction = "당신은 커머스 데이터 엔지니어 및 SEO 전문가입니다.
                    제공된 텍스트와 이미지를 분석하여 다음 JSON 구조로만 응답하세요.

                    [필드 정의]
                    - name: 브랜드가 포함된 정규화된 상품명
                    - category: 가장 적합한 카테고리명
                    - price: 숫자 가격 (없으면 0)
                    - summary: 상품의 핵심 장점을 녹여낸 50자 내외의 한 줄 요약
                    - tags: SEO를 위한 핵심 키워드 5~8개 (배열)
                    - specs: 상세 규격 (Key-Value 쌍)
                    - image_match: {
                        status: 'success'(일치), 'warning'(의심), 'fail'(불일치),
                        message: 'warning/fail 시 구체적인 이유 설명'
                      }

                    텍스트: " . $rawText;

                $parts = [
                    ['text' => $instruction]
                ];

                // 3. 이미지 파트 추가 (멀티모달)
                if ($imagePath && file_exists($imagePath)) {
                    $parts[] = [
                        'inline_data' => [
                            'mime_type' => mime_content_type($imagePath), // 기존 헬퍼 메서드 대신 내장 함수 직접 사용
                            'data' => base64_encode(file_get_contents($imagePath))
                        ]
                    ];
                }

                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                ])->post($fullUrl, [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => $parts
                        ]
                    ]
                ]);

//                dd([
//                    'status' => $response->status(),
//                    'body' => $response->body(),
//                ]);

                if ($response->failed()) {
                    throw new \Exception('Vertex AI 호출 실패: ' . $response->body());
                }

                // [수정 4] REST API 응답 구조에 맞게 텍스트 추출
                $responseData = $response->json();
                $responseText = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';

                Log::info('Gemini text', [
                    'text' => $responseText
                ]);

                $data = $this->parseJsonResponse($responseText);

                Log::info('Parsed data', [
                    'data' => $data
                ]);

                if (!is_array($data)) continue;

                $data = array_merge([
                    'name' => null,
                    'category' => '미분류',
                    'price' => 0,
                    'summary' => '',
                    'tags' => [],
                    'specs' => [],
                    'image_match' => [
                        'status' => 'success',
                        'message' => null
                    ],
                ], $data);

                // 4. 데이터 정규화 (카테고리 처리)
                $categoryName = trim($data['category'] ?? '미분류');
                $category = Category::firstOrCreate(['name' => $categoryName]);

                $analysisResult = [
                    'name'                => trim($data['name']),
                    'category_id'         => $category->id,
                    'price'               => (int)($data['price'] ?? 0),
                    'summary'             => $data['summary'] ?? '',
                    'tags'                => $data['tags'] ?? [],
                    'image_match_status'  => $data['image_match']['status'] ?? 'success',
                    'image_match_message' => $data['image_match']['message'] ?? null,
                    'raw_text'            => $rawText,
                    'specs'               => $data['specs'] ?? [],
                ];

                Cache::put($cacheKey, $analysisResult, now()->addDays(30));
                return $analysisResult;

            } catch (\Exception $e) {
                if (str_contains($e->getMessage(), '429')) {
                    sleep($retryDelay * pow(2, $i));
                    continue;
                }
                Log::error("분석 오류: " . $e->getMessage());
                return null;
            }
        }
        return null;
    }

    private function parseJsonResponse($text)
    {
        $text = trim(preg_replace('/```json|```/', '', $text));

        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if ($start !== false && $end !== false) {
            $json = substr($text, $start, $end - $start + 1);
            return json_decode($json, true);
        }

        return null;
    }

    public function analyzeImage(string $imagePath)
    {
        return $this->analyzeAndSave(rawText: "이미지 분석 결과", imagePath: $imagePath);
    }
}
