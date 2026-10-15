<?php

namespace Tests\Concerns;

trait ReviewsClothingRefunds
{
    private array $clothingReviewTokens = [];

    /** Exercise the same review and confirm steps as the browser. */
    private function postRefund(string $uri, array $input = [])
    {
        if (! preg_match('~/clothing/orders/[0-9]+/refunds$~', $uri)) return $this->post($uri, $input);
        $selection = array_diff_key($input, ['bank_details' => true]);
        $key = hash('sha256', $uri.json_encode($selection));
        if (! isset($this->clothingReviewTokens[$key])) {
            $response = $this->post($uri.'/review', $selection);
            if ($response->status() !== 200) return $response;
            $this->clothingReviewTokens[$key] = $response->viewData('reviewToken');
        }
        $confirm = ['review_token' => $this->clothingReviewTokens[$key]];
        if (isset($input['bank_details'])) $confirm['bank_details'] = $input['bank_details'];
        return $this->post($uri, $confirm);
    }
}
