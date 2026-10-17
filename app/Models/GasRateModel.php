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

            if ($locationFilter === 'global') {
                $builder->where('location_id', null);
            } elseif ($locationFilter !== null) {
                $builder->where('location_id', (int) $locationFilter);
            }

            return $builder->orderBy('effective_from', 'DESC')->orderBy('id', 'DESC')->get(1)->getRowArray();
        };

        if ($locationId !== null && $locationId > 0) {
            $row = $queryForLocation($locationId);
            if ($row) {
                return $row;
            }

            // Global rates are the only fallback; never consume another shop's rate.
            return $queryForLocation('global');
        }

        // Keep the legacy no-location call behavior for any not-yet-migrated callers.
        return $queryForLocation(null);
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
