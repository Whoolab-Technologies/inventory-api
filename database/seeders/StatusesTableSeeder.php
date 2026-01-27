<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StatusesTableSeeder extends Seeder
{
    public function run()
    {
        $statuses = $statuses = [
            [
                'name' => 'Unknown',
                'code' => 'UNKNOWN',
                'color' => '#6B7280',
                'description' => 'Default status when not set or unidentified.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Pending',
                'code' => 'PENDING',
                'color' => '#F59E0B',
                'description' => 'Awaiting action or approval.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Approved',
                'code' => 'APPROVED',
                'color' => '#3B82F6',
                'description' => 'Request has been approved.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Rejected',
                'code' => 'REJECTED',
                'color' => '#EF4444',
                'description' => 'Request was rejected.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Processing',
                'code' => 'PROCESSING',
                'color' => '#6366F1',
                'description' => 'Request is currently being processed.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Cancelled',
                'code' => 'CANCELLED',
                'color' => '#9CA3AF',
                'description' => 'Request was cancelled.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Completed',
                'code' => 'COMPLETED',
                'color' => '#10B981',
                'description' => 'Request is fully completed.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Partially Received',
                'code' => 'PARTIALLY_RECEIVED',
                'color' => '#F59E0B',
                'description' => 'Only some of the material has been received.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Awaiting Procurement',
                'code' => 'AWAITING_PROC',
                'color' => '#EAB308',
                'description' => 'Waiting for procurement process to complete.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'In Transit',
                'code' => 'IN_TRANSIT',
                'color' => '#0EA5E9',
                'description' => 'Materials are on the way.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Received',
                'code' => 'RECEIVED',
                'color' => '#22C55E',
                'description' => 'All materials have been received.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];


        DB::table('statuses')->insert($statuses);
    }
}
