<?php

namespace Tests\Unit;

use App\Services\WalletRewardService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WalletRewardPrecisionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.prefix' => '',
            'database.connections.sqlite.foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('tbluserwallet', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('userid');
            $table->string('totaltoken', 40);
            $table->timestamps();
        });
    }

    public function test_credit_preserves_eight_decimal_precision_on_large_balances(): void
    {
        DB::table('tbluserwallet')->insert([
            'userid' => 99,
            'totaltoken' => '123456789.00000001',
        ]);

        app(WalletRewardService::class)->credit(99, '0.00000001');

        $this->assertSame(
            '123456789.00000002',
            DB::table('tbluserwallet')->where('userid', 99)->value('totaltoken')
        );
    }

    public function test_credit_rejects_amounts_with_more_than_eight_decimal_places(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(WalletRewardService::class)->credit(99, '0.000000001');
    }
}
