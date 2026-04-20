<?php

namespace Tests\Unit;

use App\Http\Requests\RefundMethodRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class RefundMethodRequestTest extends TestCase
{
    private function validate(array $data): \Illuminate\Validation\Validator
    {
        $request = new RefundMethodRequest();
        return Validator::make($data, $request->rules(), $request->messages());
    }

    // -----------------------------------------------------------------------
    // method field
    // -----------------------------------------------------------------------

    public function test_method_is_required(): void
    {
        $v = $this->validate([]);
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('method', $v->errors()->toArray());
    }

    public function test_method_wallet_is_valid(): void
    {
        $v = $this->validate(['method' => 'wallet']);
        $this->assertFalse($v->fails());
    }

    public function test_method_bank_is_valid_with_all_bank_fields(): void
    {
        $v = $this->validate([
            'method' => 'bank',
            'account_name' => 'John Doe',
            'bank_name' => 'Test Bank',
            'account_number' => '123456789',
            'branch_code' => '632005',
            'account_type' => 'savings',
        ]);
        $this->assertFalse($v->fails());
    }

    public function test_method_must_be_wallet_or_bank(): void
    {
        $v = $this->validate(['method' => 'cash']);
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('method', $v->errors()->toArray());
    }

    // -----------------------------------------------------------------------
    // Bank fields required when method=bank
    // -----------------------------------------------------------------------

    public function test_account_name_required_for_bank_method(): void
    {
        $v = $this->validate([
            'method' => 'bank',
            'bank_name' => 'Test Bank',
            'account_number' => '123456789',
            'branch_code' => '632005',
            'account_type' => 'savings',
        ]);
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('account_name', $v->errors()->toArray());
    }

    public function test_bank_name_required_for_bank_method(): void
    {
        $v = $this->validate([
            'method' => 'bank',
            'account_name' => 'John Doe',
            'account_number' => '123456789',
            'branch_code' => '632005',
            'account_type' => 'savings',
        ]);
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('bank_name', $v->errors()->toArray());
    }

    public function test_account_number_required_for_bank_method(): void
    {
        $v = $this->validate([
            'method' => 'bank',
            'account_name' => 'John Doe',
            'bank_name' => 'Test Bank',
            'branch_code' => '632005',
            'account_type' => 'savings',
        ]);
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('account_number', $v->errors()->toArray());
    }

    public function test_account_type_must_be_valid_value(): void
    {
        $v = $this->validate([
            'method' => 'bank',
            'account_name' => 'John Doe',
            'bank_name' => 'Test Bank',
            'account_number' => '123456789',
            'branch_code' => '632005',
            'account_type' => 'current', // invalid
        ]);
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('account_type', $v->errors()->toArray());
    }

    public function test_account_type_accepts_cheque(): void
    {
        $v = $this->validate([
            'method' => 'bank',
            'account_name' => 'John Doe',
            'bank_name' => 'Test Bank',
            'account_number' => '123456789',
            'branch_code' => '632005',
            'account_type' => 'cheque',
        ]);
        $this->assertFalse($v->fails());
    }

    // -----------------------------------------------------------------------
    // Bank fields NOT required when method=wallet
    // -----------------------------------------------------------------------

    public function test_bank_fields_not_required_when_method_is_wallet(): void
    {
        $v = $this->validate(['method' => 'wallet']);
        $this->assertFalse($v->fails());
    }

    // -----------------------------------------------------------------------
    // authorize()
    // -----------------------------------------------------------------------

    public function test_authorize_returns_true_when_authenticated(): void
    {
        $user = \App\Models\User::factory()->make();
        $this->actingAs($user);

        $request = new RefundMethodRequest();
        $this->assertTrue($request->authorize());
    }

    public function test_authorize_returns_false_when_guest(): void
    {
        $request = new RefundMethodRequest();
        $this->assertFalse($request->authorize());
    }
}
