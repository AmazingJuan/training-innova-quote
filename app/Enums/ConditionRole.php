<?php

namespace App\Enums;

/**
 * What the application itself does with the answer to a question, so it
 * does not depend on the question's wording (which admins can change).
 */
enum ConditionRole: string
{
    /**
     * The date the client expects the commercial offer by: it must be at
     * least 3 business days away and drives the "overdue" reminders.
     */
    case OfferDate = 'offer_date';
}
