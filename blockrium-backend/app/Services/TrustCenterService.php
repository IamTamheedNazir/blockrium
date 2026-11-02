<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TrustCenterService
{
    public function publicPayload(): array
    {
        if (!Schema::hasTable('trust_center_sections')) {
            return [
                'sections' => [],
                'links' => $this->links(),
                'updated_at' => null,
            ];
        }

        $sections = DB::table('trust_center_sections')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function ($section) {
                return [
                    'key' => $section->key,
                    'title' => $section->title,
                    'body' => $section->body,
                    'sort_order' => (int) $section->sort_order,
                ];
            })
            ->all();

        return [
            'sections' => $sections,
            'links' => $this->links(),
            'updated_at' => DB::table('trust_center_sections')->max('updated_at'),
        ];
    }

    private function links(): array
    {
        return [
            'privacy_policy' => url('/privacy-policy'),
            'terms' => '',
            'delete_account' => url('/delete-account-request'),
            'support' => '',
        ];
    }
}
