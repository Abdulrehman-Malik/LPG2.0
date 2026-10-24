<?php

namespace App\Models;

class GasRateModel extends RateCardModel
{
    protected function effectiveRow(string $rateType, ?int $cylinderTypeId, ?string $asOf = null, ?int $locationId = null): ?array
    {
        $asOf = $asOf ?? date('Y-m-d H:i:s');

        $queryForLocation = function ($locationFilter) use ($rateType, $cylinderTypeId, $asOf) {
            $builder = $this->db->table('rate_cards')
                ->where('rate_type', $rateType)
                ->where('effective_from <=', $asOf);

            if ($cylinderTypeId === null) {
                $builder->where('cylinder_type_id', null);
            } else {
                $builder->where('cylinder_type_id', $cylinderTypeId);
            }

            if (is_array($locationFilter)) {
                // Business-level rate of the branch's own business (BL-012).
                $builder->where('location_id', null)->where('business_id', $locationFilter['business_id']);
            } else {
                $builder->where('location_id', (int) $locationFilter);
            }

            return $builder->orderBy('effective_from', 'DESC')->orderBy('id', 'DESC')->get(1)->getRowArray();
        };

        // A rate is always looked up for one branch: its own rate, else its business's rate.
        // Never another branch's, never another business's (BL-012).
        $locationId = $locationId !== null && $locationId > 0 ? $locationId : \App\Libraries\TenantContext::requireBranchId();
        $row = $queryForLocation($locationId);
        if ($row) {
            return $row;
        }

        return $queryForLocation(['business_id' => \App\Services\BusinessContext::businessIdForLocation($locationId)]);
    }

    public function currentCylinderRate(int $id, ?string $asOf = null, ?int $locationId = null): ?float
    {
        $row = $this->effectiveRow('cylinder_package', $id, $asOf, $locationId);
        return $row ? (float) $row['rate_value'] : null;
    }

    /** Price of the gas in one full cylinder of this type (rate type filled_cylinder). */
    public function currentFilledCylinderRate(int $id, ?string $asOf = null, ?int $locationId = null): ?float
    {
        $row = $this->effectiveRow('filled_cylinder', $id, $asOf, $locationId);
        return $row ? (float) $row['rate_value'] : null;
    }

    public function currentKgRate(?string $asOf = null, ?int $locationId = null): ?float
    {
        $row = $this->effectiveRow('gas_per_kg', null, $asOf, $locationId);
        return $row ? (float) $row['rate_value'] : null;
    }
}
