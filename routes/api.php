<?php

use App\Enums\JobStatus;
use App\Enums\Province;
use App\Http\Middleware\AuthenticateApiToken;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Job;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/*
 * First-party JSON API. Every route authenticates a bearer token and runs under
 * that token's tenant (AuthenticateApiToken), so the normal BelongsToTenant
 * scope isolates all data automatically. Reads are open to any token; writes
 * enforce the same role gates as the app (via the token's user).
 */
Route::middleware(AuthenticateApiToken::class)->prefix('v1')->group(function () {
    Route::get('me', function () {
        $user = auth()->user();

        return response()->json([
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role?->value],
            'tenant' => ['id' => tenant('id'), 'name' => tenant('name')],
        ]);
    });

    Route::get('customers', function () {
        return response()->json([
            'data' => Customer::orderBy('name')->get()->map(fn (Customer $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'email' => $c->email,
                'phone' => $c->phone,
                'city' => $c->city,
                'province' => $c->province?->value,
                'is_active' => (bool) $c->is_active,
            ]),
        ]);
    });

    Route::get('customers/{id}', function (string $id) {
        $c = Customer::findOrFail($id);

        return response()->json(['data' => [
            'id' => $c->id, 'name' => $c->name, 'contact_name' => $c->contact_name,
            'email' => $c->email, 'phone' => $c->phone,
            'address_line1' => $c->address_line1, 'city' => $c->city,
            'province' => $c->province?->value, 'postal_code' => $c->postal_code,
            'tax_exempt' => (bool) $c->tax_exempt, 'is_active' => (bool) $c->is_active,
        ]]);
    });

    Route::get('invoices', function () {
        return response()->json([
            'data' => Invoice::with(['lines', 'payments', 'customer'])->latest()->get()->map(fn (Invoice $i) => [
                'id' => $i->id,
                'number' => $i->number,
                'customer' => $i->customer?->name,
                'status' => $i->status?->value,
                'subtotal' => $i->subtotal(),
                'tax_total' => (float) $i->tax_total,
                'total' => $i->total(),
                'paid' => $i->amountPaid(),
                'balance' => $i->balance(),
                'issued_at' => $i->issued_at?->toDateString(),
                'due_at' => $i->due_at?->toDateString(),
            ]),
        ]);
    });

    Route::get('invoices/{id}', function (string $id) {
        $i = Invoice::with(['lines', 'payments', 'customer'])->findOrFail($id);

        return response()->json(['data' => [
            'id' => $i->id, 'number' => $i->number, 'customer' => $i->customer?->name,
            'status' => $i->status?->value, 'subtotal' => $i->subtotal(),
            'tax_total' => (float) $i->tax_total, 'total' => $i->total(),
            'paid' => $i->amountPaid(), 'balance' => $i->balance(),
            'lines' => $i->lines->map(fn ($l) => [
                'description' => $l->description, 'quantity' => (float) $l->quantity,
                'unit_price' => (float) $l->unit_price, 'total' => $l->lineTotal(),
            ]),
        ]]);
    });

    Route::get('jobs', function () {
        return response()->json([
            'data' => Job::with('customer')->latest()->get()->map(fn (Job $j) => [
                'id' => $j->id,
                'number' => $j->number,
                'title' => $j->title,
                'customer' => $j->customer?->name,
                'status' => $j->status?->value,
                'scheduled_at' => $j->scheduled_at?->toIso8601String(),
            ]),
        ]);
    });

    Route::get('inventory', function () {
        return response()->json([
            'data' => InventoryItem::where('is_active', true)
                ->withSum('stockLevels as on_hand', 'quantity')
                ->orderBy('name')->get()->map(fn (InventoryItem $i) => [
                    'id' => $i->id,
                    'name' => $i->name,
                    'internal_sku' => $i->internal_sku,
                    'price' => (float) $i->price,
                    'on_hand' => (float) ($i->on_hand ?? 0),
                ]),
        ]);
    });

    // --- Writes (enforce the same role gates as the app) ---

    $provinceRule = 'in:'.implode(',', array_map(fn (Province $p) => $p->value, Province::cases()));

    Route::post('customers', function (Request $request) use ($provinceRule) {
        abort_unless(Gate::allows('manage-customers'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', $provinceRule],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'tax_exempt' => ['boolean'],
        ]);

        $customer = Customer::create($data);

        return response()->json(['data' => ['id' => $customer->id, 'name' => $customer->name]], 201);
    });

    Route::patch('customers/{id}', function (Request $request, string $id) use ($provinceRule) {
        abort_unless(Gate::allows('manage-customers'), 403);

        $customer = Customer::findOrFail($id);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'province' => ['nullable', 'string', $provinceRule],
            'tax_exempt' => ['boolean'],
            'is_active' => ['boolean'],
        ]);
        $customer->update($data);

        return response()->json(['data' => ['id' => $customer->id, 'name' => $customer->name, 'is_active' => (bool) $customer->is_active]]);
    });

    Route::post('jobs', function (Request $request) {
        abort_unless(Gate::allows('manage-jobs'), 403);

        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'title' => ['required', 'string', 'max:255'],
            'scheduled_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $job = Job::create([
            'customer_id' => $data['customer_id'],
            'title' => $data['title'],
            'status' => JobStatus::Scheduled,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json(['data' => ['id' => $job->id, 'number' => $job->number, 'title' => $job->title, 'status' => $job->status->value]], 201);
    });
});
