<?php
namespace App\Models;
class GasRateModel extends RateCardModel
{
    public function currentCylinderRate(int $id, ?string $asOf=null): ?float
    {
        $row=$this->where('rate_type','cylinder_package')->where('cylinder_type_id',$id)->where('effective_from <=',$asOf??date('Y-m-d H:i:s'))->orderBy('effective_from','DESC')->first();
        return $row?(float)$row['rate_value']:null;
    }
    public function currentKgRate(?string $asOf=null): ?float
    {
        $row=$this->where('rate_type','gas_per_kg')->where('cylinder_type_id',null)->where('effective_from <=',$asOf??date('Y-m-d H:i:s'))->orderBy('effective_from','DESC')->first();
        return $row?(float)$row['rate_value']:null;
    }
}