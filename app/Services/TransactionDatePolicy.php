<?php

namespace App\Services;

use RuntimeException;

/**
 * Business-level back-dating rule for financial postings (owner decision D10-1).
 *
 *   none          — today only
 *   current_month — from the 1st of the current month
 *   days          — up to backdate_max_days days back
 *
 * Future-dated postings are never allowed. Opening inventory is exempt because it
 * records historical stock rather than a business transaction.
 */
class TransactionDatePolicy
{
    /** Client clocks drift; a posting this far ahead of the server clock is still "now". */
    private const FUTURE_TOLERANCE_SECONDS = 600;

    private ConfigurationService $configuration;

    public function __construct(?ConfigurationService $configuration = null)
    {
        $this->configuration = $configuration ?? new ConfigurationService();
    }

    /**
     * @return array{mode:string,max_days:int,earliest:string,latest:string,allows_backdate:bool,description:string}
     */
    public function rules(int $locationId): array
    {
        $mode = (string) $this->configuration->valueForShop('backdate_mode', $locationId);
        $maxDays = (int) $this->configuration->valueForShop('backdate_max_days', $locationId);
        $today = date('Y-m-d');

        $earliest = match ($mode) {
            'current_month' => date('Y-m-01'),
            'days' => date('Y-m-d', strtotime($today . ' -' . $maxDays . ' days')),
            default => $today,
        };
        $description = match ($mode) {
            'current_month' => 'Back-dated entries are allowed from ' . date('d-M-Y', strtotime($earliest)) . ' (current month).',
            'days' => 'Back-dated entries are allowed up to ' . $maxDays . ' day(s) back (from ' . date('d-M-Y', strtotime($earliest)) . ').',
            default => 'Back-dated entries are not allowed; transactions are posted with today\'s date.',
        };

        return [
            'mode' => $mode,
            'max_days' => $maxDays,
            'earliest' => $earliest,
            'latest' => $today,
            'allows_backdate' => $earliest < $today,
            'description' => $description,
        ];
    }

    /**
     * Returns the posting timestamp (Y-m-d H:i:s) for a user-supplied date/time, or the
     * current time when none is supplied. Throws when the date is outside the policy.
     */
    public function resolve(?string $input, int $locationId): string
    {
        $input = trim((string) $input);
        if ($input === '') {
            return date('Y-m-d H:i:s');
        }

        $normalized = str_replace('T', ' ', $input);
        $timestamp = null;
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'] as $format) {
            $parsed = \DateTime::createFromFormat('!' . $format, $normalized);
            if ($parsed !== false && $parsed->format($format) === $normalized) {
                $timestamp = $parsed->getTimestamp();
                // A date without a time posted today means "now"; an earlier date keeps midnight.
                if ($format === 'Y-m-d' && $normalized === date('Y-m-d')) {
                    $timestamp = time();
                }
                break;
            }
        }
        if ($timestamp === null) {
            throw new RuntimeException('Invalid transaction date/time. Use the date picker to choose a valid date.');
        }

        if ($timestamp > time() + self::FUTURE_TOLERANCE_SECONDS) {
            throw new RuntimeException('Future-dated transactions are not allowed.');
        }

        $rules = $this->rules($locationId);
        if (date('Y-m-d', $timestamp) < $rules['earliest']) {
            throw new RuntimeException($rules['mode'] === 'none'
                ? 'Back-dated transactions are not allowed for this business. Use today\'s date.'
                : 'Transaction date is outside the allowed back-dating period. ' . $rules['description']);
        }

        return date('Y-m-d H:i:s', min($timestamp, time()));
    }
}
