<?php

namespace App\Http\Controllers;

use App\Models\S3SettingHistory;
use App\Models\Setting;
use Aws\S3\S3Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class S3SettingsController extends Controller
{
    public function index()
    {
        $current = S3SettingHistory::where('is_active', true)->latest()->first();
        $history = S3SettingHistory::orderByDesc('id')->limit(20)
            ->get(['id','bucket','region','endpoint','access_key','test_passed','test_message','is_active','created_at']);

        return Inertia::render('Settings/S3', [
            'current' => $current ? [
                'id' => $current->id, 'bucket' => $current->bucket, 'region' => $current->region,
                'endpoint' => $current->endpoint, 'access_key' => $current->access_key,
                'created_at' => $current->created_at,
            ] : null,
            'history' => $history,
        ]);
    }

    /**
     * Save a NEW config — REQUIRES the test to pass.
     * If test fails, the previous config stays active.
     */
    public function saveNew(Request $request)
    {
        $data = $request->validate([
            'bucket'     => 'required|string|max:200',
            'region'     => 'nullable|string|max:50',
            'access_key' => 'required|string|max:255',
            'secret_key' => 'required|string|max:255',
            'endpoint'   => 'nullable|string|max:255',
        ]);

        // If user pasted a full URL into "bucket" (common Contabo mistake),
        // split it: endpoint = scheme://host, bucket = first path segment.
        if (preg_match('#^(https?://[^/]+)/([^/?]+)#i', $data['bucket'], $m)) {
            $data['endpoint'] = $data['endpoint'] ?: $m[1];
            $data['bucket']   = $m[2];
        }
        // S3 SDK requires *some* region string even for non-AWS providers
        $data['region'] = $data['region'] ?: 'us-east-1';

        // Test the new config FIRST
        $result = $this->testConfig($data);
        if (!$result['ok']) {
            // Save as failed history but DO NOT activate
            S3SettingHistory::create($data + [
                'test_passed' => false,
                'test_message' => $result['message'],
                'is_active' => false,
                'saved_by' => Auth::id(),
            ]);
            return back()->with('error', '❌ Test failed — previous config kept active. ' . $result['message']);
        }

        // Test passed — deactivate the old, activate the new
        S3SettingHistory::where('is_active', true)->update(['is_active' => false]);
        $row = S3SettingHistory::create($data + [
            'test_passed' => true,
            'test_message' => $result['message'],
            'is_active' => true,
            'saved_by' => Auth::id(),
        ]);

        // Push into runtime settings (read by AppServiceProvider on every boot)
        Setting::setValue('s3_bucket',   $data['bucket'], 's3');
        Setting::setValue('s3_region',   $data['region'], 's3');
        Setting::setValue('s3_key',      $data['access_key'], 's3');
        Setting::setValue('s3_secret',   $data['secret_key'], 's3');
        Setting::setValue('s3_endpoint', $data['endpoint'] ?? '', 's3');

        return back()->with('success', '✅ ' . $result['message']);
    }

    /**
     * Restore a previously-good config from history.
     */
    public function restore(S3SettingHistory $history)
    {
        if (!$history->test_passed) {
            return back()->with('error', 'Cannot restore — that config never passed its test.');
        }

        S3SettingHistory::where('is_active', true)->update(['is_active' => false]);
        $history->update(['is_active' => true]);

        Setting::setValue('s3_bucket', $history->bucket, 's3');
        Setting::setValue('s3_region', $history->region, 's3');
        Setting::setValue('s3_key',    $history->access_key, 's3');
        Setting::setValue('s3_secret', $history->secret_key, 's3');

        return back()->with('success', "Restored S3 config from {$history->created_at}");
    }

    /**
     * Delete is BLOCKED. There is no destroy endpoint.
     * To rotate keys, save a NEW config that passes its test — the old one stays in history.
     */

    private function testConfig(array $cfg): array
    {
        try {
            $client = new S3Client(array_filter([
                'version' => 'latest',
                'region'  => $cfg['region'] ?: 'us-east-1',
                'credentials' => [
                    'key'    => $cfg['access_key'],
                    'secret' => $cfg['secret_key'],
                ],
                'endpoint' => $cfg['endpoint'] ?? null,
                'use_path_style_endpoint' => !empty($cfg['endpoint']),
                'http'    => ['timeout' => 10],
            ], fn($v) => $v !== null));

            // 1. List bucket (or create-then-delete a tiny test object)
            $key = 'autogo-s3test-'.now()->timestamp.'.txt';
            $client->putObject(['Bucket' => $cfg['bucket'], 'Key' => $key, 'Body' => 'autogo s3 test']);
            $client->getObject(['Bucket' => $cfg['bucket'], 'Key' => $key]);
            $client->deleteObject(['Bucket' => $cfg['bucket'], 'Key' => $key]);

            return ['ok' => true, 'message' => "S3 write+read+delete OK on bucket {$cfg['bucket']} ({$cfg['region']})"];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'S3 test failed: '.$e->getMessage()];
        }
    }
}
