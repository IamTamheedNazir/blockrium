<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateTrustCenterAndLearnEarnTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('trust_center_sections')) {
            Schema::create('trust_center_sections', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('key', 120)->unique();
                $table->string('title', 180);
                $table->text('body');
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->index(['is_active', 'sort_order'], 'trust_sections_active_sort_idx');
            });
        }

        if (!Schema::hasTable('learn_modules')) {
            Schema::create('learn_modules', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('key', 120)->unique();
                $table->string('title', 180);
                $table->text('description');
                $table->longText('content');
                $table->string('youtube_url', 500)->nullable();
                $table->string('youtube_video_id', 80)->nullable();
                $table->string('video_title', 180)->nullable();
                $table->unsignedSmallInteger('estimated_minutes')->nullable();
                $table->unsignedInteger('reward_xp')->default(20);
                $table->decimal('reward_brc', 16, 4)->nullable();
                $table->unsignedTinyInteger('passing_score')->default(100);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['is_active', 'sort_order'], 'learn_modules_active_sort_idx');
            });
        }

        if (!Schema::hasTable('learn_questions')) {
            Schema::create('learn_questions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('module_id');
                $table->text('question');
                $table->json('options');
                $table->string('correct_option', 1);
                $table->text('explanation')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index('module_id', 'learn_questions_module_idx');
                $table->index(['module_id', 'sort_order'], 'learn_questions_module_sort_idx');
            });
        }

        if (!Schema::hasTable('user_learn_progress')) {
            Schema::create('user_learn_progress', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('module_id');
                $table->string('status', 40)->default('not_started');
                $table->unsignedTinyInteger('score')->nullable();
                $table->json('answers')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('reward_claimed_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'module_id'], 'learn_progress_user_module_unique');
                $table->index('user_id', 'learn_progress_user_idx');
                $table->index('module_id', 'learn_progress_module_idx');
                $table->index('status', 'learn_progress_status_idx');
                $table->index('completed_at', 'learn_progress_completed_idx');
            });
        }

        $this->seedTrustCenter();
        $this->seedStarterLessons();
    }

    public function down()
    {
        Schema::dropIfExists('user_learn_progress');
        Schema::dropIfExists('learn_questions');
        Schema::dropIfExists('learn_modules');
        Schema::dropIfExists('trust_center_sections');
    }

    private function seedTrustCenter(): void
    {
        $sections = [
            ['key' => 'what_is_blockrium', 'title' => 'What is Blockrium?', 'body' => 'Blockrium is a Web3 participation and rewards network built around daily app activity, learning, referrals, and safety education.', 'sort_order' => 1],
            ['key' => 'what_is_brc', 'title' => 'What is BRC?', 'body' => 'BRC is the Blockrium in-app reward unit shown inside the app. It is controlled by backend validation rules and may differ from self-custody wallet assets.', 'sort_order' => 2],
            ['key' => 'mining_explained', 'title' => 'What does mining mean?', 'body' => 'In Blockrium, mining means starting a backend-controlled daily participation session. Your phone does not perform blockchain proof-of-work mining.', 'sort_order' => 3],
            ['key' => 'daily_participation', 'title' => 'Mining is daily participation', 'body' => 'Blockrium participation sessions are validated by the backend. They do not use phone hardware to mine blockchain blocks.', 'sort_order' => 4],
            ['key' => 'wallet_vs_reward_balance', 'title' => 'Wallet balance vs reward balance', 'body' => 'The app may show Blockrium reward balances and wallet-related screens. Reward balances are app-controlled records; self-custody wallet assets are controlled by the wallet holder.', 'sort_order' => 5],
            ['key' => 'no_guaranteed_income', 'title' => 'No guaranteed income', 'body' => 'Blockrium does not promise guaranteed income, profit, passive return, or investment results. Rewards depend on app rules, validation, and availability.', 'sort_order' => 6],
            ['key' => 'reward_validation', 'title' => 'How rewards are validated', 'body' => 'Rewards are checked on the backend. The app cannot choose its own reward amount, and duplicate claims are blocked where supported.', 'sort_order' => 7],
            ['key' => 'privacy_ads_consent', 'title' => 'Privacy and ad consent', 'body' => 'Some reward flows may involve ads or partner content. Users should review privacy information and consent choices before participating.', 'sort_order' => 8],
            ['key' => 'wallet_safety', 'title' => 'Wallet safety', 'body' => 'Never share recovery phrases, private keys, passcodes, or keystore files. Blockrium support will not ask for those secrets.', 'sort_order' => 9],
            ['key' => 'contact_support', 'title' => 'Contact support or report an issue', 'body' => 'If something looks wrong, contact Blockrium support through the official app or website channels. Avoid links from unknown accounts.', 'sort_order' => 10],
            ['key' => 'delete_account_privacy', 'title' => 'Delete account and privacy links', 'body' => 'Account deletion and privacy links are available from Blockrium support pages when configured by the admin team.', 'sort_order' => 11],
        ];

        foreach ($sections as $section) {
            DB::table('trust_center_sections')->updateOrInsert(
                ['key' => $section['key']],
                array_merge($section, ['is_active' => 1, 'created_at' => now(), 'updated_at' => now()])
            );
        }
    }

    private function seedStarterLessons(): void
    {
        $modules = [
            [
                'key' => 'self_custody_basics',
                'title' => 'What is self-custody?',
                'description' => 'Learn how self-custody works and why wallet keys matter.',
                'content' => 'Self-custody means you control the wallet keys that can access your assets. It gives you responsibility for keeping recovery information private and safe. If someone else gets your recovery phrase or private key, they may be able to access the wallet. Blockrium will never ask you to share those secrets.',
                'sort_order' => 1,
                'questions' => [
                    ['question' => 'What does self-custody mean?', 'options' => ['A' => 'You control your wallet keys', 'B' => 'A stranger controls your wallet', 'C' => 'A website stores your recovery phrase', 'D' => 'Your phone mines blockchain blocks'], 'correct_option' => 'A', 'explanation' => 'Self-custody means the user controls wallet keys.'],
                    ['question' => 'Who should know your recovery phrase?', 'options' => ['A' => 'Anyone in a chat group', 'B' => 'Only you', 'C' => 'Fake support agents', 'D' => 'Random websites'], 'correct_option' => 'B', 'explanation' => 'Recovery phrases should stay private.'],
                    ['question' => 'Will Blockrium support ask for private keys?', 'options' => ['A' => 'Yes, always', 'B' => 'Only on social media', 'C' => 'No', 'D' => 'Only for rewards'], 'correct_option' => 'C', 'explanation' => 'Support should never ask for wallet secrets.'],
                ],
            ],
            [
                'key' => 'recovery_phrase_safety',
                'title' => 'What is a recovery phrase?',
                'description' => 'Understand why a recovery phrase must stay private.',
                'content' => 'A recovery phrase can restore access to a wallet. Anyone who has it may be able to control that wallet. Store it offline in a safe place, never paste it into unknown forms, and never send it to support accounts.',
                'sort_order' => 2,
                'questions' => [
                    ['question' => 'What can a recovery phrase do?', 'options' => ['A' => 'Restore wallet access', 'B' => 'Increase phone battery', 'C' => 'Guarantee income', 'D' => 'Change internet speed'], 'correct_option' => 'A', 'explanation' => 'Recovery phrases restore wallet access.'],
                    ['question' => 'Where should you enter a recovery phrase?', 'options' => ['A' => 'Any reward form', 'B' => 'Unknown links', 'C' => 'Only trusted wallet recovery screens when necessary', 'D' => 'Public chat'], 'correct_option' => 'C', 'explanation' => 'Only use trusted wallet recovery flows.'],
                    ['question' => 'Should you send your phrase to support?', 'options' => ['A' => 'No', 'B' => 'Yes', 'C' => 'Only if asked twice', 'D' => 'Only for bonuses'], 'correct_option' => 'A', 'explanation' => 'Legitimate support does not need it.'],
                ],
            ],
            [
                'key' => 'avoid_crypto_scams',
                'title' => 'How to avoid crypto scams',
                'description' => 'Learn common warning signs before clicking links.',
                'content' => 'Scams often use fake support accounts, fake airdrops, urgent deadlines, and suspicious links. Be careful with messages that ask for wallet secrets, ask you to pay first, or promise guaranteed returns. Use official channels and verify links before taking action.',
                'sort_order' => 3,
                'questions' => [
                    ['question' => 'Which is a scam warning sign?', 'options' => ['A' => 'A request for your recovery phrase', 'B' => 'Reading official docs', 'C' => 'Using app settings', 'D' => 'Checking an address'], 'correct_option' => 'A', 'explanation' => 'Requests for recovery phrases are dangerous.'],
                    ['question' => 'What should you do with suspicious links?', 'options' => ['A' => 'Click quickly', 'B' => 'Verify before opening', 'C' => 'Share wallet secrets', 'D' => 'Ignore official channels'], 'correct_option' => 'B', 'explanation' => 'Verify links before opening them.'],
                    ['question' => 'Do guaranteed profit messages deserve caution?', 'options' => ['A' => 'Yes', 'B' => 'No', 'C' => 'Only at night', 'D' => 'Only if short'], 'correct_option' => 'A', 'explanation' => 'Guaranteed profit claims are risky.'],
                ],
            ],
            [
                'key' => 'gas_fees_basics',
                'title' => 'What are gas fees?',
                'description' => 'Learn what network transaction fees mean.',
                'content' => 'Gas fees are network transaction fees paid when a blockchain transaction is submitted. Fees can change based on network activity. Blockrium app rewards and self-custody wallet transactions are different systems, so always review wallet prompts carefully.',
                'sort_order' => 4,
                'questions' => [
                    ['question' => 'Gas fees are usually used for what?', 'options' => ['A' => 'Network transaction costs', 'B' => 'Phone charging', 'C' => 'Guaranteed rewards', 'D' => 'Password recovery'], 'correct_option' => 'A', 'explanation' => 'Gas fees are network transaction costs.'],
                    ['question' => 'Can gas fees change?', 'options' => ['A' => 'Yes', 'B' => 'Never', 'C' => 'Only in the app name', 'D' => 'Only by email'], 'correct_option' => 'A', 'explanation' => 'Fees can change with network activity.'],
                    ['question' => 'What should you review before wallet transactions?', 'options' => ['A' => 'The wallet prompt and address', 'B' => 'Random comments', 'C' => 'Private key requests', 'D' => 'Fake support links'], 'correct_option' => 'A', 'explanation' => 'Review wallet prompts carefully.'],
                ],
            ],
            [
                'key' => 'rewards_vs_wallet_assets',
                'title' => 'Blockrium rewards vs wallet assets',
                'description' => 'Understand the difference between in-app rewards and wallet assets.',
                'content' => 'Blockrium reward balance is an app-controlled record validated by backend rules. Self-custody wallet assets are blockchain assets controlled by wallet keys. Keep the two concepts separate and never share wallet secrets to claim app rewards.',
                'sort_order' => 5,
                'questions' => [
                    ['question' => 'Blockrium reward balance is controlled by what?', 'options' => ['A' => 'Backend app rules', 'B' => 'Random callers', 'C' => 'A recovery phrase form', 'D' => 'Phone hardware mining'], 'correct_option' => 'A', 'explanation' => 'Reward balances are app-controlled records.'],
                    ['question' => 'Self-custody wallet assets are controlled by what?', 'options' => ['A' => 'Wallet keys', 'B' => 'Public chat users', 'C' => 'Unknown links', 'D' => 'A badge icon'], 'correct_option' => 'A', 'explanation' => 'Wallet keys control wallet assets.'],
                    ['question' => 'Should wallet secrets be shared for app rewards?', 'options' => ['A' => 'No', 'B' => 'Yes', 'C' => 'Only for a bonus', 'D' => 'Only in comments'], 'correct_option' => 'A', 'explanation' => 'Do not share wallet secrets.'],
                ],
            ],
            [
                'key' => 'protect_your_wallet',
                'title' => 'How to protect your wallet',
                'description' => 'Simple habits for safer wallet use.',
                'content' => 'Use a strong passcode, keep recovery phrases private, check addresses before sending, and avoid unknown links. If a message asks for secret wallet data, treat it as unsafe. Good wallet safety is about careful habits, not speed.',
                'sort_order' => 6,
                'questions' => [
                    ['question' => 'Which habit helps protect a wallet?', 'options' => ['A' => 'Checking addresses before sending', 'B' => 'Sharing private keys', 'C' => 'Using unknown links', 'D' => 'Posting recovery phrases'], 'correct_option' => 'A', 'explanation' => 'Address checking helps avoid mistakes.'],
                    ['question' => 'How should recovery phrases be treated?', 'options' => ['A' => 'Kept private', 'B' => 'Posted publicly', 'C' => 'Sent to fake support', 'D' => 'Used as a username'], 'correct_option' => 'A', 'explanation' => 'Recovery phrases must stay private.'],
                    ['question' => 'What should you do with secret-data requests?', 'options' => ['A' => 'Treat them as unsafe', 'B' => 'Answer quickly', 'C' => 'Send passcode', 'D' => 'Share keystore'], 'correct_option' => 'A', 'explanation' => 'Secret-data requests are unsafe.'],
                ],
            ],
        ];

        foreach ($modules as $module) {
            $questions = $module['questions'];
            unset($module['questions']);

            DB::table('learn_modules')->updateOrInsert(
                ['key' => $module['key']],
                array_merge($module, [
                    'youtube_url' => null,
                    'youtube_video_id' => null,
                    'video_title' => null,
                    'estimated_minutes' => 3,
                    'reward_xp' => 20,
                    'reward_brc' => null,
                    'passing_score' => 100,
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );

            $moduleId = DB::table('learn_modules')->where('key', $module['key'])->value('id');
            foreach ($questions as $index => $question) {
                DB::table('learn_questions')->updateOrInsert(
                    ['module_id' => $moduleId, 'sort_order' => $index + 1],
                    [
                        'question' => $question['question'],
                        'options' => json_encode($question['options']),
                        'correct_option' => $question['correct_option'],
                        'explanation' => $question['explanation'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
}
