<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_proposal_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('client_id')->nullable()->after('id');
        });

        $this->backfillClients();

        Schema::table('quotation_proposal_orders', function (Blueprint $table) {
            $table->foreign('client_id')->references('id')->on('clients')->nullOnDelete();
        });

        if (Schema::hasColumn('quotation_proposal_orders', 'contact_info')) {
            Schema::table('quotation_proposal_orders', function (Blueprint $table) {
                $table->dropColumn('contact_info');
            });
        }
    }

    /**
     * Move every contact_info JSON into the new clients table (one row per email,
     * latest quotation wins) and link each order to its client.
     */
    private function backfillClients(): void
    {
        if (! Schema::hasColumn('quotation_proposal_orders', 'contact_info')) {
            return;
        }

        $orders = DB::table('quotation_proposal_orders')
            ->select('id', 'contact_info', 'created_at')
            ->orderBy('created_at')
            ->get();

        foreach ($orders as $order) {
            $contact = json_decode($order->contact_info ?? '[]', true) ?: [];
            $email = strtolower(trim((string) ($contact['email'] ?? '')));

            if ($email === '') {
                continue;
            }

            $payload = [
                'name' => trim((string) ($contact['name'] ?? '')),
                'company' => isset($contact['company']) && trim((string) $contact['company']) !== ''
                    ? trim((string) $contact['company'])
                    : null,
                'phone' => isset($contact['phone']) && trim((string) $contact['phone']) !== ''
                    ? trim((string) $contact['phone'])
                    : null,
                'role' => isset($contact['role']) && trim((string) $contact['role']) !== ''
                    ? trim((string) $contact['role'])
                    : null,
                'updated_at' => now(),
            ];

            $existingId = DB::table('clients')->where('email', $email)->value('id');

            if ($existingId) {
                DB::table('clients')->where('id', $existingId)->update($payload);
                $clientId = $existingId;
            } else {
                $clientId = DB::table('clients')->insertGetId(array_merge($payload, [
                    'email' => $email,
                    'created_at' => now(),
                ]));
            }

            DB::table('quotation_proposal_orders')->where('id', $order->id)->update(['client_id' => $clientId]);
        }
    }

    public function down(): void
    {
        Schema::table('quotation_proposal_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('quotation_proposal_orders', 'contact_info')) {
                $table->json('contact_info')->nullable()->after('id');
            }
        });

        Schema::table('quotation_proposal_orders', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropColumn('client_id');
        });
    }
};
