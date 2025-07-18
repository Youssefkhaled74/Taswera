<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardStatsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'summary' => [
                'total_sales' => $this['summary']['total_sales'],
                'total_clients' => $this['summary']['total_clients'],
                'printed_photos' => $this['summary']['printed_photos'],
                'active_booths' => $this['summary']['active_booths'],
            ],
            'sales_chart' => [
                'labels' => $this['sales_chart']['labels'],
                'data' => $this['sales_chart']['data'],
            ],
            'staff_performance' => $this['staff_performance'],
            'photo_stats' => [
                'sold_percentage' => $this['photo_stats']['sold_percentage'],
                'sold_count' => $this['photo_stats']['sold_count'],
                'captured_count' => $this['photo_stats']['captured_count'],
            ],
        ];
    }
} 