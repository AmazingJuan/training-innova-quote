<?php

namespace Database\Factories;

use App\Models\BusinessUnit;
use App\Models\Client;
use App\Models\QuotationProposalOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuotationProposalOrder> */
class QuotationProposalOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'quotation_code' => 'EO-V-'.fake()->unique()->numerify('26##'),
            'client_id' => Client::factory(),
            'business_unit_id' => BusinessUnit::factory(),
            'answers' => ['Descripción del servicio a solicitar' => 'Auditoría interna ISO 9001'],
        ];
    }

    /** Management already uploaded the quotation. */
    public function ready(string $url = 'https://docs.example.com/propuesta.pdf'): static
    {
        return $this->state(['is_generated' => true, 'quotation_url' => $url]);
    }
}
